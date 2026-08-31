<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\Consumable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Consumable>
 */
class ConsumableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consumable::class);
    }

    public function save(Consumable $consumable): void
    {
        $this->getEntityManager()->persist($consumable);
        $this->getEntityManager()->flush();
    }

    public function remove(Consumable $consumable): void
    {
        $this->getEntityManager()->remove($consumable);
        $this->getEntityManager()->flush();
    }

    public function softDelete(Consumable $consumable): void
    {
        $consumable->setDelete(true);
        $consumable->setUpdatedAt(new \DateTime());
        $this->getEntityManager()->flush();
    }

    public function restore(Consumable $consumable): void
    {
        $consumable->setDelete(false);
        $consumable->setUpdatedAt(new \DateTime());
        $this->getEntityManager()->flush();
    }

    public function getActiveById(int $id): ?Consumable
    {
        return $this->createQueryBuilder('c')
            ->where('c.id = :id')
            ->andWhere('c.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Calcul du stock actuel d'un consomptible
     * stock_actuel = stock_ouverture - SUM(transferts reels) + SUM(retours_bsp)
     * où stock_ouverture = SUM(ConsumableEntry) si des entrées existent (cas où un service
     * est affecté dès la création, cf. ConsumableService::create), sinon quantite_initiale
     * du consomptible (cas sans service). Les deux ne sont jamais additionnés : dès qu'un
     * service est affecté, l'entrée créée automatiquement porte déjà la quantité initiale,
     * l'additionner en plus doublerait le stock.
     */
    public function getStockActuel(int $consumableId): float
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.quantite')
            ->where('c.id = :id')
            ->andWhere('c.isDelete = false')
            ->setParameter('id', $consumableId);

        $quantiteInitiale = (float) $qb->getQuery()->getSingleScalarResult();

        // Somme des entrées
        $qbEntrees = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(ce.quantite), 0)')
            ->from('App\Entity\ConsumableEntry', 'ce')
            ->where('ce.consumable = :id')
            ->andWhere('ce.isDelete = false')
            ->setParameter('id', $consumableId);
        $totalEntrees = (float) $qbEntrees->getQuery()->getSingleScalarResult();

        // Somme des transferts
        $qbTransferts = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(ct.quantite), 0)')
            ->from('App\Entity\ConsumableTransfer', 'ct')
            ->where('ct.consumable = :id')
            ->andWhere('ct.isDelete = false')
            ->setParameter('id', $consumableId);
        $totalTransferts = (float) $qbTransferts->getQuery()->getSingleScalarResult();

        // Somme des quantités consommées sur les transferts reçus par les services.
        $qbConsommes = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(ct.quantityConsumed), 0)')
            ->from('App\Entity\ConsumableTransfer', 'ct')
            ->where('ct.consumable = :id')
            ->andWhere('ct.isDelete = false')
            ->andWhere('ct.quantityConsumed IS NOT NULL')
            ->setParameter('id', $consumableId);
        $totalConsommes = (float) $qbConsommes->getQuery()->getSingleScalarResult();

        // Somme des retours BSP (quantiteServie des BSP avec retour = true)
        $qbRetours = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(b.quantiteServie), 0)')
            ->from('App\Entity\Bsp', 'b')
            ->innerJoin('App\Entity\ConsumableBsp', 'cb', 'WITH', 'cb.bsp = b.id')
            ->innerJoin('App\Entity\ConsumableTransfer', 'ct', 'WITH', 'ct.id = cb.consumableTransfer')
            ->where('ct.consumable = :id')
            ->andWhere('b.retour = true')
            ->andWhere('b.isDelete = false')
            ->andWhere('ct.isDelete = false')
            ->andWhere('cb.isDelete = false')
            ->setParameter('id', $consumableId);
        $totalRetours = (float) $qbRetours->getQuery()->getSingleScalarResult();

        $stockOuverture = $totalEntrees > 0 ? $totalEntrees : $quantiteInitiale;

        return max(0, $stockOuverture - $totalTransferts + $totalRetours - $totalConsommes);
    }

    /**
     * Quantité totale consommée d'un consomptible, tous services confondus
     * (somme de ConsumableTransfer::quantityConsumed sur les transferts actifs).
     */
    public function getTotalQuantityConsumed(int $consumableId): float
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(ct.quantityConsumed), 0)')
            ->from('App\Entity\ConsumableTransfer', 'ct')
            ->where('ct.consumable = :id')
            ->andWhere('ct.isDelete = false')
            ->andWhere('ct.quantityConsumed IS NOT NULL')
            ->setParameter('id', $consumableId);

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Calcul du stock par service destination
     */
    public function getStockParService(int $consumableId): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(ct.serviceDestination) as serviceId, s.nom as serviceNom, COALESCE(SUM(ct.quantite), 0) as quantite')
            ->from('App\Entity\ConsumableTransfer', 'ct')
            ->leftJoin('ct.serviceDestination', 's')
            ->where('ct.consumable = :id')
            ->andWhere('ct.isDelete = false')
            ->groupBy('ct.serviceDestination, s.nom')
            ->setParameter('id', $consumableId);

        return $qb->getQuery()->getResult();
    }

    /**
     * Bilan d'un consomptible sur une période
     */
    public function getBilan(int $consumableId, ?\DateTimeInterface $dateDebut, ?\DateTimeInterface $dateFin): array
    {
        // Stock au début de la période (calculé comme stock actuel - entrées + transferts de la période)
        $stockActuel = $this->getStockActuel($consumableId);

        // Entrées sur la période
        $qbEntrees = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(ce.quantite), 0)')
            ->from('App\Entity\ConsumableEntry', 'ce')
            ->where('ce.consumable = :id')
            ->andWhere('ce.isDelete = false');
        
        if ($dateDebut) {
            $qbEntrees->andWhere('ce.dateEntree >= :dateDebut')->setParameter('dateDebut', $dateDebut);
        }
        if ($dateFin) {
            $qbEntrees->andWhere('ce.dateEntree <= :dateFin')->setParameter('dateFin', $dateFin);
        }
        $qbEntrees->setParameter('id', $consumableId);
        $totalEntrees = (float) $qbEntrees->getQuery()->getSingleScalarResult();

        // Transferts sur la période
        $qbTransferts = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(ct.quantite), 0)')
            ->from('App\Entity\ConsumableTransfer', 'ct')
            ->where('ct.consumable = :id')
            ->andWhere('ct.isDelete = false');
        
        if ($dateDebut) {
            $qbTransferts->andWhere('ct.dateTransfert >= :dateDebut')->setParameter('dateDebut', $dateDebut);
        }
        if ($dateFin) {
            $qbTransferts->andWhere('ct.dateTransfert <= :dateFin')->setParameter('dateFin', $dateFin);
        }
        $qbTransferts->setParameter('id', $consumableId);
        $totalTransferts = (float) $qbTransferts->getQuery()->getSingleScalarResult();

        // Stock début de période (approximation)
        $stockDebutPeriode = $stockActuel - $totalEntrees + $totalTransferts;

        // Par service
        $parService = $this->getStockParService($consumableId);

        return [
            // 'stockDebutPeriode' => max(0, $stockDebutPeriode),
            'totalEntrees' => $totalEntrees,
            'totalTransfere' => $totalTransferts,
            'stockActuel' => $stockActuel,
            'parService' => $parService,
        ];
    }

    /**
     * Bilan global sur une période
     */
    public function getBilanGlobal(?\DateTimeInterface $dateDebut, ?\DateTimeInterface $dateFin): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isDelete = false')
            ->orderBy('c.nom', 'ASC');

        $consomptibles = $qb->getQuery()->getResult();

        $bilan = [];
        foreach ($consomptibles as $consomptible) {
            $bilan[] = [
                'consumable' => [
                    'id' => $consomptible->getId(),
                    'nom' => $consomptible->getNom(),
                ],
                'bilan' => $this->getBilan($consomptible->getId(), $dateDebut, $dateFin),
            ];
        }

        return $bilan;
    }

    /**
     * Liste paginée des consomptibles
     */
    public function findPaginated(int $page, int $limit, ?string $search = null, ?int $serviceId = null, ?string $isDelete = 'false', ?array $categoryIds = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.category', 'cat')->addSelect('cat')
            ->leftJoin('c.assetType', 'at')->addSelect('at')
            ->leftJoin('c.assetSubType', 'ast')->addSelect('ast')
            ->leftJoin('c.service', 's')->addSelect('s')
            ->orderBy('c.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qb, 'c', $isDelete);

        if ($search && '' !== $search) {
            $qb->andWhere('c.nom LIKE :search OR c.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($serviceId) {
            $qb->andWhere('c.service = :serviceId')
                ->setParameter('serviceId', $serviceId);
        }
        // 🔥 FILTRE PAR CATÉGORIES
        if ($categoryIds && !empty($categoryIds)) {
            $qb->andWhere('cat.id IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre total de consomptibles
     */
    public function countAll(?string $search = null, ?int $serviceId = null, ?string $isDelete = 'false', ?array $categoryIds = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)')
            ->leftJoin('c.category', 'cat');
        SoftDeleteQueryFilter::apply($qb, 'c', $isDelete);

        if ($search && '' !== $search) {
            $qb->andWhere('c.nom LIKE :search OR c.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($serviceId) {
            $qb->andWhere('c.service = :serviceId')
                ->setParameter('serviceId', $serviceId);
        }

        // 🔥 FILTRE PAR CATÉGORIES
        if ($categoryIds && !empty($categoryIds)) {
            $qb->andWhere('cat.id IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Trouve les consommables avec des mouvements (entries ou transferts) pour les services donnés
     */
    public function findWithMovements(array $filters): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.category', 'cat')->addSelect('cat')
            ->distinct();

        // Filtrer par service si fourni
        if (isset($filters['serviceId'])) {
            $qb->innerJoin('App\Entity\ConsumableEntry', 'ce', 'WITH', 'ce.consumable = c.id')
                ->andWhere('ce.service = :serviceId')
                ->andWhere('ce.isDelete = false')
                ->setParameter('serviceId', $filters['serviceId']);
        } else {
            // Sinon, prendre tous les consommables avec des entries
            $qb->innerJoin('App\Entity\ConsumableEntry', 'ce', 'WITH', 'ce.consumable = c.id')
                ->andWhere('ce.isDelete = false');
        }

        // Filtrer par catégorie si fourni
        if (isset($filters['categorieId'])) {
            $qb->andWhere('c.category = :categorieId')
                ->setParameter('categorieId', $filters['categorieId']);
        }

        // Filtrer par consommable si fourni
        if (isset($filters['consumableId'])) {
            $qb->andWhere('c.id = :consumableId')
                ->setParameter('consumableId', $filters['consumableId']);
        }

        // Filtre search
        if (isset($filters['search']) && $filters['search'] !== '') {
            $qb->andWhere('c.nom LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $qb->andWhere('c.isDelete = false')
            ->orderBy('c.nom', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les consommables avec des mouvements
     */
    public function countWithMovements(array $filters): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)');

        // Filtrer par service si fourni
        if (isset($filters['serviceId'])) {
            $qb->innerJoin('App\Entity\ConsumableEntry', 'ce', 'WITH', 'ce.consumable = c.id')
                ->andWhere('ce.service = :serviceId')
                ->andWhere('ce.isDelete = false')
                ->setParameter('serviceId', $filters['serviceId']);
        } else {
            $qb->innerJoin('App\Entity\ConsumableEntry', 'ce', 'WITH', 'ce.consumable = c.id')
                ->andWhere('ce.isDelete = false');
        }

        // Filtrer par catégorie si fourni
        if (isset($filters['categorieId'])) {
            $qb->andWhere('c.category = :categorieId')
                ->setParameter('categorieId', $filters['categorieId']);
        }

        // Filtrer par consommable si fourni
        if (isset($filters['consumableId'])) {
            $qb->andWhere('c.id = :consumableId')
                ->setParameter('consumableId', $filters['consumableId']);
        }

        // Filtre search
        if (isset($filters['search']) && $filters['search'] !== '') {
            $qb->andWhere('c.nom LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $qb->andWhere('c.isDelete = false');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les services qui ont des entrées ou des transferts pour un consommable
     */
    public function findServicesWithStock(int $consumableId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Services avec des entrées
        $sqlEntries = "SELECT DISTINCT s.id, s.nom 
                      FROM service s
                      INNER JOIN consumable_entry ce ON ce.service_id = s.id
                      WHERE ce.consumable_id = :consumableId 
                      AND ce.is_delete = 0 
                      AND s.is_active = 1";

        // Services avec des transferts reçus (statut = TRANSFERE)
        $sqlReceived = "SELECT DISTINCT s.id, s.nom 
                        FROM service s
                        INNER JOIN consumable_transfer ct ON ct.service_destination_id = s.id
                        WHERE ct.consumable_id = :consumableId 
                        AND ct.is_delete = 0 
                        AND ct.statut = 'TRANSFERE'
                        AND s.is_active = 1";

        // Services avec des transferts envoyés (statut = TRANSFERE)
        $sqlSent = "SELECT DISTINCT s.id, s.nom 
                    FROM service s
                    INNER JOIN consumable_transfer ct ON ct.service_source_id = s.id
                    WHERE ct.consumable_id = :consumableId 
                    AND ct.is_delete = 0 
                    AND ct.statut = 'TRANSFERE'
                    AND s.is_active = 1";

        $entries = $conn->executeQuery($sqlEntries, ['consumableId' => $consumableId])->fetchAllAssociative();
        $received = $conn->executeQuery($sqlReceived, ['consumableId' => $consumableId])->fetchAllAssociative();
        $sent = $conn->executeQuery($sqlSent, ['consumableId' => $consumableId])->fetchAllAssociative();

        // Fusionner et dédupliquer
        $services = [];
        foreach (array_merge($entries, $received, $sent) as $service) {
            $services[$service['id']] = $service;
        }

        return array_values($services);
    }

    /**
     * Calcule les stocks pour tous les consommables et services en une seule requête
     */
    public function calculateAllStocks(array $filters): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Construire la requête SQL avec des sous-requêtes pour calculer les stocks
        $sql = "
            SELECT 
                c.id as consumableId,
                c.nom as designation,
                cat.id as categorieId,
                cat.nom as categorieNom,
                s.id as serviceId,
                s.nom as serviceNom,
                COALESCE(
                    (SELECT COALESCE(SUM(ce.quantite), 0) 
                     FROM consumable_entry ce 
                     WHERE ce.consumable_id = c.id 
                     AND ce.service_id = s.id 
                     AND ce.is_delete = 0), 0
                ) as stockInitial,
                COALESCE(
                    (SELECT COALESCE(SUM(ct.quantite), 0) 
                     FROM consumable_transfer ct 
                     WHERE ct.consumable_id = c.id 
                     AND ct.service_destination_id = s.id 
                     AND ct.is_delete = 0 
                     AND ct.statut = 'TRANSFERE'), 0
                ) as totalTransfertsRecus,
                COALESCE(
                    (SELECT COALESCE(SUM(ct.quantite), 0) 
                     FROM consumable_transfer ct 
                     WHERE ct.consumable_id = c.id 
                     AND ct.service_source_id = s.id 
                     AND ct.is_delete = 0 
                     AND ct.statut = 'TRANSFERE'), 0
                ) as totalTransfertsEffectues,
                COALESCE(
                    (SELECT COALESCE(SUM(ct.quantite), 0) 
                     FROM consumable_transfer ct 
                     WHERE ct.consumable_id = c.id 
                     AND ct.service_source_id = s.id 
                     AND ct.is_delete = 0 
                     AND ct.statut = 'SORTI'), 0
                ) as totalSorties
            FROM consumable c
            LEFT JOIN category cat ON c.category_id = cat.id
            CROSS JOIN service s
            WHERE c.is_delete = 0
            AND s.is_active = 1
            AND (
                EXISTS (
                    SELECT 1 FROM consumable_entry ce 
                    WHERE ce.consumable_id = c.id 
                    AND ce.service_id = s.id 
                    AND ce.is_delete = 0
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_transfer ct 
                    WHERE ct.consumable_id = c.id 
                    AND ct.service_destination_id = s.id 
                    AND ct.is_delete = 0 
                    AND ct.statut = 'TRANSFERE'
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_transfer ct 
                    WHERE ct.consumable_id = c.id 
                    AND ct.service_source_id = s.id 
                    AND ct.is_delete = 0 
                    AND ct.statut = 'TRANSFERE'
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_transfer ct 
                    WHERE ct.consumable_id = c.id 
                    AND ct.service_source_id = s.id 
                    AND ct.is_delete = 0 
                    AND ct.statut = 'SORTI'
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_entry ce 
                    WHERE ce.consumable_id = c.id 
                    AND ce.is_delete = 0
                )
            )
        ";

        $params = [];

        // Filtre par service
        if (isset($filters['serviceId'])) {
            $sql .= " AND s.id = :serviceId";
            $params['serviceId'] = $filters['serviceId'];
        }

        // Filtre par catégorie
        if (isset($filters['categorieId'])) {
            $sql .= " AND cat.id = :categorieId";
            $params['categorieId'] = $filters['categorieId'];
        }

        // Filtre par consommable
        if (isset($filters['consumableId'])) {
            $sql .= " AND c.id = :consumableId";
            $params['consumableId'] = $filters['consumableId'];
        }

        // Filtre search
        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND c.nom LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $results = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        // Filtrer les lignes où tout est à 0 (pas de stock)
        $results = array_filter($results, function($row) {
            return $row['stockInitial'] > 0 
                || $row['totalTransfertsRecus'] > 0 
                || $row['totalTransfertsEffectues'] > 0 
                || $row['totalSorties'] > 0;
        });

        // Calculer le stock actuel pour chaque ligne
        foreach ($results as &$row) {
            $totalEntrees = bcadd($row['stockInitial'], $row['totalTransfertsRecus'], 2);
            $stockActuel = bcsub($totalEntrees, $row['totalTransfertsEffectues'], 2);
            $stockActuel = bcsub($stockActuel, $row['totalSorties'], 2);
            $stockActuel = max(0, (float) $stockActuel);
            
            $row['totalEntrees'] = $totalEntrees;
            $row['stockActuel'] = (string) $stockActuel;
        }

        return $results;
    }

     /**
     * 🔥 NOUVELLE MÉTHODE
     * Met à jour le stock actuel d'un consommable
     */
    public function updateStockActuel(Consumable $consumable, float $stock): void
    {
        $consumable->setStockActuel((string) $stock);
        $this->getEntityManager()->flush();
    }

    /**
     * 🔥 NOUVELLE MÉTHODE
     * Récupère le stock global d'un consommable depuis les transferts
     * stock_global = stock_initial - total_sorties + total_retours_bsp
     */
    public function getStockActuelFromTransfers(int $consumableId): float
    {
        // Stock initial (INITIAL) : ce n'est pas un transfert réel, quantite = 0.
        // Le stock d'ouverture est porté par stockActuel.
        $qbInitial = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(ct.stockActuel), 0)')
            ->from('App\Entity\ConsumableTransfer', 'ct')
            ->where('ct.consumable = :consumableId')
            ->andWhere('ct.statut = :statut')
            ->andWhere('ct.isDelete = false')
            ->setParameter('consumableId', $consumableId)
            ->setParameter('statut', ConsumableTransfer::STATUT_INITIAL);
        $stockInitial = (float) $qbInitial->getQuery()->getSingleScalarResult();

        // Total sorties (SORTI)
        $qbSorties = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(ct.quantite), 0)')
            ->from('App\Entity\ConsumableTransfer', 'ct')
            ->where('ct.consumable = :consumableId')
            ->andWhere('ct.statut = :statut')
            ->andWhere('ct.isDelete = false')
            ->setParameter('consumableId', $consumableId)
            ->setParameter('statut', ConsumableTransfer::STATUT_SORTI);
        $totalSorties = (float) $qbSorties->getQuery()->getSingleScalarResult();

        // Retours BSP
        $qbRetours = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(b.quantiteServie), 0)')
            ->from('App\Entity\Bsp', 'b')
            ->innerJoin('App\Entity\ConsumableBsp', 'cb', 'WITH', 'cb.bsp = b.id')
            ->innerJoin('App\Entity\ConsumableTransfer', 'ct', 'WITH', 'ct.id = cb.consumableTransfer')
            ->where('ct.consumable = :consumableId')
            ->andWhere('b.retour = true')
            ->andWhere('b.isDelete = false')
            ->andWhere('ct.isDelete = false')
            ->andWhere('cb.isDelete = false')
            ->setParameter('consumableId', $consumableId);
        $totalRetours = (float) $qbRetours->getQuery()->getSingleScalarResult();

        return $stockInitial - $totalSorties + $totalRetours;
    }

    /**
     * Compte les services distincts ayant du stock pour les filtres donnés
     */
    public function countServicesWithStock(array $filters): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT COUNT(DISTINCT s.id)
            FROM consumable c
            LEFT JOIN category cat ON c.category_id = cat.id
            CROSS JOIN service s
            WHERE c.is_delete = 0
            AND s.is_active = 1
            AND (
                EXISTS (
                    SELECT 1 FROM consumable_entry ce 
                    WHERE ce.consumable_id = c.id 
                    AND ce.service_id = s.id 
                    AND ce.is_delete = 0
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_transfer ct 
                    WHERE ct.consumable_id = c.id 
                    AND ct.service_destination_id = s.id 
                    AND ct.is_delete = 0 
                    AND ct.statut = 'TRANSFERE'
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_transfer ct 
                    WHERE ct.consumable_id = c.id 
                    AND ct.service_source_id = s.id 
                    AND ct.is_delete = 0 
                    AND ct.statut = 'TRANSFERE'
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_transfer ct 
                    WHERE ct.consumable_id = c.id 
                    AND ct.service_source_id = s.id 
                    AND ct.is_delete = 0 
                    AND ct.statut = 'SORTI'
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_entry ce 
                    WHERE ce.consumable_id = c.id 
                    AND ce.is_delete = 0
                )
            )
        ";

        $params = [];

        if (isset($filters['serviceId'])) {
            $sql .= " AND s.id = :serviceId";
            $params['serviceId'] = $filters['serviceId'];
        }

        if (isset($filters['categorieId'])) {
            $sql .= " AND cat.id = :categorieId";
            $params['categorieId'] = $filters['categorieId'];
        }

        if (isset($filters['consumableId'])) {
            $sql .= " AND c.id = :consumableId";
            $params['consumableId'] = $filters['consumableId'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND c.nom LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        return (int) $conn->executeQuery($sql, $params)->fetchOne();
    }
}
