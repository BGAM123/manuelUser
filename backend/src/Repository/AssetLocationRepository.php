<?php

namespace App\Repository;

use App\Entity\AssetLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetLocation>
 */
class AssetLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetLocation::class);
    }

    public function save(AssetLocation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AssetLocation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Retourne la dernière localisation d'un bien (la plus récente).
     */
    public function findLatestByAsset(int $assetId): ?AssetLocation
    {
        return $this->createQueryBuilder('al')
            ->where('al.asset = :assetId')
            ->setParameter('assetId', $assetId)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne l'historique complet des localisations d'un bien, trié par date décroissante.
     *
     * @return AssetLocation[]
     */
    public function findHistoryByAsset(int $assetId): array
    {
        return $this->createQueryBuilder('al')
            ->where('al.asset = :assetId')
            ->setParameter('assetId', $assetId)
            ->orderBy('al.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
