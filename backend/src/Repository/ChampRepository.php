<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\Champ;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Champ>
 */
class ChampRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Champ::class);
    }

    public function save(Champ $champ, bool $flush = true): void
    {
        $this->getEntityManager()->persist($champ);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getActiveById(int $id): ?Champ
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }


    /**
     * Récupère les champs par bien
     * @param int $assetId
     * @return Champ[]
     */
    public function findByAssetId(int $assetId): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.assets', 'a')
            ->where('a.id = :assetId')
            ->andWhere('c.isDelete = false')
            ->andWhere('a.isDelete = false')
            ->setParameter('assetId', $assetId)
            // ->orderBy('c.ordre', 'ASC') // ✅ Tri par ordre
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existsByNom(string $nom, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.nom = :nom')
            ->setParameter('nom', $nom);

        if (null !== $excludeId) {
            $qb->andWhere('c.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    
    /**
     * Récupère le prochain numéro d'ordre disponible
     */
    public function getNextOrdre(): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('MAX(c.ordre)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result + 1 : 0;
    }

    public function buildFromPayload(array $payload): Champ
    {
        $champ = new Champ();
        $now = new \DateTimeImmutable();
        $champ->setCreatedAt($now);
        $champ->setUpdatedAt($now);
        // ✅ Définir automatiquement le prochain numéro d'ordre
        // $champ->setOrdre($this->getNextOrdre());
        $this->applyPayload($champ, $payload);
        return $champ;
    }

    public function applyPayload(Champ $champ, array $payload): void
    {
        if (array_key_exists('nom', $payload)) {
            $champ->setNom((string) $payload['nom']);
        }
        // ❌ SUPPRIMEZ typeChamp et valeur
        // if (array_key_exists('typeChamp', $payload)) {
        //     $champ->setTypeChamp((string) $payload['typeChamp']);
        // }
        // if (array_key_exists('valeur', $payload)) {
        //     $champ->setValeur(null !== $payload['valeur'] && '' !== $payload['valeur'] ? (string) $payload['valeur'] : null);
        // }

        // ✅ Ajout du type
        if (array_key_exists('type', $payload)) {
            $champ->setType((string) $payload['type']);
        }
        
        // ✅ Ajout du sous-type
        if (array_key_exists('subtype', $payload)) {
            $champ->setSubtype(null !== $payload['subtype'] && '' !== $payload['subtype'] ? (string) $payload['subtype'] : null);
        }

        // ✅ Mise à jour de l'ordre si fourni
        // if (array_key_exists('ordre', $payload) && is_int($payload['ordre'])) {
        //     $champ->setOrdre($payload['ordre']);
        // }
        
        $champ->setUpdatedAt(new \DateTimeImmutable());
    }

    public function softDelete(Champ $champ): void
    {
        $champ->setIsDelete(true);
        $champ->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function restore(Champ $champ): void
    {
        $champ->setIsDelete(false);
        $champ->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    /**
     * Suppression physique et irréversible.
     */
    public function remove(Champ $champ): void
    {
        $em = $this->getEntityManager();
        $em->remove($champ);
        $em->flush();
    }

    /**
     * @return Champ[]
     */
    public function findPaginated(int $page, int $limit, ?string $isDelete, ?string $search, ?array $categoryIds = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.categories', 'cat')
            ->addSelect('cat')
            // ->orderBy('c.ordre', 'ASC')
            ->orderBy('c.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qb, 'c', $isDelete);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('c.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        if (null !== $categoryIds && !empty($categoryIds)) {
            $qb->andWhere('cat.id IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(?string $isDelete, ?string $search, ?array $categoryIds = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)')
            ->leftJoin('c.categories', 'cat');
        SoftDeleteQueryFilter::apply($qb, 'c', $isDelete);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('c.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        if (null !== $categoryIds && !empty($categoryIds)) {
            $qb->andWhere('cat.id IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Champ[]
     */
    public function findActiveByCategoryId(int $categoryId): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.categories', 'cat')
            ->andWhere('cat.id = :categoryId')
            // ->orderBy('c.ordre', 'ASC')
            ->orderBy('c.nom', 'ASC')
            ->andWhere('c.isDelete = false')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $ids
     * @return Champ[]
     */
    public function findActiveByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.id IN (:ids)')
            ->andWhere('c.isDelete = false')
            ->setParameter('ids', $ids)
            // ->orderBy('c.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
