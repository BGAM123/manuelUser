<?php

namespace App\Repository;

use App\Entity\Bsp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bsp>
 */
class BspRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bsp::class);
    }

    public function save(Bsp $bsp): void
    {
        $this->getEntityManager()->persist($bsp);
        $this->getEntityManager()->flush();
    }

    public function findActiveById(int $id): ?Bsp
    {
        return $this->createQueryBuilder('b')
            ->where('b.id = :id')
            ->andWhere('b.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Bsp[]
     */
    public function findActiveByAssetExitId(int $assetExitId, ?int $serviceId = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.assetExit = :assetExitId')
            ->andWhere('b.isDelete = false')
            ->setParameter('assetExitId', $assetExitId)
            ->orderBy('b.id', 'ASC');

        if (null !== $serviceId) {
            $qb->andWhere('b.service = :serviceId')->setParameter('serviceId', $serviceId);
        }

        return $qb->getQuery()->getResult();
    }

    public function generateNextNumero(?\DateTimeImmutable $date = null): string
    {
        $year = ($date ?? new \DateTimeImmutable())->format('Y');
        $prefix = 'BSP-' . $year . '-';

        $last = $this->createQueryBuilder('b')
            ->select('b.numero')
            ->andWhere('b.numero LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('b.numero', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $next = 1;
        if (is_array($last) && isset($last['numero'])) {
            $suffix = substr((string) $last['numero'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
