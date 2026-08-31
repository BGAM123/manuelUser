<?php

// src/Repository/RoleRepository.php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\Permission;
use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * @return array<int, Role>
     */
    public function findPaginatedRoles(int $page, int $limit, ?string $q = null, ?bool $isActive = null, ?string $isDelete = 'false'): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')
            ->addSelect('p');
        SoftDeleteQueryFilter::apply($qb, 'r', $isDelete);

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('r.nom LIKE :q OR r.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if (null !== $isActive) {
            $qb->andWhere('r.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        /** @var array<int, Role> $result */
        $result = $qb
            ->orderBy('r.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countRoles(?string $q = null, ?bool $isActive = null, ?string $isDelete = 'false'): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)');
        SoftDeleteQueryFilter::apply($qb, 'r', $isDelete);

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('r.nom LIKE :q OR r.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if (null !== $isActive) {
            $qb->andWhere('r.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getRoleById(int $id): ?Role
    {
        return $this->find($id);
    }

    /**
     * Récupère un rôle par son nom (ex: 'Utilisateur' pour le rôle par défaut).
     */
    public function getRoleByNom(string $nom): ?Role
    {
        return $this->findOneBy(['nom' => $nom]);
    }

    public function existsByNom(string $nom, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('LOWER(r.nom) = LOWER(:nom)')
            ->setParameter('nom', $nom);

        if (null !== $excludeId) {
            $qb->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return ((int) $qb->getQuery()->getSingleScalarResult()) > 0;
    }

    public function buildRoleFromPayload(array $data): Role
    {
        $role = new Role();

        if (isset($data['nom'])) {
            $role->setNom((string) $data['nom']);
        }
        if (array_key_exists('description', $data)) {
            $role->setDescription($data['description'] === null ? null : (string) $data['description']);
        }
        if (isset($data['is_active'])) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $role->setIsActive($isActive ?? true);
        }

        $now = new \DateTimeImmutable();
        $role->setCreatedAt($now);
        $role->setUpdatedAt($now);

        // Traiter les permissions si présentes
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $this->syncRolePermissions($role, $data['permissions']);
        }

        return $role;
    }

    public function applyPayloadToRole(Role $role, array $data): Role
    {
        if (isset($data['nom'])) {
            $role->setNom((string) $data['nom']);
        }
        if (array_key_exists('description', $data)) {
            $role->setDescription($data['description'] === null ? null : (string) $data['description']);
        }
        if (isset($data['is_active'])) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (null !== $isActive) {
                $role->setIsActive($isActive);
            }
        }

        $role->setUpdatedAt(new \DateTimeImmutable());

        // Traiter les permissions si présentes
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $this->syncRolePermissions($role, $data['permissions']);
        }

        return $role;
    }

    public function save(Role $role): void
    {
        $em = $this->getEntityManager();
        $em->persist($role);
        $em->flush();
    }

    /**
     * Suppression logique : conserve le rôle en base (des utilisateurs peuvent encore
     * y faire référence) mais le désactive et le masque des listes actives.
     */
    public function softDelete(Role $role): void
    {
        $role->setIsDelete(true);
        $role->setIsActive(false);
        $this->save($role);
    }

    /**
     * Suppression physique et irréversible.
     */
    public function remove(Role $role): void
    {
        $em = $this->getEntityManager();
        $em->remove($role);
        $em->flush();
    }

    /**
     * Affecte une permission à un rôle. Retourne false si l'association existait déjà
     * (permet au contrôleur de renvoyer un 409 Conflict).
     */
    public function assignPermission(Role $role, Permission $permission): bool
    {
        if ($role->hasPermission($permission)) {
            return false;
        }

        $role->addPermission($permission);
        $this->save($role);

        return true;
    }

    /**
     * Retire une permission d'un rôle. Retourne false si l'association n'existait pas.
     */
    public function unassignPermission(Role $role, Permission $permission): bool
    {
        if (!$role->hasPermission($permission)) {
            return false;
        }

        $role->removePermission($permission);
        $this->save($role);

        return true;
    }

    /**
     * Synchronise les permissions d'un rôle avec une liste d'IDs.
     * Valide que chaque permission existe et lance une exception si ce n'est pas le cas.
     *
     * @param Role $role
     * @param array<int> $permissionIds IDs des permissions à synchroniser
     * @throws \InvalidArgumentException Si une permission n'existe pas
     */
    public function syncRolePermissions(Role $role, array $permissionIds): void
    {
        $em = $this->getEntityManager();
        $permissionRepository = $em->getRepository(Permission::class);

        // Valider que toutes les permissions existent
        $permissions = [];
        foreach ($permissionIds as $permissionId) {
            $permission = $permissionRepository->find((int) $permissionId);
            if (!$permission) {
                throw new \InvalidArgumentException(\sprintf('La permission avec l\'ID %d n\'existe pas.', $permissionId));
            }
            $permissions[] = $permission;
        }

        // Nettoyer les permissions actuelles
        $role->getPermissions()->clear();

        // Ajouter les nouvelles permissions
        foreach ($permissions as $permission) {
            $role->addPermission($permission);
        }
    }
}
