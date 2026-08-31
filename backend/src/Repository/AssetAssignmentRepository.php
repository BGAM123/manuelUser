<?php

namespace App\Repository;

use App\Entity\AssetAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetAssignment>
 */
class AssetAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetAssignment::class);
    }

    public function save(AssetAssignment $assignment): void
    {
        $this->getEntityManager()->persist($assignment);
        $this->getEntityManager()->flush();
    }

    public function remove(AssetAssignment $assignment): void
    {
        $this->getEntityManager()->remove($assignment);
        $this->getEntityManager()->flush();
    }

    /**
     * @return AssetAssignment[]
     */
    public function findByAsset(int $assetId): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->leftJoin('a.service', 's')
            ->addSelect('s')
            ->where('a.asset = :assetId')
            ->andWhere('a.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->orderBy('a.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AssetAssignment|null
     */
    public function findActiveByAsset(int $assetId): ?AssetAssignment
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->leftJoin('a.service', 's')
            ->addSelect('s')
            ->where('a.asset = :assetId')
            ->andWhere('a.isDelete = false')
            ->andWhere('a.dateFin IS NULL')
            ->setParameter('assetId', $assetId)
            ->orderBy('a.dateDebut', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return AssetAssignment[]
     */
    public function findActiveByUser(int $userId): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.asset', 'asset')
            ->addSelect('asset')
            ->leftJoin('a.service', 's')
            ->addSelect('s')
            ->where('a.user = :userId')
            ->andWhere('a.isDelete = false')
            ->andWhere('a.dateFin IS NULL')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AssetAssignment|null
     */
    public function findLastActiveByAssetId(int $assetId): ?AssetAssignment
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->leftJoin('a.service', 's')
            ->addSelect('s')
            ->where('a.asset = :assetId')
            ->andWhere('a.isDelete = false')
            ->setParameter('assetId', $assetId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * ✅ Listing paginé des affectations avec les mêmes filtres que le listing des biens
     * @return AssetAssignment[]
     */
    public function findPaginated(
        int $page,
        int $limit,
        bool $isDelete = false,
        ?string $search = null,
        ?int $categoryId = null,
        ?int $assetTypeId = null,
        ?array $serviceIds = null,
        ?array $projectIds = null,
        ?int $exercice = null,
        ?string $statut = null,
        ?int $userId = null,
        ?string $securise = null,
        ?string $received = null,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.asset', 'asset')
            ->addSelect('asset')
            ->leftJoin('asset.categories', 'c')
            ->addSelect('c')
            ->leftJoin('asset.assetTypes', 't')
            ->addSelect('t')
            ->leftJoin('asset.etatBiens', 'e')
            ->addSelect('e')
            ->leftJoin('asset.services', 's')
            ->addSelect('s')
            ->leftJoin('asset.projects', 'p')
            ->addSelect('p')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->leftJoin('a.service', 'assService')
            ->addSelect('assService')
            ->leftJoin('asset.createdBy', 'creator')
            ->addSelect('creator')
            ->leftJoin('asset.assetSecurities', 'assetSec')
            ->leftJoin('assetSec.security', 'sec')
            ->andWhere('a.isDelete = :isDelete')
            ->andWhere('asset.isDelete = false')
            ->setParameter('isDelete', $isDelete)
            ->orderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('asset.nom LIKE :search OR asset.reference LIKE :search OR asset.numeroSerie LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        if (null !== $categoryId) {
            $qb->andWhere('c.id = :categoryId')->setParameter('categoryId', $categoryId);
        }
        if (null !== $assetTypeId) {
            $qb->andWhere('t.id = :assetTypeId')->setParameter('assetTypeId', $assetTypeId);
        }
        // ✅ Filtre par service : trouver les utilisateurs du service, puis leurs biens affectés
        if (null !== $serviceIds && !empty($serviceIds)) {
            $qb->innerJoin('u.service', 'userService')
               ->andWhere('userService.id IN (:serviceIds)')
               ->setParameter('serviceIds', $serviceIds);
        }
        if (null !== $projectIds && !empty($projectIds)) {
            $qb->andWhere('p.id IN (:projectIds)')->setParameter('projectIds', $projectIds);
        }
        // ✅ Filtre par exercice
        if (null !== $exercice) {
            $qb->andWhere('asset.exercice = :exercice')->setParameter('exercice', $exercice);
        }
        // if (null !== $statut) {
        //     $qb->andWhere('asset.statut = :statut')->setParameter('statut', $statut);
        // } else {
        //     // Par défaut, exclure les biens SORTIE
        //     $qb->andWhere('asset.statut != :statutDefault')->setParameter('statutDefault', 'SORTIE');
        // }

        if (null !== $statut) {
            $qb->andWhere('asset.statut = :statut')->setParameter('statut', $statut);
        } else {
            // Par défaut, exclure les biens SORTIE
            $qb->andWhere('asset.statut != :statutDefault')->setParameter('statutDefault', 'SORTIE');
        }

        // ✅ Filtre par utilisateur connecté : uniquement les affectations où l'utilisateur est le détenteur actuel
        if (null !== $userId) {
            $qb->andWhere('a.detenteur = true')
                ->andWhere('(u.id = :userId OR assService.id IN (SELECT IDENTITY(us.service) FROM App\Entity\User us WHERE us.id = :userId))')
                ->setParameter('userId', $userId);
        }

        // ✅ Filtre par sécurisation
        if (null !== $securise && '' !== trim($securise)) {
            $isSecured = filter_var($securise, FILTER_VALIDATE_BOOLEAN);
            if ($isSecured) {
                // Biens sécurisés : ont au moins une sécurisation active
                $qb->andWhere('sec.id IS NOT NULL')
                    ->andWhere('sec.isDelete = false')
                    ->andWhere('sec.dateSecurisation IS NOT NULL');
            } else {
                // Biens non sécurisés : n'ont aucune sécurisation active
                $qb->andWhere('sec.id IS NULL');
            }
        }

        // Filtre par accusé de réception (champ received)
        if (null !== $received && '' !== trim($received)) {
            $isReceived = filter_var($received, FILTER_VALIDATE_BOOLEAN);
            $qb->andWhere('a.received = :received')
                ->setParameter('received', $isReceived);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * ✅ Compte le nombre total d'affectations avec les mêmes filtres
     */
    public function countAll(
        bool $isDelete = false,
        ?string $search = null,
        ?int $categoryId = null,
        ?int $assetTypeId = null,
        ?array $serviceIds = null,
        ?array $projectIds = null,
        ?int $exercice = null,
        ?string $statut = null,
        ?int $userId = null,
        ?string $securise = null,
        ?string $received = null,
    ): int {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id)')
            ->leftJoin('a.asset', 'asset')
            ->leftJoin('a.user', 'u')
            ->leftJoin('a.service', 'assService')
            ->leftJoin('asset.createdBy', 'creator')
            ->andWhere('a.isDelete = :isDelete')
            ->andWhere('asset.isDelete = false')
            ->setParameter('isDelete', $isDelete);

        // Recherche
        if (null !== $search && '' !== $search) {
            $qb->andWhere('asset.nom LIKE :search OR asset.reference LIKE :search OR asset.numeroSerie LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if (null !== $categoryId) {
            $qb->innerJoin('asset.categories', 'c')
               ->andWhere('c.id = :categoryId')
               ->setParameter('categoryId', $categoryId);
        }

        if (null !== $assetTypeId) {
            $qb->innerJoin('asset.assetTypes', 't')
               ->andWhere('t.id = :assetTypeId')
               ->setParameter('assetTypeId', $assetTypeId);
        }

        if (null !== $serviceIds && !empty($serviceIds)) {
            $qb->innerJoin('u.service', 'userService')
               ->andWhere('userService.id IN (:serviceIds)')
               ->setParameter('serviceIds', $serviceIds);
        }

        if (null !== $projectIds && !empty($projectIds)) {
            $qb->innerJoin('asset.projects', 'p')
               ->andWhere('p.id IN (:projectIds)')
               ->setParameter('projectIds', $projectIds);
        }

        if (null !== $exercice) {
            $qb->andWhere('asset.exercice = :exercice')
               ->setParameter('exercice', $exercice);
        }

        if (null !== $statut) {
            $qb->andWhere('asset.statut = :statut')
               ->setParameter('statut', $statut);
        } else {
            // Par défaut, exclure les biens SORTIE
            $qb->andWhere('asset.statut != :statutDefault')
               ->setParameter('statutDefault', 'SORTIE');
        }

        // ✅ Filtre par utilisateur connecté : uniquement les affectations où l'utilisateur est le détenteur actuel
        if (null !== $userId) {
            $qb->andWhere('a.detenteur = true')
               ->andWhere('(u.id = :userId OR assService.id IN (SELECT IDENTITY(us.service) FROM App\Entity\User us WHERE us.id = :userId))')
               ->setParameter('userId', $userId);
        }

        // ✅ Filtre par sécurisation
        if (null !== $securise && '' !== trim($securise)) {
            $qb->leftJoin('asset.assetSecurities', 'assetSec')
                ->leftJoin('assetSec.security', 'sec');
            $isSecured = filter_var($securise, FILTER_VALIDATE_BOOLEAN);
            if ($isSecured) {
                // Biens sécurisés : ont au moins une sécurisation active
                $qb->andWhere('sec.id IS NOT NULL')
                    ->andWhere('sec.isDelete = false')
                    ->andWhere('assetSec.isDelete = false');
            } else {
                // Biens non sécurisés : n'ont aucune sécurisation active
                $qb->andWhere('sec.id IS NULL OR (sec.isDelete = true OR assetSec.isDelete = true)');
            }
        }

        // ✅ Filtre par accusé de réception (champ received)
        if (null !== $received && '' !== trim($received)) {
            $isReceived = filter_var($received, FILTER_VALIDATE_BOOLEAN);
            $qb->andWhere('a.received = :received')
                ->setParameter('received', $isReceived);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
