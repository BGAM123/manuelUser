<?php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\EtatBien;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EtatBien>
 */
class EtatBienRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EtatBien::class);
    }

    public function save(EtatBien $etatBien, bool $flush = true): void
    {
        $this->getEntityManager()->persist($etatBien);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getActiveById(int $id): ?EtatBien
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.assetTypes', 'a')
            ->addSelect('a')
            ->andWhere('e.id = :id')
            ->andWhere('e.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

     /**
     * Trouve l'état "Réformé" ou "Reformé" (insensible à la casse)
     */

    public function existsByNom(string $nom, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.nom = :nom')
            ->setParameter('nom', $nom);

        if (null !== $excludeId) {
            $qb->andWhere('e.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

      /**
     * ✅ NOUVEAU : Trouve l'état "Réformé" ou "Reformé" (insensible à la casse)
     */
    public function findReformeEtat(): ?EtatBien
    {
        // D'abord chercher exactement "Réformé"
        $reforme = $this->createQueryBuilder('eb')
            ->where('eb.isDelete = false')
            ->andWhere('LOWER(eb.nom) = LOWER(:nom)')
            ->setParameter('nom', 'réformé')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        if ($reforme) {
            return $reforme;
        }
        
        // Ensuite chercher "Reformé" (sans accent)
        $reforme = $this->createQueryBuilder('eb')
            ->where('eb.isDelete = false')
            ->andWhere('LOWER(eb.nom) = LOWER(:nom)')
            ->setParameter('nom', 'reformé')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        if ($reforme) {
            return $reforme;
        }
        
        // Si pas trouvé, chercher par LIKE en excluant "A Reformer"
        return $this->createQueryBuilder('eb')
            ->where('eb.isDelete = false')
            ->andWhere('LOWER(eb.nom) LIKE :nom')
            ->andWhere('LOWER(eb.nom) NOT LIKE :exclude')
            ->setParameter('nom', '%réform%')
            ->setParameter('exclude', '%a reformer%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    

    /**
     * ✅ NOUVEAU : Trouve un état par son nom (insensible à la casse)
     */
    public function findByName(string $nom): ?EtatBien
    {
        return $this->createQueryBuilder('eb')
            ->where('eb.isDelete = false')
            ->andWhere('LOWER(eb.nom) = LOWER(:nom)')
            ->setParameter('nom', $nom)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function buildFromPayload(array $payload): EtatBien
    {
        $etatBien = new EtatBien();
        $now = new \DateTimeImmutable();
        $etatBien->setCreatedAt($now);
        $etatBien->setUpdatedAt($now);
        $this->applyPayload($etatBien, $payload);

        return $etatBien;
    }

    public function applyPayload(EtatBien $etatBien, array $payload): void
    {
        if (array_key_exists('nom', $payload)) {
            $etatBien->setNom((string) $payload['nom']);
        }
        if (array_key_exists('numeroOrdre', $payload)) {
            $etatBien->setNumeroOrdre($payload['numeroOrdre'] !== null ? (int) $payload['numeroOrdre'] : null);
        }
        if (array_key_exists('description', $payload)) {
            $etatBien->setDescription($payload['description'] !== null ? (string) $payload['description'] : null);
        }
        $etatBien->setUpdatedAt(new \DateTimeImmutable());
    }

    public function softDelete(EtatBien $etatBien): void
    {
        $etatBien->setIsDelete(true);
        $etatBien->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function restore(EtatBien $etatBien): void
    {
        $etatBien->setIsDelete(false);
        $etatBien->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    /**
     * @return EtatBien[]
     */
    public function findPaginated(int $page, int $limit, ?string $isDelete, ?string $search, ?int $assetTypeId = null): array
    {
        // D'abord récupérer les IDs distincts avec pagination
        $qbIds = $this->createQueryBuilder('e')
            ->select('e.id')
            ->addOrderBy('e.numeroOrdre', 'ASC')
            ->addOrderBy('e.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qbIds, 'e', $isDelete);

        if (null !== $search && '' !== $search) {
            $qbIds->andWhere('e.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        if (null !== $assetTypeId) {
            $qbIds->innerJoin('e.assetTypes', 'a')
                ->andWhere('a.id = :assetTypeId')
                ->andWhere('a.isDelete = false')
                ->setParameter('assetTypeId', $assetTypeId);
        }

        $ids = array_column($qbIds->getQuery()->getResult(), 'id');

        if (empty($ids)) {
            return [];
        }

        // Puis récupérer les entités complètes avec leurs relations
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.assetTypes', 'a')
            ->leftJoin('a.category', 'c')
            ->addSelect('a')
            ->addSelect('c')
            ->andWhere('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('e.numeroOrdre', 'ASC')
            ->addOrderBy('e.nom', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function countAll(?string $isDelete, ?string $search, ?int $assetTypeId = null): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.id)')
            ->leftJoin('e.assetTypes', 'a');
        SoftDeleteQueryFilter::apply($qb, 'e', $isDelete);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('e.nom LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        if (null !== $assetTypeId) {
            $qb->andWhere('a.id = :assetTypeId')
                ->andWhere('a.isDelete = false')
                ->setParameter('assetTypeId', $assetTypeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return EtatBien[]
     */
    public function findActiveByAssetTypeId(int $assetTypeId): array
    {
        return $this->createQueryBuilder('e')
            ->innerJoin('e.assetTypes', 'a')
            ->andWhere('a.id = :assetTypeId')
            ->andWhere('a.isDelete = false')
            ->andWhere('e.isDelete = false')
            ->setParameter('assetTypeId', $assetTypeId)
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $ids
     * @return EtatBien[]
     */
    public function findActiveByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->andWhere('e.id IN (:ids)')
            ->andWhere('e.isDelete = false')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function existsByNumeroOrdre(?int $numeroOrdre, ?int $excludeId = null): bool
    {
        if ($numeroOrdre === null) {
            return false;
        }

        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.numeroOrdre = :numeroOrdre')
            ->andWhere('e.isDelete = false') 
            ->setParameter('numeroOrdre', $numeroOrdre);

        if (null !== $excludeId) {
            $qb->andWhere('e.id != :excludeId')
            ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
