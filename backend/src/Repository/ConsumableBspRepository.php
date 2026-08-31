<?php

namespace App\Repository;

use App\Entity\ConsumableBsp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConsumableBsp>
 */
class ConsumableBspRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsumableBsp::class);
    }

    public function save(ConsumableBsp $consumableBsp): void
    {
        $this->getEntityManager()->persist($consumableBsp);
        $this->getEntityManager()->flush();
    }

    public function remove(ConsumableBsp $consumableBsp): void
    {
        $this->getEntityManager()->remove($consumableBsp);
        $this->getEntityManager()->flush();
    }

    public function softDelete(ConsumableBsp $consumableBsp): void
    {
        $consumableBsp->setDelete(true);
        $this->getEntityManager()->flush();
    }

    public function restore(ConsumableBsp $consumableBsp): void
    {
        $consumableBsp->setDelete(false);
        $this->getEntityManager()->flush();
    }

    public function getActiveById(int $id): ?ConsumableBsp
    {
        return $this->createQueryBuilder('cb')
            ->where('cb.id = :id')
            ->andWhere('cb.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByConsumableTransfer(int $consumableTransferId): ?ConsumableBsp
    {
        return $this->createQueryBuilder('cb')
            ->where('cb.consumableTransfer = :consumableTransferId')
            ->andWhere('cb.isDelete = false')
            ->setParameter('consumableTransferId', $consumableTransferId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByBsp(int $bspId): ?ConsumableBsp
    {
        return $this->createQueryBuilder('cb')
            ->where('cb.bsp = :bspId')
            ->andWhere('cb.isDelete = false')
            ->setParameter('bspId', $bspId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
