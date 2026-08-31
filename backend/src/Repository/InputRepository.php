<?php

namespace App\Repository;

use App\Entity\Input;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InputRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Input::class);
    }

    public function save(Input $input, bool $flush = true): void
    {
        $this->getEntityManager()->persist($input);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function softDelete(Input $input): void
    {
        $input->setDelete(true);
        $this->getEntityManager()->flush();
    }

    public function restore(Input $input): void
    {
        $input->setDelete(false);
        $this->getEntityManager()->flush();
    }

    public function findWithChamp(int $id): ?Input
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.champ', 'c')
            ->addSelect('c')
            ->where('i.id = :id')
            ->andWhere('i.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Version simplifiée de findPaginated (sans jointure)
     */
    public function findPaginated(int $page, int $limit, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.champ', 'c')
            ->addSelect('c')
            ->where('i.isDelete = false')
            ->orderBy('i.valeur', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Version simplifiée de findPaginatedByChampId
     */
    public function findPaginatedByChampId(int $champId, int $page, int $limit): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.champ', 'c')
            ->addSelect('c')
            ->where('i.champ = :champId')
            ->andWhere('i.isDelete = false')
            ->setParameter('champId', $champId)
            ->orderBy('i.valeur', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(?string $search = null): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.isDelete = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByChampId(int $champId): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.champ = :champId')
            ->andWhere('i.isDelete = false')
            ->setParameter('champId', $champId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * ✅ Récupère tous les inputs d'un bien
     * @param int $assetId
     * @return Input[]
     */
    public function findByAssetId(int $assetId): array
    {
        return $this->createQueryBuilder('i')
            ->innerJoin('i.assets', 'a')
            ->where('a.id = :assetId')
            ->andWhere('i.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->orderBy('i.valeur', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Récupère les inputs d'un bien pour un champ spécifique
     * @param int $assetId
     * @param int $champId
     * @return Input[]
     */
    public function findByAssetIdAndChampId(int $assetId, int $champId): array
    {
        return $this->createQueryBuilder('i')
            ->innerJoin('i.assets', 'a')
            ->where('a.id = :assetId')
            ->andWhere('i.champ = :champId')
            ->andWhere('i.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->setParameter('champId', $champId)
            ->orderBy('i.valeur', 'ASC')
            ->getQuery()
            ->getResult();
    }
}