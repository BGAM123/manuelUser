<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\TypeOrganigramme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeOrganigramme>
 */
class TypeOrganigrammeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeOrganigramme::class);
    }

    /**
     * Récupère les types d'organigrammes paginés
     *
     * @param int $page
     * @param int $limit
     * @param string|null $searchQuery
     * @param string|null $isDelete false (défaut) = actifs, true = corbeille, all = tous
     * @return TypeOrganigramme[]
     */
    public function findPaginatedTypes(int $page, int $limit, ?string $searchQuery = null, ?string $isDelete = 'false'): array
    {
        $qb = $this->createQueryBuilder('t');
        SoftDeleteQueryFilter::apply($qb, 't', $isDelete);

        if ($searchQuery) {
            $qb->andWhere('t.nom LIKE :searchQuery')
               ->setParameter('searchQuery', '%' . $searchQuery . '%');
        }

        return $qb
            ->orderBy('t.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les types d'organigrammes selon les critères
     *
     * @param string|null $searchQuery
     * @param string|null $isDelete false (défaut) = actifs, true = corbeille, all = tous
     * @return int
     */
    public function countTypes(?string $searchQuery = null, ?string $isDelete = 'false'): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)');
        SoftDeleteQueryFilter::apply($qb, 't', $isDelete);

        if ($searchQuery) {
            $qb->andWhere('t.nom LIKE :searchQuery')
               ->setParameter('searchQuery', '%' . $searchQuery . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function softDelete(TypeOrganigramme $typeOrganigramme): void
    {
        $typeOrganigramme->setIsDelete(true);
        $this->getEntityManager()->flush();
    }

    /**
     * Récupère les types d'organigrammes non supprimés avec leurs services
     *
     * @param int|null $typeOrganigrammeId
     * @return TypeOrganigramme|null
     */
    public function findByIdWithServices(?int $typeOrganigrammeId): ?TypeOrganigramme
    {
        if (null === $typeOrganigrammeId) {
            return null;
        }

        return $this->createQueryBuilder('t')
            ->leftJoin('t.services', 's')
            ->addSelect('s')
            ->andWhere('t.id = :id')
            ->andWhere('t.isDelete = false')
            ->setParameter('id', $typeOrganigrammeId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
