<?php

namespace App\Repository;

use App\Entity\Security;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Security>
 */
class SecurityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Security::class);
    }

    public function save(Security $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Security $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * ✅ Récupère la DERNIÈRE sécurisation active pour un bien
     * @param int $assetId
     * @return Security|null
     */
    public function findLastActiveByAssetId(int $assetId): ?Security
    {
        $result = $this->createQueryBuilder('s')
            ->leftJoin('s.assetSecurities', 'assetSec')
            ->leftJoin('assetSec.asset', 'a')
            // ✅ SUPPRIMER la jointure sur securityMode car c'est maintenant un champ texte
            // leftJoin('s.securityMode', 'sm') // ❌ À SUPPRIMER
            ->leftJoin('s.securityDocuments', 'sd')
            ->leftJoin('sd.pieceJointe', 'pj')
            ->where('a.id = :assetId')
            ->andWhere('s.isDelete = false')
            ->andWhere('assetSec.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $result[0] ?? null;
    }

    /**
     * ✅ Récupère toutes les sécurisations actives pour un bien
     * @param int $assetId
     * @return Security[]
     */
    public function findActiveByAssetId(int $assetId): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.assetSecurities', 'assetSec')
            ->leftJoin('assetSec.asset', 'a')
            // ✅ SUPPRIMER la jointure sur securityMode
            // leftJoin('s.securityMode', 'sm') // ❌ À SUPPRIMER
            ->leftJoin('s.securityDocuments', 'sd')
            ->leftJoin('sd.pieceJointe', 'pj')
            ->where('a.id = :assetId')
            ->andWhere('s.isDelete = false')
            ->andWhere('assetSec.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les sécurisations par IDs de biens.
     * @param list<int> $assetIds
     * @return array<int, Security> indexed by security_id
     */
    public function findByAssetIds(array $assetIds): array
    {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.assetSecurities', 'as')
            ->innerJoin('as.asset', 'a')
            // ✅ SUPPRIMER la jointure sur securityMode car c'est maintenant un champ texte
            // leftJoin('s.securityMode', 'sm') // ❌ À SUPPRIMER
            ->where('a.id IN (:assetIds)')
            ->andWhere('s.isDelete = false')
            ->andWhere('as.isDelete = false')
            ->setParameter('assetIds', $assetIds);

        $securities = $qb->getQuery()->getResult();
        
        $result = [];
        foreach ($securities as $security) {
            $result[$security->getId()] = $security;
        }
        
        return $result;
    }

    /**
     * ✅ Compte le nombre de sécurisations pour un bien
     * @param int $assetId
     * @return int
     */
    public function countActiveByAssetId(int $assetId): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->leftJoin('s.assetSecurities', 'assetSec')
            ->leftJoin('assetSec.asset', 'a')
            // ✅ SUPPRIMER la jointure sur securityMode
            // leftJoin('s.securityMode', 'sm') // ❌ À SUPPRIMER
            ->where('a.id = :assetId')
            ->andWhere('s.isDelete = false')
            ->andWhere('assetSec.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * ✅ Récupère les sécurisations paginées avec filtres (texte)
     * @param int $page
     * @param int $limit
     * @param string|null $securityMode
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param int|null $assetId
     * @param string|null $search
     * @param string $orderBy
     * @param string $orderDir
     * @return Security[]
     */
    public function findPaginated(
        int $page,
        int $limit,
        ?string $securityMode = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $assetId = null,
        ?string $search = null,
        string $orderBy = 'createdAt',
        string $orderDir = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.assetSecurities', 'assetSec')
            ->leftJoin('assetSec.asset', 'a')
            // ✅ SUPPRIMER la jointure sur securityMode car c'est maintenant un champ texte
            ->where('s.isDelete = false')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        // ✅ Filtre par mode de sécurisation (texte)
        if ($securityMode && !empty(trim($securityMode))) {
            $qb->andWhere('LOWER(s.securityMode) LIKE LOWER(:securityMode)')
               ->setParameter('securityMode', '%' . trim($securityMode) . '%');
        }

        if ($dateFrom) {
            $qb->andWhere('s.dateSecurisation >= :dateFrom')
               ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }

        if ($dateTo) {
            $qb->andWhere('s.dateSecurisation <= :dateTo')
               ->setParameter('dateTo', new \DateTimeImmutable($dateTo));
        }

        if ($assetId) {
            $qb->andWhere('a.id = :assetId')
               ->setParameter('assetId', $assetId);
        }

        if ($search && !empty(trim($search))) {
            $qb->andWhere('LOWER(s.securityMode) LIKE LOWER(:search) OR LOWER(a.nom) LIKE LOWER(:search)')
               ->setParameter('search', '%' . trim($search) . '%');
        }

        // Tri
        switch ($orderBy) {
            case 'dateSecurisation':
                $qb->orderBy('s.dateSecurisation', $orderDir);
                break;
            case 'securityMode':
                $qb->orderBy('s.securityMode', $orderDir);
                break;
            case 'id':
                $qb->orderBy('s.id', $orderDir);
                break;
            case 'createdAt':
            default:
                $qb->orderBy('s.createdAt', $orderDir);
                break;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * ✅ Compte le nombre total de sécurisations avec filtres
     * @param string|null $securityMode
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param int|null $assetId
     * @param string|null $search
     * @return int
     */
    public function countWithFilters(
        ?string $securityMode = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $assetId = null,
        ?string $search = null
    ): int {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->leftJoin('s.assetSecurities', 'assetSec')
            ->leftJoin('assetSec.asset', 'a')
            // ✅ SUPPRIMER la jointure sur securityMode car c'est maintenant un champ texte
            ->where('s.isDelete = false');

        if ($securityMode && !empty(trim($securityMode))) {
            $qb->andWhere('LOWER(s.securityMode) LIKE LOWER(:securityMode)')
               ->setParameter('securityMode', '%' . trim($securityMode) . '%');
        }

        if ($dateFrom) {
            $qb->andWhere('s.dateSecurisation >= :dateFrom')
               ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }

        if ($dateTo) {
            $qb->andWhere('s.dateSecurisation <= :dateTo')
               ->setParameter('dateTo', new \DateTimeImmutable($dateTo));
        }

        if ($assetId) {
            $qb->andWhere('a.id = :assetId')
               ->setParameter('assetId', $assetId);
        }

        if ($search && !empty(trim($search))) {
            $qb->andWhere('LOWER(s.securityMode) LIKE LOWER(:search) OR LOWER(a.nom) LIKE LOWER(:search)')
               ->setParameter('search', '%' . trim($search) . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}