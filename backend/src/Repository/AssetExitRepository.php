<?php

namespace App\Repository;

use App\Entity\AssetExit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetExit>
 */
class AssetExitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetExit::class);
    }

    public function save(AssetExit $assetExit): void
    {
        $this->getEntityManager()->persist($assetExit);
        $this->getEntityManager()->flush();
    }

    public function remove(AssetExit $assetExit): void
    {
        $this->getEntityManager()->remove($assetExit);
        $this->getEntityManager()->flush();
    }

    public function findByAssetId(int $assetId): ?AssetExit
    {
        return $this->createQueryBuilder('ae')
            ->where('ae.asset = :assetId')
            ->andWhere('ae.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveById(int $id): ?AssetExit
    {
        return $this->createQueryBuilder('ae')
            ->where('ae.id = :id')
            ->andWhere('ae.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
