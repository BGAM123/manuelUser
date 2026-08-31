<?php

namespace App\Repository;

use App\Entity\Service;
use App\Entity\TypeOrganigramme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    // public function findPaginatedServices(int $page, int $limit, ?bool $isActive = null, ?int $parentId = null): array
    public function findPaginatedServices(int $page, int $limit, ?bool $isActive = null, array|int|null $parentIds = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.typeOrganigrammes', 't')
            ->leftJoin('s.region', 'r')
            ->leftJoin('s.departement', 'd')
            ->leftJoin('s.arrondissement', 'a')
            ->addSelect('t', 'r', 'd', 'a');

        if (null !== $parentIds) {
            if (is_array($parentIds) && !empty($parentIds)) {
                $qb->andWhere('IDENTITY(s.parent) IN (:parentIds)')
                ->setParameter('parentIds', $parentIds);
            } elseif (is_int($parentIds) || is_numeric($parentIds)) {
                $qb->andWhere('IDENTITY(s.parent) = :parentId')
                ->setParameter('parentId', (int) $parentIds);
            }
        }

        // Ajouter après le bloc isActive
        if (null !== $search && $search !== '') {
            $qb->andWhere('(s.nom LIKE :search OR s.sigle LIKE :search OR s.code LIKE :search)')
               ->setParameter('search', '%' . $search . '%');
        }

        if (null !== $isActive) {
            $qb->andWhere('s.is_active = :isActive')
               ->setParameter('isActive', $isActive);
        }

        return $qb
            ->orderBy('s.ordre', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère TOUS les services (racines ET descendants) pour construire une hiérarchie.
     * Applique le filtre is_active à tous les services, pas seulement aux racines.
     * Utilisé pour l'affichage hiérarchisé sans paramètre parent_id.
     *
     * @return Service[]
     */
    public function findRootServicesWithDescendants(?bool $isActive = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.typeOrganigrammes', 't')
            ->leftJoin('s.region', 'r')
            ->leftJoin('s.departement', 'd')
            ->leftJoin('s.arrondissement', 'a')
            ->addSelect('t', 'r', 'd', 'a')
            ->orderBy('s.ordre', 'ASC');

        if (null !== $isActive) {
            $qb->andWhere('s.is_active = :isActive')
               ->setParameter('isActive', $isActive);
        }

        return $qb->getQuery()->getResult();
    }

    // public function countAllServices(?bool $isActive = null, ?int $parentId = null): int
    public function countAllServices(?bool $isActive = null, array|int|null $parentIds = null, ?string $search = null): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)');

        if (null !== $isActive) {
            $qb->andWhere('s.is_active = :isActive')
               ->setParameter('isActive', $isActive);
        }

        if (null !== $parentIds) {
            if (is_array($parentIds) && !empty($parentIds)) {
                $qb->andWhere('IDENTITY(s.parent) IN (:parentIds)')
                ->setParameter('parentIds', $parentIds);
            } elseif (is_int($parentIds) || is_numeric($parentIds)) {
                $qb->andWhere('IDENTITY(s.parent) = :parentId')
                ->setParameter('parentId', (int) $parentIds);
            }
        }

        if (null !== $search && $search !== '') {
            $qb->andWhere('(s.nom LIKE :search OR s.sigle LIKE :search OR s.code LIKE :search)')
               ->setParameter('search', '%' . $search . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Compte les services racines (sans parent) pour la pagination hiérarchisée.
     */
    public function countRootServices(?bool $isActive = null): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.parent IS NULL');

        if (null !== $isActive) {
            $qb->andWhere('s.is_active = :isActive')
               ->setParameter('isActive', $isActive);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getServiceById(int $id): ?Service
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.typeOrganigrammes', 't')
            ->leftJoin('s.region', 'r')
            ->leftJoin('s.departement', 'd')
            ->leftJoin('s.arrondissement', 'a')
            ->addSelect('t', 'r', 'd', 'a')
            ->andWhere('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne les enfants directs d'un service
     *
     * @return Service[]
     */
    public function getDirectChildren(int $serviceId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('IDENTITY(s.parent) = :parentId')
            ->setParameter('parentId', $serviceId)
            ->orderBy('s.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function buildServiceFromPayload(array $data): Service
    {
        $service = new Service();
        if (isset($data['nom'])) {
            $service->setNom((string) $data['nom']);
        }
        if (isset($data['sigle'])) {
            $service->setSigle((string) $data['sigle']);
        }
        if (isset($data['type_service'])) {
            $service->setTypeService((string) $data['type_service']);
        }
        if (isset($data['ordre'])) {
            // store as string/number wrapper if needed; keep as-is
            $service->setOrdre($data['ordre']);
        }

         if (isset($data['code'])) {
            $service->setCode((string) $data['code']);
        }
        if (isset($data['is_active'])) {
            $service->setIsActive(filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true);
        } else {
            $service->setIsActive(true);
        }
        $service->setCreatedAt(new \DateTimeImmutable());
        $service->setUpdatedAt(new \DateTimeImmutable());

        return $service;
    }

    public function applyPayloadToService(Service $service, array $data): Service
    {
        if (isset($data['nom'])) {
            $service->setNom((string) $data['nom']);
        }
        if (array_key_exists('sigle', $data)) {
            $service->setSigle($data['sigle'] === null ? null : (string) $data['sigle']);
        }
        if (array_key_exists('type_service', $data)) {
            $service->setTypeService($data['type_service'] === null ? null : (string) $data['type_service']);
        }
        if (isset($data['ordre'])) {
            $service->setOrdre($data['ordre']);
        }
        if (array_key_exists('parent_id', $data)) {
            // parent linkage should be handled in controller (fetch parent entity) - here accept null or integer
        }
        if (array_key_exists('code', $data)) {
            $service->setCode($data['code'] === null ? null : (string) $data['code']);
        }
        if (isset($data['is_active'])) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (null !== $isActive) {
                $service->setIsActive($isActive);
            }
        }


        $service->setUpdatedAt(new \DateTimeImmutable());

        return $service;
    }

    public function save(Service $service): void
    {
        $em = $this->getEntityManager();
        $em->persist($service);
        $em->flush();
    }

    public function remove(Service $service): void
    {
        $em = $this->getEntityManager();
        $em->remove($service);
        $em->flush();
    }

    /**
     * Vérifie si un numéro d'ordre est déjà utilisé (pour un parent donné)
     *
     * @param int $ordre
     * @param int|null $parentId
     * @param int|null $excludeServiceId (pour ignorer le service en cours de modification)
     * @return bool
     */
    public function isOrdreAlreadyUsed(int $ordre, ?int $parentId = null, ?int $excludeServiceId = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.ordre = :ordre')
            ->setParameter('ordre', $ordre);

        if (null !== $parentId) {
            $qb->andWhere('IDENTITY(s.parent) = :parentId')
               ->setParameter('parentId', $parentId);
        } else {
            $qb->andWhere('s.parent IS NULL');
        }

        if (null !== $excludeServiceId) {
            $qb->andWhere('s.id != :excludeId')
               ->setParameter('excludeId', $excludeServiceId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Récupère les services par type d'organigramme
     *
     * @param int $typeOrganigrammeId
     * @param int $page
     * @param int $limit
     * @param bool|null $isActive
     * @return Service[]
     */
    public function findByTypeOrganigramme(int $typeOrganigrammeId, int $page, int $limit, ?bool $isActive = null, $parentIds = null, ?string $search = null): array
{
    $qb = $this->createQueryBuilder('s')
        ->innerJoin('s.typeOrganigrammes', 't')
        ->leftJoin('s.typeOrganigrammes', 't2')
        ->addSelect('t2')
        ->andWhere('t.id = :typeId')
        ->andWhere('t.isDelete = false')
        ->setParameter('typeId', $typeOrganigrammeId);

    if (null !== $isActive) {
        $qb->andWhere('s.is_active = :isActive')
           ->setParameter('isActive', $isActive);
    }

    if (null !== $parentIds) {
        if (is_array($parentIds) && !empty($parentIds)) {
            $qb->andWhere('IDENTITY(s.parent) IN (:parentIds)')
               ->setParameter('parentIds', $parentIds);
        } elseif (is_int($parentIds) || is_numeric($parentIds)) {
            $qb->andWhere('IDENTITY(s.parent) = :parentId')
               ->setParameter('parentId', (int) $parentIds);
        }
    }

    if (null !== $search && $search !== '') {
        $qb->andWhere('(s.nom LIKE :search OR s.sigle LIKE :search OR s.code LIKE :search)')
        ->setParameter('search', '%' . $search . '%');
    }

    return $qb
        ->orderBy('s.ordre', 'ASC')
        ->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

    /**
     * Compte les services par type d'organigramme
     *
     * @param int $typeOrganigrammeId
     * @param bool|null $isActive
     * @return int
     */
    public function countByTypeOrganigramme(int $typeOrganigrammeId, ?bool $isActive = null, $parentIds = null, ?string $search = null): int
{
    $qb = $this->createQueryBuilder('s')
        ->select('COUNT(s.id)')
        ->innerJoin('s.typeOrganigrammes', 't')
        ->andWhere('t.id = :typeId')
        ->andWhere('t.isDelete = false')
        ->setParameter('typeId', $typeOrganigrammeId);

    if (null !== $isActive) {
        $qb->andWhere('s.is_active = :isActive')
           ->setParameter('isActive', $isActive);
    }

    if (null !== $parentIds) {
        if (is_array($parentIds) && !empty($parentIds)) {
            $qb->andWhere('IDENTITY(s.parent) IN (:parentIds)')
               ->setParameter('parentIds', $parentIds);
        } elseif (is_int($parentIds) || is_numeric($parentIds)) {
            $qb->andWhere('IDENTITY(s.parent) = :parentId')
               ->setParameter('parentId', (int) $parentIds);
        }
    }

    if (null !== $search && $search !== '') {
        $qb->andWhere('(s.nom LIKE :search OR s.sigle LIKE :search OR s.code LIKE :search)')
        ->setParameter('search', '%' . $search . '%');
    }

    return (int) $qb->getQuery()->getSingleScalarResult();
}

    /**
     * Récupère tous les services (racines et descendants) par type d'organigramme pour construire une hiérarchie
     *
     * @param int $typeOrganigrammeId
     * @param bool|null $isActive
     * @return Service[]
     */
    public function findAllByTypeOrganigramme(int $typeOrganigrammeId, ?bool $isActive = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.typeOrganigrammes', 't')
            ->leftJoin('s.typeOrganigrammes', 't2')
            ->addSelect('t2')
            ->andWhere('t.id = :typeId')
            ->andWhere('t.isDelete = false')
            ->setParameter('typeId', $typeOrganigrammeId);

        if (null !== $isActive) {
            $qb->andWhere('s.is_active = :isActive')
               ->setParameter('isActive', $isActive);
        }

        return $qb
            ->orderBy('s.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }


    //    /**
    //     * @return Service[] Returns an array of Service objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Service
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
