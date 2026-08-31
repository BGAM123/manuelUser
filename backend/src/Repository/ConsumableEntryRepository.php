<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\ConsumableEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConsumableEntry>
 */
class ConsumableEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsumableEntry::class);
    }

    public function save(ConsumableEntry $consumableEntry): void
    {
        $this->getEntityManager()->persist($consumableEntry);
        $this->getEntityManager()->flush();
    }

    public function remove(ConsumableEntry $consumableEntry): void
    {
        $this->getEntityManager()->remove($consumableEntry);
        $this->getEntityManager()->flush();
    }

    public function softDelete(ConsumableEntry $consumableEntry): void
    {
        $consumableEntry->setDelete(true);
        $consumableEntry->setUpdatedAt(new \DateTime());
        $this->getEntityManager()->flush();
    }

    public function restore(ConsumableEntry $consumableEntry): void
    {
        $consumableEntry->setDelete(false);
        $consumableEntry->setUpdatedAt(new \DateTime());
        $this->getEntityManager()->flush();
    }

    public function getActiveById(int $id): ?ConsumableEntry
    {
        return $this->createQueryBuilder('ce')
            ->where('ce.id = :id')
            ->andWhere('ce.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByConsumable(int $consumableId): array
    {
        return $this->createQueryBuilder('ce')
            ->leftJoin('ce.service', 's')->addSelect('s')
            ->leftJoin('ce.consumable', 'c')->addSelect('c')
            ->where('ce.consumable = :consumableId')
            ->andWhere('ce.isDelete = false')
            ->orderBy('ce.dateEntree', 'DESC')
            ->setParameter('consumableId', $consumableId)
            ->getQuery()
            ->getResult();
    }

    public function findPaginated(int $page, int $limit, ?string $search = null, ?int $consumableId = null, ?string $isDelete = 'false'): array
    {
        $qb = $this->createQueryBuilder('ce')
            ->leftJoin('ce.service', 's')->addSelect('s')
            ->leftJoin('ce.consumable', 'c')->addSelect('c')
            ->orderBy('ce.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qb, 'ce', $isDelete);

        if ($search && '' !== $search) {
            $qb->andWhere('c.nom LIKE :search OR ce.observations LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($consumableId) {
            $qb->andWhere('ce.consumable = :consumableId')
                ->setParameter('consumableId', $consumableId);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(?string $search = null, ?int $consumableId = null, ?string $isDelete = 'false'): int
    {
        $qb = $this->createQueryBuilder('ce')
            ->select('COUNT(DISTINCT ce.id)')
            ->leftJoin('ce.consumable', 'c');
        SoftDeleteQueryFilter::apply($qb, 'ce', $isDelete);

        if ($search && '' !== $search) {
            $qb->andWhere('c.nom LIKE :search OR ce.observations LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($consumableId) {
            $qb->andWhere('ce.consumable = :consumableId')
                ->setParameter('consumableId', $consumableId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function sumQuantiteByConsumableAndService(int $consumableId, int $serviceId): string
    {
        $result = $this->createQueryBuilder('ce')
            ->select('COALESCE(SUM(ce.quantite), 0)')
            ->where('ce.consumable = :consumableId')
            ->andWhere('ce.service = :serviceId')
            ->andWhere('ce.isDelete = false')
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?: '0';
    }

    public function sumQuantiteByService(int $serviceId): array
    {
        return $this->createQueryBuilder('ce')
            ->select('c.id as consumableId, c.nom as designation, COALESCE(SUM(ce.quantite), 0) as stockInitial')
            ->leftJoin('ce.consumable', 'c')
            ->where('ce.service = :serviceId')
            ->andWhere('ce.isDelete = false')
            ->groupBy('c.id, c.nom')
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getResult();
    }
}
