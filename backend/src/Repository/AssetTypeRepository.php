<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\AssetType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetType>
 */
class AssetTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetType::class);
    }

    public function save(AssetType $assetType, bool $flush = true): void
    {
        $this->getEntityManager()->persist($assetType);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }


    /**
 * Récupère les types par catégorie
 * @param int $categoryId
 * @param bool $includeInactive
 * @return AssetType[]
 */
 public function findActiveByCategoryId(int $categoryId, bool $includeInactive = false, ?string $search = null): array
{
    $qb = $this->createQueryBuilder('at')
        ->innerJoin('at.category', 'c')
        ->where('c.id = :categoryId')
        ->andWhere('c.isDelete = false')
        ->setParameter('categoryId', $categoryId)
        ->orderBy('at.nom', 'ASC');

    if (!$includeInactive) {
        $qb->andWhere('at.isDelete = false');
    }

     // ✅ Correction : Vérifier que $search existe avant de l'utiliser
        if ($search && !empty(trim($search))) {
            $qb->andWhere('LOWER(at.nom) LIKE LOWER(:search) OR LOWER(at.description) LIKE LOWER(:search)')
                ->setParameter('search', '%' . trim($search) . '%');
        }
    return $qb->getQuery()->getResult();
}

    public function getAssetTypeByIdIncludingDeleted(int $id): ?AssetType
    {
        return $this->find($id);
    }

    /**
     * Résout le type de bien par défaut (is_default = true), quel que soit son statut de
     * suppression — même logique que CategoryRepository::findDefaultCategory.
     */
    public function findDefaultAssetType(): ?AssetType
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isDefault = true')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche par nom exact, y compris les types de biens supprimés — utilisée par
     * DefaultAssetReferencesService::getDefaultAssetType() pour retrouver la référence
     * interne même si son flag is_default n'a pas encore été posé.
     */
    public function findByNom(string $nom): ?AssetType
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.nom = :nom')
            ->setParameter('nom', $nom)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getActiveAssetTypeById(int $id): ?AssetType
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.id = :id')
            ->andWhere('a.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int[] $ids
     * @return AssetType[]
     */
    public function findActiveByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->andWhere('a.isDelete = false')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function existsByNom(string $nom, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.nom = :nom')
            ->setParameter('nom', $nom);

        if (null !== $excludeId) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Construit un AssetType à partir du payload. Ne résout PAS la catégorie :
     * le contrôleur reste responsable de résoudre category_id via CategoryRepository
     * et d'appeler setCategory(), exactement comme UpdateProjectController résout
     * user_ids via UserRepository plutôt que de le faire dans le repository.
     */
    public function buildAssetTypeFromPayload(array $payload): AssetType
    {
        $assetType = new AssetType();
        $now = new \DateTimeImmutable();
        $assetType->setCreatedAt($now);
        $assetType->setUpdatedAt($now);

        $this->applyPayloadToAssetType($assetType, $payload);

        return $assetType;
    }

    public function applyPayloadToAssetType(AssetType $assetType, array $payload): void
    {
        if (array_key_exists('nom', $payload)) {
            $assetType->setNom((string) $payload['nom']);
        }
        if (array_key_exists('description', $payload)) {
            $assetType->setDescription(null !== $payload['description'] ? (string) $payload['description'] : null);
        }
        if (array_key_exists('dureeVie', $payload)) {
            $assetType->setDureeVie(null !== $payload['dureeVie'] && '' !== $payload['dureeVie'] ? (int) $payload['dureeVie'] : null);
        }
        if (array_key_exists('taux', $payload)) {
            $assetType->setTaux(null !== $payload['taux'] && '' !== $payload['taux'] ? (string) $payload['taux'] : null);
        }
        $assetType->setUpdatedAt(new \DateTimeImmutable());
    }

    public function softDelete(AssetType $assetType): void
    {
        $assetType->setIsDelete(true);
        $assetType->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function restore(AssetType $assetType): void
    {
        $assetType->setIsDelete(false);
        $assetType->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    /**
     * @return AssetType[]
     */
    public function findPaginatedAssetTypes(
        int $page,
        int $limit,
        ?string $isDelete,
        ?int $categoryId,
        ?string $search
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qb, 'a', $isDelete);

        if (null !== $categoryId) {
            $qb->andWhere('a.category = :categoryId')->setParameter('categoryId', $categoryId);
        }
        if (null !== $search && '' !== $search) {
            $qb->andWhere('a.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countAllAssetTypes(?string $isDelete, ?int $categoryId, ?string $search): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');
        SoftDeleteQueryFilter::apply($qb, 'a', $isDelete);

        if (null !== $categoryId) {
            $qb->andWhere('a.category = :categoryId')->setParameter('categoryId', $categoryId);
        }
        if (null !== $search && '' !== $search) {
            $qb->andWhere('a.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}