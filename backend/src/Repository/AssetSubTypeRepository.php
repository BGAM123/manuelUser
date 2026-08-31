<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\AssetSubType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetSubType>
 */
class AssetSubTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetSubType::class);
    }

    public function save(AssetSubType $assetSubType, bool $flush = true): void
    {
        $this->getEntityManager()->persist($assetSubType);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
 * Récupère les sous-types par type de bien
 * @param int $assetTypeId
 * @param bool $includeInactive
 * @return AssetSubType[]
 */
public function findActiveByAssetTypeId(int $assetTypeId, bool $includeInactive = false, ?string $search = null): array
{
    $qb = $this->createQueryBuilder('ast')
        ->innerJoin('ast.assetType', 'at')
        ->where('at.id = :assetTypeId')
        ->andWhere('at.isDelete = false')
        ->setParameter('assetTypeId', $assetTypeId)
        ->orderBy('ast.nom', 'ASC');

    if (!$includeInactive) {
        $qb->andWhere('ast.isDelete = false');
    }

    // ✅ Ajout de la recherche sur le nom et la description
        if ($search && !empty(trim($search))) {
            $qb->andWhere('LOWER(ast.nom) LIKE LOWER(:search) OR LOWER(ast.description) LIKE LOWER(:search)')
                ->setParameter('search', '%' . trim($search) . '%');
        }

    return $qb->getQuery()->getResult();
}

    public function getAssetSubTypeByIdIncludingDeleted(int $id): ?AssetSubType
    {
        return $this->find($id);
    }

    public function getActiveAssetSubTypeById(int $id): ?AssetSubType
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.id = :id')
            ->andWhere('s.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByNom(string $nom, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.nom = :nom')
            ->setParameter('nom', $nom);

        if (null !== $excludeId) {
            $qb->andWhere('s.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Construit un AssetSubType à partir du payload. Ne résout PAS l'AssetType parent :
     * le contrôleur reste responsable de le résoudre puis d'appeler setAssetType(),
     * exactement comme pour AssetType/Category.
     */
    public function buildAssetSubTypeFromPayload(array $payload): AssetSubType
    {
        $assetSubType = new AssetSubType();
        $now = new \DateTimeImmutable();
        $assetSubType->setCreatedAt($now);
        $assetSubType->setUpdatedAt($now);

        $this->applyPayloadToAssetSubType($assetSubType, $payload);

        return $assetSubType;
    }

    public function applyPayloadToAssetSubType(AssetSubType $assetSubType, array $payload): void
    {
        if (array_key_exists('nom', $payload)) {
            $assetSubType->setNom((string) $payload['nom']);
        }
        if (array_key_exists('description', $payload)) {
            $assetSubType->setDescription(null !== $payload['description'] ? (string) $payload['description'] : null);
        }
        $assetSubType->setUpdatedAt(new \DateTimeImmutable());
    }

    public function softDelete(AssetSubType $assetSubType): void
    {
        $assetSubType->setIsDelete(true);
        $assetSubType->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function restore(AssetSubType $assetSubType): void
    {
        $assetSubType->setIsDelete(false);
        $assetSubType->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    /**
     * @return AssetSubType[]
     */
    public function findPaginatedAssetSubTypes(
        int $page,
        int $limit,
        ?string $isDelete,
        ?int $assetTypeId,
        ?string $search
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qb, 's', $isDelete);

        if (null !== $assetTypeId) {
            $qb->andWhere('s.assetType = :assetTypeId')->setParameter('assetTypeId', $assetTypeId);
        }
        if (null !== $search && '' !== $search) {
            $qb->andWhere('s.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countAllAssetSubTypes(?string $isDelete, ?int $assetTypeId, ?string $search): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)');
        SoftDeleteQueryFilter::apply($qb, 's', $isDelete);

        if (null !== $assetTypeId) {
            $qb->andWhere('s.assetType = :assetTypeId')->setParameter('assetTypeId', $assetTypeId);
        }
        if (null !== $search && '' !== $search) {
            $qb->andWhere('s.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Garde-fou avant suppression logique d'un AssetType : combien d'AssetSubType actifs
     * (non supprimés) référencent encore ce type de bien ? Même logique que
     * CategoryRepository::countActiveAssetTypes.
     */
    public function countActiveAssetSubTypes(\App\Entity\AssetType $assetType): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(\App\Entity\AssetSubType::class, 's')
            ->andWhere('s.assetType = :assetType')
            ->andWhere('s.isDelete = false')
            ->setParameter('assetType', $assetType)
            ->getQuery()
            ->getSingleScalarResult();
    }
}