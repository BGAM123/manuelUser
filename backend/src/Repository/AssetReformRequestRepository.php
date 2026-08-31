<?php

namespace App\Repository;

use App\Entity\AssetReformRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetReformRequest>
 */
class AssetReformRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetReformRequest::class);
    }

    /**
     * Trouve une demande de réforme en attente pour un bien
     */
    public function findPendingByAssetId(int $assetId): ?AssetReformRequest
    {
        return $this->createQueryBuilder('r')
            ->where('r.asset = :assetId')
            ->andWhere('r.statut = :statut')
            ->andWhere('r.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->setParameter('statut', AssetReformRequest::STATUT_EN_ATTENTE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les demandes de réforme en attente pour plusieurs biens
     * @return array<int, AssetReformRequest> indexed by asset_id
     */
    public function findPendingByAssetIds(array $assetIds): array
    {
        if (empty($assetIds)) {
            return [];
        }

        $requests = $this->createQueryBuilder('r')
            ->where('r.asset IN (:assetIds)')
            ->andWhere('r.statut = :statut')
            ->andWhere('r.isDelete = false')
            ->setParameter('assetIds', $assetIds)
            ->setParameter('statut', AssetReformRequest::STATUT_EN_ATTENTE)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($requests as $request) {
            $indexed[$request->getAsset()->getId()] = $request;
        }

        return $indexed;
    }

    /**
     * Compte le nombre de demandes en attente pour un bien
     */
    public function countPendingByAssetId(int $assetId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.asset = :assetId')
            ->andWhere('r.statut = :statut')
            ->andWhere('r.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->setParameter('statut', AssetReformRequest::STATUT_EN_ATTENTE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si un bien a déjà une demande validée
     */
    public function hasValidatedRequest(int $assetId): bool
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.asset = :assetId')
            ->andWhere('r.statut = :statut')
            ->andWhere('r.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->setParameter('statut', AssetReformRequest::STATUT_VALIDEE)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function save(AssetReformRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AssetReformRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
