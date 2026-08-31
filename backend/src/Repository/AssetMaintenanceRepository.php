<?php

namespace App\Repository;

use App\Entity\Asset;
use App\Entity\AssetMaintenance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetMaintenance>
 */
class AssetMaintenanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetMaintenance::class);
    }

    public function save(AssetMaintenance $maintenance): void
    {
        $this->getEntityManager()->persist($maintenance);
        $this->getEntityManager()->flush();
    }

    public function remove(AssetMaintenance $maintenance): void
    {
        $this->getEntityManager()->remove($maintenance);
        $this->getEntityManager()->flush();
    }

    /**
     * Maintenance ouverte la plus récente d'un bien (dateRecuperation IS NULL), ou null s'il
     * n'en a aucune. Utilisée à la fois pour synchroniser Asset::$statut (AssetMaintenanceService)
     * et pour l'affichage (AssetResponseBuilder::resolveOpenMaintenance).
     */
    public function findOpenForAsset(Asset $asset): ?AssetMaintenance
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.assets', 'a')
            ->andWhere('a.id = :assetId')
            ->andWhere('m.dateRecuperation IS NULL')
            ->setParameter('assetId', $asset->getId())
            ->orderBy('m.dateIntervention', 'DESC')
            ->addOrderBy('m.createdAt', 'DESC')  // Ajouter un second tri pour plus de précision
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }


    /**
     * ✅ Compte le nombre total de maintenances pour un bien donné
     */
    public function countMaintenancesForAsset(Asset $asset): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('COUNT(m.id) as total')
            ->innerJoin('m.assets', 'a')
            ->where('a.id = :assetId')
            ->setParameter('assetId', $asset->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * ✅ Calcule le coût total des maintenances pour un bien donné
     */
    public function getTotalMaintenanceCostForAsset(Asset $asset): ?string
    {
        $result = $this->createQueryBuilder('m')
            ->select('SUM(m.cout) as total')
            ->innerJoin('m.assets', 'a')
            ->where('a.id = :assetId')
            ->andWhere('m.cout IS NOT NULL')
            ->setParameter('assetId', $asset->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (string) $result : null;
    }
    

    /**
     * Toutes les maintenances enregistrées (pas seulement les ouvertes), paginées.
     *
     * @return AssetMaintenance[]
     */
    public function findPaginated(int $page, int $limit, ?int $assetId = null, ?string $statut = null, ?int $userId = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.etatBien', 'eb')->addSelect('eb')
            ->orderBy('m.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if (null !== $assetId) {
            $qb->innerJoin('m.assets', 'a')
                ->andWhere('a.id = :assetId')
                ->setParameter('assetId', $assetId);
        }
        if (null !== $statut) {
            $qb->andWhere('m.statut = :statut')->setParameter('statut', $statut);
        }
        if (null !== $userId) {
            $qb->innerJoin('m.assets', 'a')
                ->leftJoin('a.assignments', 'ass')
                ->leftJoin('ass.user', 'u')
                ->leftJoin('a.createdBy', 'creator')
                ->andWhere('(u.id = :userId AND ass.dateFin IS NULL) OR creator.id = :userId')
                ->setParameter('userId', $userId);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(?int $assetId = null, ?string $statut = null, ?int $userId = null): int
    {
        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(DISTINCT m.id)');

        if (null !== $assetId) {
            $qb->innerJoin('m.assets', 'a')
                ->andWhere('a.id = :assetId')
                ->setParameter('assetId', $assetId);
        }
        if (null !== $statut) {
            $qb->andWhere('m.statut = :statut')->setParameter('statut', $statut);
        }
        if (null !== $userId) {
            $qb->innerJoin('m.assets', 'a')
                ->leftJoin('a.assignments', 'ass')
                ->leftJoin('ass.user', 'u')
                ->leftJoin('a.createdBy', 'creator')
                ->andWhere('(u.id = :userId AND ass.dateFin IS NULL) OR creator.id = :userId')
                ->setParameter('userId', $userId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
