<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\ConsumableTransfer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConsumableTransfer>
 */
class ConsumableTransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsumableTransfer::class);
    }

    public function save(ConsumableTransfer $consumableTransfer): void
    {
        $this->getEntityManager()->persist($consumableTransfer);
        $this->getEntityManager()->flush();
    }

    public function remove(ConsumableTransfer $consumableTransfer): void
    {
        $this->getEntityManager()->remove($consumableTransfer);
        $this->getEntityManager()->flush();
    }

    public function softDelete(ConsumableTransfer $consumableTransfer): void
    {
        $consumableTransfer->setDelete(true);
        $consumableTransfer->setUpdatedAt(new \DateTime());
        $this->getEntityManager()->flush();
    }

    public function restore(ConsumableTransfer $consumableTransfer): void
    {
        $consumableTransfer->setDelete(false);
        $consumableTransfer->setUpdatedAt(new \DateTime());
        $this->getEntityManager()->flush();
    }

    public function getActiveById(int $id): ?ConsumableTransfer
    {
        return $this->createQueryBuilder('ct')
            ->where('ct.id = :id')
            ->andWhere('ct.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByConsumable(int $consumableId): array
    {
        return $this->createQueryBuilder('ct')
            ->leftJoin('ct.serviceDestination', 's')->addSelect('s')
            ->leftJoin('ct.consumable', 'c')->addSelect('c')
            ->leftJoin('ct.consumableBsp', 'cb')->addSelect('cb')
            ->where('ct.consumable = :consumableId')
            ->andWhere('ct.isDelete = false')
            ->orderBy('ct.dateTransfert', 'DESC')
            ->setParameter('consumableId', $consumableId)
            ->getQuery()
            ->getResult();
    }

    public function findByService(int $serviceId): array
    {
        return $this->createQueryBuilder('ct')
            ->leftJoin('ct.serviceDestination', 's')->addSelect('s')
            ->leftJoin('ct.consumable', 'c')->addSelect('c')
            ->leftJoin('ct.consumableBsp', 'cb')->addSelect('cb')
            ->where('ct.serviceDestination = :serviceId')
            ->andWhere('ct.isDelete = false')
            ->orderBy('ct.dateTransfert', 'DESC')
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getResult();
    }

    public function findPaginated(int $page, int $limit, ?string $search = null, ?int $consumableId = null, ?int $serviceId = null, ?string $statut = null, ?string $isDelete = 'false'): array
    {
        $qb = $this->createQueryBuilder('ct')
            ->leftJoin('ct.serviceDestination', 's')->addSelect('s')
            ->leftJoin('ct.consumable', 'c')->addSelect('c')
            ->leftJoin('ct.consumableBsp', 'cb')->addSelect('cb')
            ->orderBy('ct.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qb, 'ct', $isDelete);

        if ($search && '' !== $search) {
            $qb->andWhere('c.nom LIKE :search OR ct.observations LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($consumableId) {
            $qb->andWhere('ct.consumable = :consumableId')
                ->setParameter('consumableId', $consumableId);
        }

        if ($serviceId) {
            $qb->andWhere('ct.serviceDestination = :serviceId')
                ->setParameter('serviceId', $serviceId);
        }

        if ($statut) {
            $qb->andWhere('ct.statut = :statut')
                ->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(?string $search = null, ?int $consumableId = null, ?int $serviceId = null, ?string $statut = null, ?string $isDelete = 'false'): int
    {
        $qb = $this->createQueryBuilder('ct')
            ->select('COUNT(DISTINCT ct.id)')
            ->leftJoin('ct.consumable', 'c');
        SoftDeleteQueryFilter::apply($qb, 'ct', $isDelete);

        if ($search && '' !== $search) {
            $qb->andWhere('c.nom LIKE :search OR ct.observations LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($consumableId) {
            $qb->andWhere('ct.consumable = :consumableId')
                ->setParameter('consumableId', $consumableId);
        }

        if ($serviceId) {
            $qb->andWhere('ct.serviceDestination = :serviceId')
                ->setParameter('serviceId', $serviceId);
        }

        if ($statut) {
            $qb->andWhere('ct.statut = :statut')
                ->setParameter('statut', $statut);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function sumQuantiteReceivedByConsumableAndService(int $consumableId, int $serviceId): string
    {
        $result = $this->createQueryBuilder('ct')
            ->select('COALESCE(SUM(ct.quantite), 0)')
            ->where('ct.consumable = :consumableId')
            ->andWhere('ct.serviceDestination = :serviceId')
            ->andWhere('ct.isDelete = false')
            ->andWhere('ct.statut = :statut')
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->setParameter('statut', ConsumableTransfer::STATUT_TRANSFERE)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?: '0';
    }

    /**
     * 🔥 NOUVELLE MÉTHODE
     * Récupère le dernier transfert pour un consommable et un service (destination)
     */
    public function findLastByConsumableAndService(
        int $consumableId,
        int $serviceId
    ): ?ConsumableTransfer {
        return $this->createQueryBuilder('ct')
            ->where('ct.consumable = :consumableId')
            ->andWhere('ct.serviceDestination = :serviceId')
            ->andWhere('ct.isDelete = false')
            ->orderBy('ct.id', 'DESC')
            ->setMaxResults(1)
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getOneOrNullResult();
    }

/**
 * Récupère le stock actuel d'un service pour un consommable
 * Calcule le stock dynamiquement à partir de l'historique des transferts
 */
public function getCurrentStock(int $consumableId, int $serviceId): float
{
    $qb = $this->createQueryBuilder('ct')
        ->where('ct.consumable = :consumableId')
        ->andWhere('(ct.serviceDestination = :serviceId OR ct.serviceSource = :serviceId)')
        ->andWhere('ct.isDelete = false')
        ->orderBy('ct.id', 'ASC')
        ->setParameter('consumableId', $consumableId)
        ->setParameter('serviceId', $serviceId);
    
    $transfers = $qb->getQuery()->getResult();
    
    // Calculer le stock dynamiquement
    $stock = 0;
    
    foreach ($transfers as $transfer) {
        // 1. Stock initial (INITIAL) : pas un transfert réel, quantite = 0.
        // Le stock d'ouverture est porté par stockActuel.
        if ($transfer->getStatut() === ConsumableTransfer::STATUT_INITIAL) {
            if ($transfer->getServiceDestination()->getId() === $serviceId) {
                $stock = (float) $transfer->getStockActuel();
            }
        }
        // 2. Transfert reçu (TRANSFERE en destination)
        elseif ($transfer->getStatut() === ConsumableTransfer::STATUT_TRANSFERE) {
            // Si le service est le DESTINATION du transfert → ENTRÉE
            if ($transfer->getServiceDestination()->getId() === $serviceId) {
                $stock += (float) $transfer->getQuantite();
            }
            // Si le service est la SOURCE du transfert → SORTIE
            elseif ($transfer->getServiceSource() && $transfer->getServiceSource()->getId() === $serviceId) {
                $stock -= (float) $transfer->getQuantite();
            }
        }
        // 3. Sortie BSP (SORTI en source)
        elseif ($transfer->getStatut() === ConsumableTransfer::STATUT_SORTI) {
            if ($transfer->getServiceSource() && $transfer->getServiceSource()->getId() === $serviceId) {
                $stock -= (float) $transfer->getQuantite();
            }
        }
    }
    
    return max(0, $stock);
}

    /**
     * 🔥 MÉTHODE MODIFIÉE
     * Récupère tous les transferts pour un service (historique complet)
     * Cherche à la fois dans serviceDestination et serviceSource
     */
    public function findByConsumableAndService(
        int $consumableId,
        int $serviceId
    ): array {
        return $this->createQueryBuilder('ct')
            ->where('ct.consumable = :consumableId')
            ->andWhere('(ct.serviceDestination = :serviceId OR ct.serviceSource = :serviceId)')
            ->andWhere('ct.isDelete = false')
            ->orderBy('ct.id', 'ASC')
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getResult();
    }

    /**
     * 🔥 NOUVELLE MÉTHODE
     * Récupère tous les transferts après un ID (pour recalcul)
     */
    public function findAfter(int $id, int $consumableId, int $serviceId): array
    {
        return $this->createQueryBuilder('ct')
            ->where('ct.id > :id')
            ->andWhere('ct.consumable = :consumableId')
            ->andWhere('ct.serviceDestination = :serviceId')
            ->andWhere('ct.isDelete = false')
            ->orderBy('ct.id', 'ASC')
            ->setParameter('id', $id)
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getResult();
    }

    /**
     * 🔥 MÉTHODE MODIFIÉE
     * findPaginated - Ajouter le stock_actuel dans les résultats
     * (Pas besoin de modifier la requête, le champ sera chargé automatiquement)
     */
    // La méthode findPaginated reste identique, le champ stockActuel sera chargé via le mapping Doctrine

    /**
     * 🔥 NOUVELLE MÉTHODE
     * Récupère le dernier transfert SORTI pour un service source
     */
    public function findLastSortieByConsumableAndService(
        int $consumableId,
        int $serviceId
    ): ?ConsumableTransfer {
        return $this->createQueryBuilder('ct')
            ->where('ct.consumable = :consumableId')
            ->andWhere('ct.serviceSource = :serviceId')
            ->andWhere('ct.statut = :statut')
            ->andWhere('ct.isDelete = false')
            ->orderBy('ct.id', 'DESC')
            ->setMaxResults(1)
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->setParameter('statut', ConsumableTransfer::STATUT_SORTI)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function sumQuantiteSentByConsumableAndService(int $consumableId, int $serviceId): string
    {
        $result = $this->createQueryBuilder('ct')
            ->select('COALESCE(SUM(ct.quantite), 0)')
            ->where('ct.consumable = :consumableId')
            ->andWhere('ct.serviceSource = :serviceId')
            ->andWhere('ct.isDelete = false')
            ->andWhere('ct.statut = :statut')
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->setParameter('statut', ConsumableTransfer::STATUT_TRANSFERE)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?: '0';
    }

    public function sumSortiesByConsumableAndService(int $consumableId, int $serviceId): string
    {
        $result = $this->createQueryBuilder('ct')
            ->select('COALESCE(SUM(ct.quantite), 0)')
            ->where('ct.consumable = :consumableId')
            ->andWhere('ct.serviceSource = :serviceId')
            ->andWhere('ct.isDelete = false')
            ->andWhere('ct.statut = :statut')
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->setParameter('statut', ConsumableTransfer::STATUT_SORTI)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?: '0';
    }

    public function getMovementsByConsumableAndService(int $consumableId, int $serviceId): array
    {
        // Entrées initiales
        $entries = $this->getEntityManager()->createQueryBuilder()
            ->select('
                \'ENTREE\' as type,
                \'ENTREE\' as sens,
                ce.quantite as quantite,
                NULL as serviceSource,
                s.id as serviceDestination,
                DATE_FORMAT(ce.dateEntree, \'%Y-%m-%d\') as date,
                ce.observations
            ')
            ->from('App\Entity\ConsumableEntry', 'ce')
            ->leftJoin('ce.service', 's')
            ->where('ce.consumable = :consumableId')
            ->andWhere('ce.service = :serviceId')
            ->andWhere('ce.isDelete = false')
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getResult();

        // Transferts reçus
        $received = $this->createQueryBuilder('ct')
            ->select('
                \'TRANSFERT\' as type,
                \'ENTREE\' as sens,
                ct.quantite as quantite,
                ss.id as serviceSource,
                sd.id as serviceDestination,
                DATE_FORMAT(ct.dateTransfert, \'%Y-%m-%d\') as date,
                ct.observations
            ')
            ->leftJoin('ct.serviceSource', 'ss')
            ->leftJoin('ct.serviceDestination', 'sd')
            ->where('ct.consumable = :consumableId')
            ->andWhere('ct.serviceDestination = :serviceId')
            ->andWhere('ct.isDelete = false')
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getResult();

        // Transferts effectués
        $sent = $this->createQueryBuilder('ct')
            ->select('
                \'TRANSFERT\' as type,
                \'SORTIE\' as sens,
                ct.quantite as quantite,
                ss.id as serviceSource,
                sd.id as serviceDestination,
                DATE_FORMAT(ct.dateTransfert, \'%Y-%m-%d\') as date,
                ct.observations
            ')
            ->leftJoin('ct.serviceSource', 'ss')
            ->leftJoin('ct.serviceDestination', 'sd')
            ->where('ct.consumable = :consumableId')
            ->andWhere('ct.serviceSource = :serviceId')
            ->andWhere('ct.isDelete = false')
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getResult();

        return array_merge($entries, $received, $sent);
    }
}
