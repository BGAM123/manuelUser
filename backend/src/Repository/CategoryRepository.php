<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\AssetType;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function save(Category $category, bool $flush = true): void
    {
        $this->getEntityManager()->persist($category);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }



    /**
     * Récupère toutes les catégories actives (non supprimées)
     * @return Category[]
     */
    public function findActiveCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isDelete = false')
            // ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    

    /**
     * Résout une catégorie par son id, quel que soit son statut de suppression.
     * Utilisée par le ParamConverter implicite ({id} dans les routes) : les contrôleurs
     * de soft-delete/restore doivent pouvoir retrouver une catégorie même supprimée.
     */
    public function getCategoryByIdIncludingDeleted(int $id): ?Category
    {
        return $this->find($id);
    }
    public function findActiveByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.id IN (:ids)')
            ->andWhere('c.isDelete = false')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Résout une catégorie ACTIVE uniquement (is_delete = false).
     * À utiliser partout où on rattache un AssetType à une catégorie
     * (création/mise à jour) : on ne rattache jamais un enfant à un parent supprimé.
     */
    public function getActiveCategoryById(int $id): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getActiveCategoryByNom(string $nom): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.nom = :nom')
            ->andWhere('c.isDelete = false')
            ->setParameter('nom', $nom)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDefaultCategory(): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isDefault = true')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * ✅ Récupère les catégories avec leurs seuils
     */
    public function findThresholds(?array $categoryIds = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('
                c.id,
                c.nom,
                c.seuil
            ')
            ->where('c.isDelete = false')
            ->andWhere('c.isDefault = false')
            ->orderBy('c.nom', 'ASC');

        if ($categoryIds !== null && !empty($categoryIds)) {
            $qb->andWhere('c.id IN (:categoryIds)')
               ->setParameter('categoryIds', $categoryIds);
        }

        $results = $qb->getQuery()->getResult();

        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'nom' => $row['nom'],
                'seuil' => $row['seuil'] !== null ? (int) $row['seuil'] : null,
            ];
        }, $results);
    }

    /**
     * NB : vérifie l'unicité sur TOUTES les lignes (y compris supprimées), pour rester
     * cohérent avec la contrainte unique en base (cf. remarque en tête de réponse sur
     * le soft-delete + unicité). $excludeId permet d'ignorer l'entité en cours de
     * modification lors d'un rename.
     */
    public function findByNom(string $nom): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.nom = :nom')
            ->setParameter('nom', $nom)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * ✅ Récupère les catégories avec leurs seuils (paginé)
     */
    public function findThresholdsPaginated(
        int $page,
        int $limit,
        ?array $categoryIds = null
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->select('
                c.id,
                c.nom,
                c.seuil
            ')
            ->where('c.isDelete = false')
            ->andWhere('c.isDefault = false')
            ->orderBy('c.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($categoryIds !== null && !empty($categoryIds)) {
            $qb->andWhere('c.id IN (:categoryIds)')
               ->setParameter('categoryIds', $categoryIds);
        }

        $results = $qb->getQuery()->getResult();

        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'nom' => $row['nom'],
                'seuil' => $row['seuil'] !== null ? (int) $row['seuil'] : null,
            ];
        }, $results);
    }

    /**
     * ✅ Compte le nombre total de catégories pour la pagination
     */
    public function countThresholds(?array $categoryIds = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isDelete = false')
            ->andWhere('c.isDefault = false');

        if ($categoryIds !== null && !empty($categoryIds)) {
            $qb->andWhere('c.id IN (:categoryIds)')
               ->setParameter('categoryIds', $categoryIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findActiveByNom(string $nom): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.nom = :nom')
            ->andWhere('c.isDelete = false')
            ->setParameter('nom', $nom)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByNom(string $nom, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.nom = :nom')
            ->andWhere('c.isDelete = false')  // ✅ Ajouter ce filtre
            ->setParameter('nom', $nom);

        if (null !== $excludeId) {
            $qb->andWhere('c.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
    

    public function buildCategoryFromPayload(array $payload): Category
    {
        $category = new Category();
        $now = new \DateTimeImmutable();
        $category->setCreatedAt($now);
        $category->setUpdatedAt($now);

        $this->applyPayloadToCategory($category, $payload);

        return $category;
    }

    public function applyPayloadToCategory(Category $category, array $payload): void
    {
        if (array_key_exists('nom', $payload)) {
            $category->setNom((string) $payload['nom']);
        }
        if (array_key_exists('description', $payload)) {
            $category->setDescription(null !== $payload['description'] ? (string) $payload['description'] : null);
        }
        if (array_key_exists('seuil', $payload)) {
            $category->setSeuil(null !== $payload['seuil'] ? (int) $payload['seuil'] : null);
        }
        if (array_key_exists('ordre', $payload)) {
            $category->setOrdre(null !== $payload['ordre'] ? (int) $payload['ordre'] : null);
        }
        if (array_key_exists('consommable', $payload)) {
            $category->setConsommable((bool) $payload['consommable']);
        }

        $category->setUpdatedAt(new \DateTimeImmutable());
    }

    public function softDelete(Category $category): void
    {
        $category->setIsDelete(true);
        $category->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function restore(Category $category): void
    {
        $category->setIsDelete(false);
        $category->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    /**
     * Garde-fou avant suppression logique : combien d'AssetType actifs (non supprimés)
     * référencent encore cette catégorie ?
     */
    public function countActiveAssetTypes(Category $category): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(AssetType::class, 'a')
            ->andWhere('a.category = :category')
            ->andWhere('a.isDelete = false')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Category[]
     */
    public function findPaginatedCategories(int $page, int $limit, ?string $isDelete, ?string $search, ?string $consommable = 'all'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.isDefault = false')
            ->orderBy('c.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qb, 'c', $isDelete);

        // ✅ Recherche sur nom ET description (insensible à la casse)
        if ($search && !empty(trim($search))) {
            $qb->andWhere('LOWER(c.nom) LIKE LOWER(:search) OR LOWER(c.description) LIKE LOWER(:search)')
                ->setParameter('search', '%' . trim($search) . '%');
        }

        // ✅ Filtre sur le champ consommable
    if ($consommable === 'true') {
        $qb->andWhere('c.consommable = true');
    } elseif ($consommable === 'false') {
        $qb->andWhere('c.consommable = false OR c.consommable IS NULL');
    }
        
        

        return $qb->getQuery()->getResult();
    }

     public function countAllCategories(?string $isDelete, ?string $search, ?string $consommable = 'all'): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isDefault = false');
        SoftDeleteQueryFilter::apply($qb, 'c', $isDelete);

        // ✅ Recherche sur nom ET description (une seule condition)
        if ($search && !empty(trim($search))) {
            $qb->andWhere('LOWER(c.nom) LIKE LOWER(:search) OR LOWER(c.description) LIKE LOWER(:search)')
                ->setParameter('search', '%' . trim($search) . '%');
        }

        // ✅ Filtre sur le champ consommable
    if ($consommable === 'true') {
        $qb->andWhere('c.consommable = true');
    } elseif ($consommable === 'false') {
        $qb->andWhere('c.consommable = false OR c.consommable IS NULL');
    }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

}
