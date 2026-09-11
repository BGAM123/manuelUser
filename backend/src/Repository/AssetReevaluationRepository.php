<?php

namespace App\Repository;

use App\Entity\AssetReevaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetReevaluation>
 */
class AssetReevaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetReevaluation::class);
    }

    public function save(AssetReevaluation $reevaluation): void
    {
        $this->getEntityManager()->persist($reevaluation);
        $this->getEntityManager()->flush();
    }

    public function remove(AssetReevaluation $reevaluation): void
    {
        $this->getEntityManager()->remove($reevaluation);
        $this->getEntityManager()->flush();
    }


     /**
     * Récupère les réévaluations avec filtres et pagination
     * 
     * @param array<string, mixed> $criteria
     * @return array{items: AssetReevaluation[], pagination: array{page: int, limit: int, total: int, totalPages: int}}
     */
    public function findWithFilters(
        array $criteria = [],
        int $page = 1,
        int $limit = 20,
        string $sortBy = 'createdAt',
        string $sortOrder = 'desc'
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.assets', 'a')
            ->leftJoin('r.service', 's')
            ->addSelect('a', 's');

        // Filtre par IDs de bien (plusieurs possibles)
        if (isset($criteria['asset_ids']) && is_array($criteria['asset_ids']) && !empty($criteria['asset_ids'])) {
            $qb->andWhere('a.id IN (:assetIds)')
               ->setParameter('assetIds', $criteria['asset_ids']);
        }
        // Support de l'ancien format (asset_id simple)
        elseif (isset($criteria['asset_id']) && !empty($criteria['asset_id'])) {
            $qb->andWhere('a.id = :assetId')
               ->setParameter('assetId', $criteria['asset_id']);
        }

        // Filtre par IDs de service (plusieurs possibles)
        if (isset($criteria['service_ids']) && is_array($criteria['service_ids']) && !empty($criteria['service_ids'])) {
            $qb->andWhere('s.id IN (:serviceIds)')
               ->setParameter('serviceIds', $criteria['service_ids']);
        }
        // Support de l'ancien format (service_id simple)
        elseif (isset($criteria['service_id']) && !empty($criteria['service_id'])) {
            $qb->andWhere('s.id = :serviceId')
               ->setParameter('serviceId', $criteria['service_id']);
        }

        // Filtre par date de début
        if (isset($criteria['date_from']) && !empty($criteria['date_from'])) {
            $qb->andWhere('r.dateReevaluation >= :dateFrom')
               ->setParameter('dateFrom', $criteria['date_from'] . ' 00:00:00');
        }

        // Filtre par date de fin
        if (isset($criteria['date_to']) && !empty($criteria['date_to'])) {
            $qb->andWhere('r.dateReevaluation <= :dateTo')
               ->setParameter('dateTo', $criteria['date_to'] . ' 23:59:59');
        }

        // Filtre par motif (recherche partielle)
        if (isset($criteria['motif']) && !empty($criteria['motif'])) {
            $qb->andWhere('r.motif LIKE :motif')
               ->setParameter('motif', '%' . $criteria['motif'] . '%');
        }

        // Filtre par méthode d'évaluation
        if (isset($criteria['methode_evaluation']) && !empty($criteria['methode_evaluation'])) {
            $qb->andWhere('r.methodeEvaluation = :methode')
               ->setParameter('methode', $criteria['methode_evaluation']);
        }

        // Compter le total (pour la pagination)
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(DISTINCT r.id)')
                         ->getQuery()
                         ->getSingleScalarResult();

        // Tri
        $validSortFields = ['createdAt', 'dateReevaluation', 'nouvelleValeur', 'valeurActuelle'];
        if (in_array($sortBy, $validSortFields)) {
            $qb->orderBy('r.' . $sortBy, $sortOrder);
        } else {
            $qb->orderBy('r.createdAt', 'DESC');
        }

        // Pagination
        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
           ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        $totalPages = ceil($total / $limit);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int) $total,
                'totalPages' => (int) $totalPages,
            ],
        ];
    }
}
