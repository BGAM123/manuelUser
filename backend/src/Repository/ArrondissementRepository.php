<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\Arrondissement;
use App\Entity\Departement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Arrondissement>
 */
class ArrondissementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Arrondissement::class);
    }

    public function save(Arrondissement $arrondissement, bool $flush = true): void
    {
        $this->getEntityManager()->persist($arrondissement);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function getArrondissementByIdIncludingDeleted(int $id): ?Arrondissement
    {
        return $this->find($id);
    }

    public function getActiveArrondissementById(int $id): ?Arrondissement
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.id = :id')
            ->andWhere('a.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByNom(string $nom, Departement $departement, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.nom = :nom')
            ->andWhere('a.departement = :departement')
            ->setParameter('nom', $nom)
            ->setParameter('departement', $departement);

        if (null !== $excludeId) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findOneByNomAndDepartement(string $nom, Departement $departement): ?Arrondissement
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.nom = :nom')
            ->andWhere('a.departement = :departement')
            ->setParameter('nom', $nom)
            ->setParameter('departement', $departement)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function buildArrondissementFromPayload(array $payload): Arrondissement
    {
        $arrondissement = new Arrondissement();
        $now = new \DateTimeImmutable();
        $arrondissement->setCreatedAt($now);
        $arrondissement->setUpdatedAt($now);

        $this->applyPayloadToArrondissement($arrondissement, $payload);

        return $arrondissement;
    }

    public function applyPayloadToArrondissement(Arrondissement $arrondissement, array $payload): void
    {
        if (array_key_exists('nom', $payload)) {
            $arrondissement->setNom((string) $payload['nom']);
        }
        if (array_key_exists('code', $payload)) {
            $arrondissement->setCode(null !== $payload['code'] ? (string) $payload['code'] : null);
        }
        $arrondissement->setUpdatedAt(new \DateTimeImmutable());
    }

    public function softDelete(Arrondissement $arrondissement): void
    {
        $arrondissement->setIsDelete(true);
        $arrondissement->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function restore(Arrondissement $arrondissement): void
    {
        $arrondissement->setIsDelete(false);
        $arrondissement->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    /**
     * @return Arrondissement[]
     */
    public function findPaginatedArrondissements(
        int $page,
        int $limit,
        ?string $isDelete,
        ?int $departementId,
        ?string $search
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('d', 'r')
            ->leftJoin('a.departement', 'd')
            ->leftJoin('d.region', 'r')
            ->orderBy('a.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qb, 'a', $isDelete);

        if (null !== $departementId) {
            $qb->andWhere('a.departement = :departementId')->setParameter('departementId', $departementId);
        }
        if (null !== $search && '' !== $search) {
            $qb->andWhere('a.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countAllArrondissements(?string $isDelete, ?int $departementId, ?string $search): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');
        SoftDeleteQueryFilter::apply($qb, 'a', $isDelete);

        if (null !== $departementId) {
            $qb->andWhere('a.departement = :departementId')->setParameter('departementId', $departementId);
        }
        if (null !== $search && '' !== $search) {
            $qb->andWhere('a.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
