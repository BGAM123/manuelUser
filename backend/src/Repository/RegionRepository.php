<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\Departement;
use App\Entity\Region;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Region>
 */
class RegionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Region::class);
    }

    public function save(Region $region, bool $flush = true): void
    {
        $this->getEntityManager()->persist($region);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getRegionByIdIncludingDeleted(int $id): ?Region
    {
        return $this->find($id);
    }

    public function getActiveRegionById(int $id): ?Region
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.id = :id')
            ->andWhere('r.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByNom(string $nom, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.nom = :nom')
            ->setParameter('nom', $nom);

        if (null !== $excludeId) {
            $qb->andWhere('r.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findOneByNom(string $nom): ?Region
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.nom = :nom')
            ->setParameter('nom', $nom)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countActiveRegions(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.isDelete = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function buildRegionFromPayload(array $payload): Region
    {
        $region = new Region();
        $now = new \DateTimeImmutable();
        $region->setCreatedAt($now);
        $region->setUpdatedAt($now);

        $this->applyPayloadToRegion($region, $payload);

        return $region;
    }

    public function applyPayloadToRegion(Region $region, array $payload): void
    {
        if (array_key_exists('nom', $payload)) {
            $region->setNom((string) $payload['nom']);
        }
        if (array_key_exists('code', $payload)) {
            $region->setCode(null !== $payload['code'] ? (string) $payload['code'] : null);
        }
        $region->setUpdatedAt(new \DateTimeImmutable());
    }

    public function softDelete(Region $region): void
    {
        $region->setIsDelete(true);
        $region->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function restore(Region $region): void
    {
        $region->setIsDelete(false);
        $region->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function countActiveDepartements(Region $region): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Departement::class, 'd')
            ->andWhere('d.region = :region')
            ->andWhere('d.isDelete = false')
            ->setParameter('region', $region)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getRegionWithDepartementsAndArrondissements(int $id): ?Region
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.departements', 'd')
            ->addSelect('d')
            ->leftJoin('d.arrondissements', 'a')
            ->addSelect('a')
            ->andWhere('r.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Region[]
     */
    public function findPaginatedRegions(int $page, int $limit, ?string $isDelete, ?string $search): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qb, 'r', $isDelete);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('r.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countAllRegions(?string $isDelete, ?string $search): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)');
        SoftDeleteQueryFilter::apply($qb, 'r', $isDelete);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('r.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Region[]
     */
    public function findAllActiveWithHierarchy(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.departements', 'd')
            ->addSelect('d')
            ->leftJoin('d.arrondissements', 'a')
            ->addSelect('a')
            ->andWhere('r.isDelete = false')
            ->orderBy('r.nom', 'ASC')
            ->addOrderBy('d.nom', 'ASC')
            ->addOrderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
