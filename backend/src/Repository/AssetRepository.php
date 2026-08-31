<?php

namespace App\Repository;

use App\Entity\Asset;
use App\Entity\AssetMaintenance;
use App\Entity\AssetType;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Asset>
 */
class AssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Asset::class);
    }

    public function save(Asset $asset, bool $flush = true): void
    {
        $this->getEntityManager()->persist($asset);
        $this->getEntityManager()->flush();  // ✅ ESSENTIEL
    }

    public function getActiveById(int $id): ?Asset
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.categories', 'c')->addSelect('c')
            ->leftJoin('a.assetTypes', 't')->addSelect('t')
            ->leftJoin('a.etatBiens', 'e')->addSelect('e')
            ->leftJoin('a.services', 's')->addSelect('s')
            ->leftJoin('a.projects', 'p')->addSelect('p')
            ->leftJoin('a.piecesJointes', 'pj')->addSelect('pj')
            ->andWhere('a.id = :id')
            // ->andWhere('a.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPaginatedForInventory(int $page, int $limit, ?array $categoryIds = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.isDelete = false')
            ->orderBy('a.nom', 'ASC')
            // Charger les relations nécessaires pour éviter les N+1 queries
            ->leftJoin('a.categories', 'categories')->addSelect('categories')
            ->leftJoin('a.assetTypes', 'assetTypes')->addSelect('assetTypes')
            ->leftJoin('a.etatBiens', 'etatBiens')->addSelect('etatBiens')
            ->leftJoin('a.projects', 'projects')->addSelect('projects')
            ->leftJoin('a.assignments', 'assignments')->addSelect('assignments')
            ->leftJoin('assignments.user', 'assignmentUser')->addSelect('assignmentUser')
            ->leftJoin('assignmentUser.service', 'assignmentUserService')->addSelect('assignmentUserService')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        // Filtrer par catégories si spécifiées
        if ($categoryIds !== null && !empty($categoryIds)) {
            $qb->andWhere('categories.id IN (:categoryIds)')
               ->setParameter('categoryIds', $categoryIds);
        }

        // ✅ Filtre par statut
        if (null !== $statut && '' !== trim($statut)) {
            $qb->andWhere('a.statut = :statut')
                ->setParameter('statut', strtoupper($statut));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre total de biens pour l'inventaire
     * 
     * @param array<int>|null $categoryIds IDs des catégories à filtrer (null = toutes)
     * @return int
     */
    public function countForInventory(?array $categoryIds = null, ?string $statut = null): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id)')
            ->where('a.isDelete = false');

        // Filtrer par catégories si spécifiées
        if ($categoryIds !== null && !empty($categoryIds)) {
            $qb->innerJoin('a.categories', 'categories')
               ->andWhere('categories.id IN (:categoryIds)')
               ->setParameter('categoryIds', $categoryIds);
        }

        // ✅ Filtre par statut
        if (null !== $statut && '' !== trim($statut)) {
            $qb->andWhere('a.statut = :statut')
                ->setParameter('statut', strtoupper($statut));
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findActiveAssetsByUser(int $userId): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.assignments', 'ass')
            ->leftJoin('ass.user', 'u')
            ->andWhere('u.id = :userId')
            ->andWhere('a.isDelete = false')
            ->andWhere('a.statut = :statut')
            ->andWhere('ass.dateFin IS NULL') // Affectations actives
            ->setParameter('userId', $userId)
            ->setParameter('statut', 'ACTIF')
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

     /**
     * Récupère les biens par champ
     * @param int $champId
     * @return Asset[]
     */
    public function findByChampId(int $champId): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.champs', 'c')
            ->where('c.id = :champId')
            ->andWhere('c.isDelete = false')
            ->andWhere('a.isDelete = false')
            ->setParameter('champId', $champId)
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }


      /**
     * Récupère les champs d'un bien
     * @param int $assetId
     * @return array
     */
    public function findChampsByAssetId(int $assetId): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.champs', 'c')
            ->where('a.id = :assetId')
            ->andWhere('c.isDelete = false')
            ->andWhere('a.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existsByReference(string $reference, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.reference = :reference')
            ->setParameter('reference', $reference);

        if (null !== $excludeId) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function generateNextReference(?\DateTimeImmutable $date = null): string
    {
        $year = ($date ?? new \DateTimeImmutable())->format('Y');
        $prefix = 'PAT-' . $year . '-';

        $last = $this->createQueryBuilder('a')
            ->select('a.reference')
            ->andWhere('a.reference LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('a.reference', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $next = 1;
        if (is_array($last) && isset($last['reference'])) {
            $suffix = substr((string) $last['reference'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

   
    /**
     * @return Asset[]
     */
    public function findPaginated(
        int $page,
        int $limit,
        bool $isDelete,
        ?string $search = null,
        ?int $categoryId = null,
        ?int $assetTypeId = null,
        ?array $serviceId = null,
        ?array $projectIds = null,
        ?int $exercice = null,
        ?string $statut = null,
        ?int $userId = null,
        ?string $securise = null,
        ?string $received = null,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.categories', 'c')->addSelect('c')
            ->leftJoin('a.assetTypes', 't')->addSelect('t')
            ->leftJoin('a.etatBiens', 'e')->addSelect('e')
            ->leftJoin('a.services', 's')->addSelect('s')
            ->leftJoin('a.projects', 'p')->addSelect('p')
            ->leftJoin('a.assignments', 'ass')
            ->leftJoin('ass.service', 'assService')
            ->leftJoin('ass.user', 'u')
            ->leftJoin('a.createdBy', 'creator')
            ->leftJoin('a.assetSecurities', 'assetSec')
            ->leftJoin('assetSec.security', 'sec')
            ->andWhere('a.isDelete = :isDelete')
            ->setParameter('isDelete', $isDelete)
            ->orderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('a.nom LIKE :search OR a.reference LIKE :search OR a.numeroSerie LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        if (null !== $categoryId) {
            $qb->andWhere('c.id = :categoryId')->setParameter('categoryId', $categoryId);
        }
        if (null !== $assetTypeId) {
            $qb->andWhere('t.id = :assetTypeId')->setParameter('assetTypeId', $assetTypeId);
        }
        // ✅ Filtre par service : trouver les utilisateurs du service, puis leurs biens affectés
        if (null !== $serviceId && !empty($serviceId)) {
            $qb->innerJoin('ass.user', 'serviceUser')
               ->innerJoin('serviceUser.service', 'userService')
               ->andWhere('userService.id IN (:serviceId) AND ass.dateFin IS NULL')
               ->setParameter('serviceId', $serviceId);
        }
        if (null !== $projectIds && !empty($projectIds)) {
            $qb->andWhere('p.id IN (:projectIds)')->setParameter('projectIds', $projectIds);
        }
        // ✅ Filtre par exercice
        if (null !== $exercice) {
            $qb->andWhere('a.exercice = :exercice')->setParameter('exercice', $exercice);
        }
        if (null !== $statut) {
            $qb->andWhere('a.statut = :statut')->setParameter('statut', $statut);
        } else {
            // Par défaut, exclure les biens SORTIS
            $qb->andWhere('a.statut != :statutDefault')->setParameter('statutDefault', 'SORTIE');
        }

        // Filtre par utilisateur connecté (détenteur actuel OU créateur)
        if (null !== $userId) {
            $qb->andWhere('(u.id = :userId AND ass.dateFin IS NULL) OR creator.id = :userId')
                ->setParameter('userId', $userId);
        }
        
        // ✅ Filtre par sécurisation
        if (null !== $securise && '' !== trim($securise)) {
            $isSecured = filter_var($securise, FILTER_VALIDATE_BOOLEAN);
            if ($isSecured) {
                // Biens sécurisés : ont au moins une sécurisation active
                $qb->andWhere('sec.id IS NOT NULL')
                    ->andWhere('sec.isDelete = false')
                    ->andWhere('assetSec.isDelete = false');
            } else {
                // Biens non sécurisés : n'ont aucune sécurisation active
                $qb->andWhere('sec.id IS NULL OR (sec.isDelete = true OR assetSec.isDelete = true)');
            }
        }

        // ✅ Filtre par accusé de réception (via le champ received des affectations)
        if (null !== $received && '' !== trim($received)) {
            $isReceived = filter_var($received, FILTER_VALIDATE_BOOLEAN);
            if ($isReceived) {
                // Biens dont l'affectation actuelle a été accusée réception
                $qb->andWhere('ass.received = true AND ass.detenteur = true');
            } else {
                // Biens dont l'affectation actuelle n'a pas été accusée réception
                $qb->andWhere('(ass.received = false OR ass.received IS NULL) AND ass.detenteur = true');
            }
        }

        if (null !== $statut) {
            $qb->andWhere('a.statut = :statut')->setParameter('statut', $statut);
        } else {
            // Par défaut, exclure les biens SORTI
            $qb->andWhere('a.statut != :statutDefault')->setParameter('statutDefault', 'SORTIE');
        }

        return $qb->getQuery()->getResult();
    }

public function countAll(
    bool $isDelete,
    ?string $search = null,
    ?int $categoryId = null,
    ?int $assetTypeId = null,
    ?array $serviceId = null,
    ?array $projectIds = null,
    ?int $exercice = null,
    ?string $statut = null,
    ?int $userId = null,
    ?string $securise = null,
    ?string $received = null,
): int {
    $qb = $this->createQueryBuilder('a')
        ->select('COUNT(DISTINCT a.id)')
        ->leftJoin('a.assignments', 'ass')
        ->leftJoin('ass.user', 'u')
        ->leftJoin('a.createdBy', 'creator')
        ->andWhere('a.isDelete = :isDelete')
        ->setParameter('isDelete', $isDelete);

    // Recherche
    if (null !== $search && '' !== $search) {
        $qb->andWhere('a.nom LIKE :search OR a.reference LIKE :search OR a.numeroSerie LIKE :search')
            ->setParameter('search', '%' . $search . '%');
    }

    // ✅ AJOUTER LES JOIN UNIQUEMENT SI NÉCESSAIRE
    // Chaque filtre doit avoir son JOIN AVANT la condition WHERE

    if (null !== $categoryId) {
        $qb->innerJoin('a.categories', 'c')
           ->andWhere('c.id = :categoryId')
           ->setParameter('categoryId', $categoryId);
    }

    if (null !== $assetTypeId) {
        $qb->innerJoin('a.assetTypes', 't')
           ->andWhere('t.id = :assetTypeId')
           ->setParameter('assetTypeId', $assetTypeId);
    }

    if (null !== $serviceId && !empty($serviceId)) {
        $qb->innerJoin('ass.user', 'serviceUser')
           ->innerJoin('serviceUser.service', 'userService')
           ->andWhere('userService.id IN (:serviceId) AND ass.dateFin IS NULL')
           ->setParameter('serviceId', $serviceId);
    }

    if (null !== $projectIds && !empty($projectIds)) {
        $qb->innerJoin('a.projects', 'p')
           ->andWhere('p.id IN (:projectIds)')
           ->setParameter('projectIds', $projectIds);
    }

    if (null !== $exercice) {
        $qb->andWhere('a.exercice = :exercice')
           ->setParameter('exercice', $exercice);
    }

    if (null !== $statut) {
        $qb->andWhere('a.statut = :statut')
           ->setParameter('statut', $statut);
    } else {
        // Par défaut, exclure les biens SORTIS
        $qb->andWhere('a.statut != :statutDefault')
           ->setParameter('statutDefault', 'SORTIE');
    }

    // Filtre par utilisateur connecté (détenteur actuel OU créateur)
    if (null !== $userId) {
        $qb->andWhere('(u.id = :userId AND ass.dateFin IS NULL) OR creator.id = :userId')
           ->setParameter('userId', $userId);
    }
    
    // ✅ Filtre par sécurisation
    if (null !== $securise && '' !== trim($securise)) {
        $qb->leftJoin('a.assetSecurities', 'assetSec')
            ->leftJoin('assetSec.security', 'sec');
        $isSecured = filter_var($securise, FILTER_VALIDATE_BOOLEAN);
        if ($isSecured) {
            // Biens sécurisés : ont au moins une sécurisation active
            $qb->andWhere('sec.id IS NOT NULL')
                ->andWhere('sec.isDelete = false')
                ->andWhere('assetSec.isDelete = false');
        } else {
            // Biens non sécurisés : n'ont aucune sécurisation active
            $qb->andWhere('sec.id IS NULL OR (sec.isDelete = true OR assetSec.isDelete = true)');
        }
    }

    // ✅ Filtre par accusé de réception (via le champ received des affectations)
    if (null !== $received && '' !== trim($received)) {
        $isReceived = filter_var($received, FILTER_VALIDATE_BOOLEAN);
        if ($isReceived) {
            // Biens dont l'affectation actuelle a été accusée réception
            $qb->andWhere('ass.received = true AND ass.detenteur = true');
        } else {
            // Biens dont l'affectation actuelle n'a pas été accusée réception
            $qb->andWhere('(ass.received = false OR ass.received IS NULL) AND ass.detenteur = true');
        }
    }

    return (int) $qb->getQuery()->getSingleScalarResult();
}

    public function softDelete(Asset $asset): void
    {
        $asset->setIsDelete(true);
        $asset->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve tous les assets qui ont une relation ManyToMany directe avec une catégorie donnée
     */
    public function findByCategory(Category $category): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.categories', 'c')
            ->andWhere('c.id = :categoryId')
            ->setParameter('categoryId', $category->getId())
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les assets qui ont une relation ManyToMany avec un type de bien donnée
     */
    public function findByAssetType(AssetType $assetType): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.assetTypes', 't')
            ->andWhere('t.id = :assetTypeId')
            ->setParameter('assetTypeId', $assetType->getId())
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les biens regroupés par catégorie avec statistiques de maintenance
     * @return array<array{id: int, nom: string, biens: array, coutTotalMaintenance: float, nombreMaintenances: int}>
     */
    public function findGroupedByCategoryWithMaintenanceStats(
        bool $isDelete,
        ?string $search = null,
        ?int $categoryId = null,
        ?int $assetTypeId = null,
        ?int $serviceId = null,
        ?int $exercice = null,
        ?string $statut = null,
        ?int $userId = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.categories', 'c')->addSelect('c')
            ->leftJoin('a.assetTypes', 't')->addSelect('t')
            ->leftJoin('a.etatBiens', 'e')->addSelect('e')
            ->leftJoin('a.services', 's')->addSelect('s')
            ->leftJoin('a.projects', 'p')->addSelect('p')
            ->leftJoin('a.assignments', 'ass')
            ->leftJoin('ass.user', 'u')
            ->leftJoin('a.createdBy', 'creator')
            ->leftJoin('a.maintenances', 'm')
            ->andWhere('a.isDelete = :isDelete')
            ->setParameter('isDelete', $isDelete)
            ->orderBy('c.nom', 'ASC')
            ->addOrderBy('a.nom', 'ASC');

        if (null !== $search && '' !== $search) {
            $qb->andWhere('a.nom LIKE :search OR a.reference LIKE :search OR a.numeroSerie LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        if (null !== $categoryId) {
            $qb->andWhere('c.id = :categoryId')->setParameter('categoryId', $categoryId);
        }
        if (null !== $assetTypeId) {
            $qb->andWhere('t.id = :assetTypeId')->setParameter('assetTypeId', $assetTypeId);
        }
        if (null !== $serviceId) {
            $qb->andWhere('s.id = :serviceId')->setParameter('serviceId', $serviceId);
        }
        if (null !== $exercice) {
            $qb->andWhere('a.exercice = :exercice')->setParameter('exercice', $exercice);
        }
        if (null !== $statut) {
            $qb->andWhere('a.statut = :statut')->setParameter('statut', $statut);
        }
        if (null !== $userId) {
            $qb->andWhere('(u.id = :userId AND ass.dateFin IS NULL) OR creator.id = :userId')
                ->setParameter('userId', $userId);
        }

        $assets = $qb->getQuery()->getResult();

        // Regrouper par catégorie
        $grouped = [];
        foreach ($assets as $asset) {
            $categories = $asset->getCategories();
            if ($categories->isEmpty()) {
                // Catégorie par défaut ou sans catégorie
                $categoryKey = 'sans_categorie';
                $categoryName = 'Sans catégorie';
                $categoryIdVal = null;
            } else {
                $category = $categories->first();
                $categoryKey = 'cat_' . $category->getId();
                $categoryName = $category->getNom();
                $categoryIdVal = $category->getId();
            }

            if (!isset($grouped[$categoryKey])) {
                $grouped[$categoryKey] = [
                    'id' => $categoryIdVal,
                    'nom' => $categoryName,
                    'biens' => [],
                    'coutTotalMaintenance' => 0,
                    'nombreMaintenances' => 0,
                ];
            }

            $grouped[$categoryKey]['biens'][] = $asset;

            // Calculer les statistiques de maintenance pour ce bien
            $maintenances = $asset->getMaintenances();
            foreach ($maintenances as $maintenance) {
                $cout = $maintenance->getCout();
                if (is_numeric($cout)) {
                    $grouped[$categoryKey]['coutTotalMaintenance'] += (float) $cout;
                }
                $grouped[$categoryKey]['nombreMaintenances']++;
            }
        }

        return array_values($grouped);
    }

    /**
     * Récupère les biens paginés d'un utilisateur via les affectations actuelles
     */
    // public function findPaginatedByUser(int $userId, int $page, int $limit): array
    // {
    //     $qb = $this->createQueryBuilder('a')
    //         ->innerJoin('a.assignments', 'ass')
    //         ->innerJoin('ass.user', 'u')
    //         ->leftJoin('a.categories', 'c')->addSelect('c')
    //         ->leftJoin('a.assetTypes', 't')->addSelect('t')
    //         ->leftJoin('a.etatBiens', 'e')->addSelect('e')
    //         ->leftJoin('a.services', 's')->addSelect('s')
    //         ->leftJoin('a.projects', 'p')->addSelect('p')
    //         ->where('u.id = :userId')
    //         ->andWhere('a.isDelete = false')
    //         ->andWhere('ass.isDelete = false')
    //         ->andWhere('a.statut = :statut')
    //         ->andWhere('ass.dateFin IS NULL') // Affectations actives
    //         ->setParameter('userId', $userId)
    //         ->setParameter('statut', 'SORTIE')
    //         ->setParameter('statut', 'SORTIE')
    //         ->orderBy('a.id', 'DESC')
    //         ->setFirstResult(($page - 1) * $limit)
    //         ->setMaxResults($limit);
        
    //     return $qb->getQuery()->getResult();
    // }

    public function findPaginatedByUser(int $userId, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.assignments', 'ass')
            ->innerJoin('ass.user', 'u')
            ->leftJoin('a.categories', 'c')->addSelect('c')
            ->leftJoin('a.assetTypes', 't')->addSelect('t')
            ->leftJoin('a.etatBiens', 'e')->addSelect('e')
            ->leftJoin('a.services', 's')->addSelect('s')
            ->leftJoin('a.projects', 'p')->addSelect('p')
            ->where('u.id = :userId')
            ->andWhere('a.isDelete = false')
            ->andWhere('ass.isDelete = false')
            ->andWhere('a.statut NOT IN (:statutsExclus)')
            ->andWhere('ass.dateFin IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('statutsExclus', ['SORTIE', 'SORTIE'])
            ->orderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre total de biens d'un utilisateur
     */
    public function countByUser(int $userId): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id)')
            ->innerJoin('a.assignments', 'ass')
            ->innerJoin('ass.user', 'u')
            ->where('u.id = :userId')
            ->andWhere('a.isDelete = false')
            ->andWhere('ass.isDelete = false')
            ->andWhere('ass.dateFin IS NULL')
            ->setParameter('userId', $userId);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Biens actifs paginés pour le tableau d'amortissement, filtrés par catégorie et/ou
     * plage de date d'acquisition (le contrôleur traduit `exercice` en dateDebut/dateFin :
     * pas de fonction DQL YEAR() enregistrée dans ce projet, et Asset::$exercice est
     * commenté dans l'entité, cf. findPaginated/countAll).
     *
     * @return Asset[]
     */
    // public function findActiveForDepreciationSchedule(
    //     int $page,
    //     int $limit,
    //     ?int $categoryId = null,
    //     ?\DateTimeInterface $dateDebut = null,
    //     ?\DateTimeInterface $dateFin = null,
    //     ?int $userId = null,
    // ): array {
    //     $qb = $this->createQueryBuilder('a')
    //         ->leftJoin('a.categories', 'c')->addSelect('c')
    //         ->leftJoin('a.assetTypes', 't')->addSelect('t')
    //         ->leftJoin('a.assignments', 'ass')
    //         ->leftJoin('ass.user', 'u')
    //         ->leftJoin('a.createdBy', 'creator')
    //         ->andWhere('a.isDelete = false')
    //         ->orderBy('a.dateAcquisition', 'ASC')
    //         ->addOrderBy('a.id', 'ASC')
    //         ->setFirstResult(($page - 1) * $limit)
    //         ->setMaxResults($limit);

    //     if (null !== $categoryId) {
    //         $qb->andWhere('c.id = :categoryId')->setParameter('categoryId', $categoryId);
    //     }
    //     if (null !== $dateDebut) {
    //         $qb->andWhere('a.dateAcquisition >= :dateDebut')->setParameter('dateDebut', $dateDebut);
    //     }
    //     if (null !== $dateFin) {
    //         $qb->andWhere('a.dateAcquisition <= :dateFin')->setParameter('dateFin', $dateFin);
    //     }
    //     if (null !== $userId) {
    //         $qb->andWhere('(u.id = :userId AND ass.dateFin IS NULL) OR creator.id = :userId')
    //             ->setParameter('userId', $userId);
    //     }

        
        

    //     return $qb->getQuery()->getResult();
    // }

    public function findActiveForDepreciationSchedule(
    int $page,
    int $limit,
    ?int $categoryId = null,
    ?\DateTimeInterface $dateDebut = null,
    ?\DateTimeInterface $dateFin = null,
    ?int $userId = null,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.categories', 'c')->addSelect('c')
            ->leftJoin('a.assetTypes', 't')->addSelect('t')
            ->leftJoin('a.assignments', 'ass')
            ->leftJoin('ass.user', 'u')
            ->leftJoin('a.createdBy', 'creator')
            ->andWhere('a.isDelete = false')
            ->andWhere('a.statut NOT IN (:statutsExclus)')
            ->setParameter('statutsExclus', ['SORTIE', 'SORTIE'])
            ->orderBy('a.dateAcquisition', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if (null !== $categoryId) {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        if (null !== $dateDebut) {
            $qb->andWhere('a.dateAcquisition >= :dateDebut')
                ->setParameter('dateDebut', $dateDebut);
        }

        if (null !== $dateFin) {
            $qb->andWhere('a.dateAcquisition <= :dateFin')
                ->setParameter('dateFin', $dateFin);
        }

        if (null !== $userId) {
            $qb->andWhere(
                '(u.id = :userId AND ass.dateFin IS NULL AND ass.isDelete = false)
                OR creator.id = :userId'
            )
            ->setParameter('userId', $userId);
        }

        return $qb->getQuery()->getResult();
    }

    // public function countActiveForDepreciationSchedule(
    //     ?int $categoryId = null,
    //     ?\DateTimeInterface $dateDebut = null,
    //     ?\DateTimeInterface $dateFin = null,
    //     ?int $userId = null,
    // ): int {
    //     $qb = $this->createQueryBuilder('a')
    //         ->select('COUNT(DISTINCT a.id)')
    //         ->leftJoin('a.assignments', 'ass')
    //         ->leftJoin('ass.user', 'u')
    //         ->leftJoin('a.createdBy', 'creator')
    //         ->andWhere('a.isDelete = false');

    //     if (null !== $categoryId) {
    //         $qb->innerJoin('a.categories', 'c')
    //             ->andWhere('c.id = :categoryId')
    //             ->setParameter('categoryId', $categoryId);
    //     }
    //     if (null !== $dateDebut) {
    //         $qb->andWhere('a.dateAcquisition >= :dateDebut')->setParameter('dateDebut', $dateDebut);
    //     }
    //     if (null !== $dateFin) {
    //         $qb->andWhere('a.dateAcquisition <= :dateFin')->setParameter('dateFin', $dateFin);
    //     }
    //     if (null !== $userId) {
    //         $qb->andWhere('(u.id = :userId AND ass.dateFin IS NULL) OR creator.id = :userId')
    //             ->setParameter('userId', $userId);
    //     }

    //     return (int) $qb->getQuery()->getSingleScalarResult();
    // }

    public function countActiveForDepreciationSchedule(
    ?int $categoryId = null,
    ?\DateTimeInterface $dateDebut = null,
    ?\DateTimeInterface $dateFin = null,
    ?int $userId = null,
    ): int {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id)')
            ->leftJoin('a.assignments', 'ass')
            ->leftJoin('ass.user', 'u')
            ->leftJoin('a.createdBy', 'creator')
            ->andWhere('a.isDelete = false')
            ->andWhere('a.statut NOT IN (:statutsExclus)')
            ->setParameter('statutsExclus', ['SORTIE', 'SORTIE']);

        if (null !== $categoryId) {
            $qb->innerJoin('a.categories', 'c')
                ->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        if (null !== $dateDebut) {
            $qb->andWhere('a.dateAcquisition >= :dateDebut')
                ->setParameter('dateDebut', $dateDebut);
        }

        if (null !== $dateFin) {
            $qb->andWhere('a.dateAcquisition <= :dateFin')
                ->setParameter('dateFin', $dateFin);
        }

        if (null !== $userId) {
        $qb->andWhere(
            '(u.id = :userId AND ass.dateFin IS NULL AND ass.isDelete = false)
             OR creator.id = :userId'
        )
        ->setParameter('userId', $userId);
    }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    

    /**
     * Biens actuellement en maintenance, paginés, filtrés par catégorie/type de bien/plage
     * de dateIntervention. "En maintenance" = AssetMaintenance liée avec dateRecuperation
     * IS NULL (pas de champ statut fiable sur Asset, cf. StatisticsRepository::getGeneralStatistics
     * qui utilise déjà exactement cette même relation). Une ligne par maintenance ouverte :
     * comme AssetMaintenance est ManyToMany avec Asset et qu'aucune contrainte n'empêche un
     * bien d'avoir plusieurs maintenances ouvertes simultanément, on sélectionne 'a' ET 'm'
     * explicitement (pas juste addSelect sur la collection) pour que chaque paire
     * bien/maintenance-ouverte soit une ligne de résultat à part entière, et que
     * setFirstResult/setMaxResults pagine correctement sur ces lignes.
     *
     * @return array<int, array{0: Asset, 1: \App\Entity\AssetMaintenance}>
     */
    public function findActiveInMaintenance(
        int $page,
        int $limit,
        ?int $categoryId = null,
        ?int $assetTypeId = null,
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null,
        ?int $serviceId = null,
    ): array {
        // Doctrine hydrate les entités jointes comme des objets racines (et non comme
        // des tableaux [Asset, AssetMaintenance]). On récupère donc d'abord les IDs
        // des paires, puis les entités en lots. Cela conserve une pagination par paire
        // bien/maintenance et évite le "Cannot use object of type Asset as array".
        $qb = $this->createQueryBuilder('a')
            ->select('a.id AS assetId', 'm.id AS maintenanceId')
            ->innerJoin('a.maintenances', 'm')
            ->andWhere('a.isDelete = false')
            ->andWhere('m.dateRecuperation IS NULL')
            ->orderBy('m.dateIntervention', 'DESC')
            ->addOrderBy('a.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if (null !== $categoryId) {
            $qb->innerJoin('a.categories', 'c')
                ->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }
        if (null !== $assetTypeId) {
            $qb->innerJoin('a.assetTypes', 't')
                ->andWhere('t.id = :assetTypeId')
                ->setParameter('assetTypeId', $assetTypeId);
        }
        if (null !== $dateDebut) {
            $qb->andWhere('m.dateIntervention >= :dateDebut')->setParameter('dateDebut', $dateDebut);
        }
        if (null !== $dateFin) {
            $qb->andWhere('m.dateIntervention <= :dateFin')->setParameter('dateFin', $dateFin);
        }
        if (null !== $serviceId) {
            $qb->innerJoin('a.services', 's')
                ->andWhere('s.id = :serviceId')
                ->setParameter('serviceId', $serviceId);
        }

        /** @var list<array{assetId: string, maintenanceId: string}> $identifiers */
        $identifiers = $qb->getQuery()->getArrayResult();
        if ([] === $identifiers) {
            return [];
        }

        $assetIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['assetId'], $identifiers)));
        $maintenanceIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['maintenanceId'], $identifiers)));

        /** @var Asset[] $assets */
        $assets = $this->createQueryBuilder('a')
            ->leftJoin('a.categories', 'c')->addSelect('c')
            ->leftJoin('a.assetTypes', 't')->addSelect('t')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $assetIds)
            ->getQuery()
            ->getResult();
        $assetsById = [];
        foreach ($assets as $asset) {
            $assetsById[$asset->getId()] = $asset;
        }

        /** @var AssetMaintenance[] $maintenances */
        $maintenances = $this->getEntityManager()->getRepository(AssetMaintenance::class)
            ->createQueryBuilder('m')
            ->leftJoin('m.etatBien', 'eb')->addSelect('eb')
            ->andWhere('m.id IN (:ids)')
            ->setParameter('ids', $maintenanceIds)
            ->getQuery()
            ->getResult();
        $maintenancesById = [];
        foreach ($maintenances as $maintenance) {
            $maintenancesById[$maintenance->getId()] = $maintenance;
        }

        $pairs = [];
        foreach ($identifiers as $identifier) {
            $asset = $assetsById[(int) $identifier['assetId']] ?? null;
            $maintenance = $maintenancesById[(int) $identifier['maintenanceId']] ?? null;
            if (null !== $asset && null !== $maintenance) {
                $pairs[] = [$asset, $maintenance];
            }
        }

        return $pairs;
    }

    public function countActiveInMaintenance(
        ?int $categoryId = null,
        ?int $assetTypeId = null,
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null,
        ?int $serviceId = null,
    ): int {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(m.id)')
            ->innerJoin('a.maintenances', 'm')
            ->andWhere('a.isDelete = false')
            ->andWhere('m.dateRecuperation IS NULL');

        if (null !== $categoryId) {
            $qb->innerJoin('a.categories', 'c')
                ->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }
        if (null !== $assetTypeId) {
            $qb->innerJoin('a.assetTypes', 't')
                ->andWhere('t.id = :assetTypeId')
                ->setParameter('assetTypeId', $assetTypeId);
        }
        if (null !== $dateDebut) {
            $qb->andWhere('m.dateIntervention >= :dateDebut')->setParameter('dateDebut', $dateDebut);
        }
        if (null !== $dateFin) {
            $qb->andWhere('m.dateIntervention <= :dateFin')->setParameter('dateFin', $dateFin);
        }
        if (null !== $serviceId) {
            $qb->innerJoin('a.services', 's')
                ->andWhere('s.id = :serviceId')
                ->setParameter('serviceId', $serviceId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
