<?php

namespace App\Repository\Core;

use App\Entity\Core\TypeReponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeReponse>
 */
class TypeReponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeReponse::class);
    }

    /**
     * Trouve tous les types de réponse actifs et non supprimés
     *
     * @return TypeReponse[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('tr')
            ->andWhere('tr.isActive = :active')
            ->andWhere('tr.isDelete = :delete')
            ->setParameter('active', true)
            ->setParameter('delete', false)
            ->orderBy('tr.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un type de réponse par son nom
     *
     * @param string $nom
     * @return TypeReponse|null
     */
    public function findOneByNom(string $nom): ?TypeReponse
    {
        return $this->createQueryBuilder('tr')
            ->andWhere('tr.nom = :nom')
            ->andWhere('tr.isDelete = :delete')
            ->setParameter('nom', $nom)
            ->setParameter('delete', false)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre de réponses associées à ce type
     *
     * @param int $typeReponseId
     * @return int
     */
    public function countReponses(int $typeReponseId): int
    {
        return $this->createQueryBuilder('tr')
            ->select('COUNT(r.id)')
            ->leftJoin('tr.reponses', 'r')
            ->andWhere('tr.id = :id')
            ->setParameter('id', $typeReponseId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les types de réponse avec pagination et recherche
     *
     * @param int $page
     * @param int $limit
     * @param string|null $search
     * @return TypeReponse[]
     */
    public function findWithPagination(int $page, int $limit, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('tr')
            ->andWhere('tr.isDelete = :delete')
            ->setParameter('delete', false)
            ->orderBy('tr.nom', 'ASC');

        if ($search) {
            $qb->andWhere('tr.nom LIKE :search OR tr.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->setFirstResult(($page - 1) * $limit)
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte le nombre total de types de réponse avec recherche
     *
     * @param string|null $search
     * @return int
     */
    public function countTotal(?string $search = null): int
    {
        $qb = $this->createQueryBuilder('tr')
            ->select('COUNT(tr.id)')
            ->andWhere('tr.isDelete = :delete')
            ->setParameter('delete', false);

        if ($search) {
            $qb->andWhere('tr.nom LIKE :search OR tr.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Compte le nombre de réponses par type de réponse
     *
     * @return array
     */
    public function countReponsesParType(): array
    {
        return $this->createQueryBuilder('tr')
            ->select('tr.id, tr.nom, COUNT(r.id) as nombreReponses')
            ->leftJoin('tr.reponses', 'r')
            ->andWhere('tr.isDelete = :delete')
            ->setParameter('delete', false)
            ->groupBy('tr.id')
            ->orderBy('tr.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
