<?php

// src/Repository/PermissionRepository.php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    /**
     * Liste paginée des permissions non supprimées, avec recherche texte simple
     * (correspond à la barre "Rechercher..." des captures : un seul champ `q`
     * qui filtre sur le nom ET la description).
     *
     * @return array<int, Permission>
     */
    public function findPaginatedPermissions(int $page, int $limit, ?string $q = null, ?bool $isActive = null, ?string $isDelete = 'false'): array
    {
        $qb = $this->createQueryBuilder('p');
        SoftDeleteQueryFilter::apply($qb, 'p', $isDelete);

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('p.nom LIKE :q OR p.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if (null !== $isActive) {
            $qb->andWhere('p.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        /** @var array<int, Permission> $result */
        $result = $qb
            ->orderBy('p.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countPermissions(?string $q = null, ?bool $isActive = null, ?string $isDelete = 'false'): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');
        SoftDeleteQueryFilter::apply($qb, 'p', $isDelete);

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('p.nom LIKE :q OR p.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if (null !== $isActive) {
            $qb->andWhere('p.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getPermissionById(int $id): ?Permission
    {
        return $this->find($id);
    }

    /**
     * Vérifie l'unicité du nom, utilisé pour retourner un 409 Conflict explicite
     * (en plus de la contrainte UniqueEntity côté validateur).
     */
    public function existsByNom(string $nom, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('LOWER(p.nom) = LOWER(:nom)')
            ->setParameter('nom', $nom);

        if (null !== $excludeId) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return ((int) $qb->getQuery()->getSingleScalarResult()) > 0;
    }

    public function buildPermissionFromPayload(array $data): Permission
    {
        $permission = new Permission();

        if (isset($data['nom'])) {
            $permission->setNom((string) $data['nom']);
        }
        if (array_key_exists('description', $data)) {
            $permission->setDescription($data['description'] === null ? null : (string) $data['description']);
        }
        if (isset($data['is_active'])) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $permission->setIsActive($isActive ?? true);
        }

        $now = new \DateTimeImmutable();
        $permission->setCreatedAt($now);
        $permission->setUpdatedAt($now);

        return $permission;
    }

    public function applyPayloadToPermission(Permission $permission, array $data): Permission
    {
        if (isset($data['nom'])) {
            $permission->setNom((string) $data['nom']);
        }
        if (array_key_exists('description', $data)) {
            $permission->setDescription($data['description'] === null ? null : (string) $data['description']);
        }
        if (isset($data['is_active'])) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (null !== $isActive) {
                $permission->setIsActive($isActive);
            }
        }

        $permission->setUpdatedAt(new \DateTimeImmutable());

        return $permission;
    }

    public function save(Permission $permission): void
    {
        $em = $this->getEntityManager();
        $em->persist($permission);
        $em->flush();
    }

    /**
     * Suppression logique : la permission reste en base (elle peut toujours être
     * référencée par des rôles existants) mais disparaît des listes actives.
     */
    public function softDelete(Permission $permission): void
    {
        $permission->setIsDelete(true);
        $permission->setIsActive(false);
        $this->save($permission);
    }

    /**
     * Suppression physique et irréversible.
     */
    public function remove(Permission $permission): void
    {
        $em = $this->getEntityManager();
        $em->remove($permission);
        $em->flush();
    }
}
