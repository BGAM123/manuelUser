<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\Arrondissement;
use App\Entity\Departement;
use App\Entity\Region;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Departement>
 */
class DepartementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Departement::class);
    }

    public function save(Departement $departement, bool $flush = true): void
    {
        $this->getEntityManager()->persist($departement);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function getDepartementByIdIncludingDeleted(int $id): ?Departement
    {
        return $this->find($id);
    }

    public function getActiveDepartementById(int $id): ?Departement
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.id = :id')
            ->andWhere('d.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByNom(string $nom, Region $region, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.nom = :nom')
            ->andWhere('d.region = :region')
            ->setParameter('nom', $nom)
            ->setParameter('region', $region);

        if (null !== $excludeId) {
            $qb->andWhere('d.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findOneByNomAndRegion(string $nom, Region $region): ?Departement
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.nom = :nom')
            ->andWhere('d.region = :region')
            ->setParameter('nom', $nom)
            ->setParameter('region', $region)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function buildDepartementFromPayload(array $payload): Departement
    {
        $departement = new Departement();
        $now = new \DateTimeImmutable();
        $departement->setCreatedAt($now);
        $departement->setUpdatedAt($now);

        $this->applyPayloadToDepartement($departement, $payload);

        return $departement;
    }

    public function applyPayloadToDepartement(Departement $departement, array $payload): void
    {
        if (array_key_exists('nom', $payload)) {
            $departement->setNom((string) $payload['nom']);
        }
        if (array_key_exists('code', $payload)) {
            $departement->setCode(null !== $payload['code'] ? (string) $payload['code'] : null);
        }
        $departement->setUpdatedAt(new \DateTimeImmutable());
    }

    public function softDelete(Departement $departement): void
    {
        $departement->setIsDelete(true);
        $departement->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function restore(Departement $departement): void
    {
        $departement->setIsDelete(false);
        $departement->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function countActiveArrondissements(Departement $departement): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Arrondissement::class, 'a')
            ->andWhere('a.departement = :departement')
            ->andWhere('a.isDelete = false')
            ->setParameter('departement', $departement)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getDepartementWithArrondissements(int $id): ?Departement
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.arrondissements', 'a')
            ->addSelect('a')
            ->leftJoin('d.region', 'r')
            ->addSelect('r')
            ->andWhere('d.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Departement[]
     */
    public function findPaginatedDepartements(
        int $page,
        int $limit,
        ?string $isDelete,
        ?int $regionId,
        ?string $search
    ): array {
        $qb = $this->createQueryBuilder('d')
            ->addSelect('r')
            ->leftJoin('d.region', 'r')
            ->orderBy('d.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qb, 'd', $isDelete);

        if (null !== $regionId) {
            $qb->andWhere('d.region = :regionId')->setParameter('regionId', $regionId);
        }
        if (null !== $search && '' !== $search) {
            $qb->andWhere('d.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countAllDepartements(?string $isDelete, ?int $regionId, ?string $search): int
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)');
        SoftDeleteQueryFilter::apply($qb, 'd', $isDelete);

        if (null !== $regionId) {
            $qb->andWhere('d.region = :regionId')->setParameter('regionId', $regionId);
        }
        if (null !== $search && '' !== $search) {
            $qb->andWhere('d.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
