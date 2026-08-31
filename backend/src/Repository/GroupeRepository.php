<?php

// src/Repository/GroupeRepository.php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\Groupe;
use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Groupe>
 */
class GroupeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Groupe::class);
    }

    /**
     * @return array<int, Groupe>
     */
    public function findPaginatedGroupes(int $page, int $limit, ?string $q = null, ?bool $isActive = null, ?string $isDelete = 'false'): array
    {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.permissions', 'p')
            ->addSelect('p');
        SoftDeleteQueryFilter::apply($qb, 'g', $isDelete);

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('g.nom LIKE :q OR g.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if (null !== $isActive) {
            $qb->andWhere('g.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        /** @var array<int, Groupe> $result */
        $result = $qb
            ->orderBy('g.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countGroupes(?string $q = null, ?bool $isActive = null, ?string $isDelete = 'false'): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)');
        SoftDeleteQueryFilter::apply($qb, 'g', $isDelete);

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('g.nom LIKE :q OR g.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if (null !== $isActive) {
            $qb->andWhere('g.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getGroupeById(int $id): ?Groupe
    {
        return $this->find($id);
    }

    public function existsByNom(string $nom, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('LOWER(g.nom) = LOWER(:nom)')
            ->setParameter('nom', $nom);

        if (null !== $excludeId) {
            $qb->andWhere('g.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return ((int) $qb->getQuery()->getSingleScalarResult()) > 0;
    }

    public function buildGroupeFromPayload(array $data): Groupe
    {
        $groupe = new Groupe();

        if (isset($data['nom'])) {
            $groupe->setNom((string) $data['nom']);
        }
        if (array_key_exists('description', $data)) {
            $groupe->setDescription($data['description'] === null ? null : (string) $data['description']);
        }
        if (isset($data['is_active'])) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $groupe->setIsActive($isActive ?? true);
        }

        $now = new \DateTimeImmutable();
        $groupe->setCreatedAt($now);
        $groupe->setUpdatedAt($now);

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $this->syncGroupePermissions($groupe, $data['permissions']);
        }

        return $groupe;
    }

    public function applyPayloadToGroupe(Groupe $groupe, array $data): Groupe
    {
        if (isset($data['nom'])) {
            $groupe->setNom((string) $data['nom']);
        }
        if (array_key_exists('description', $data)) {
            $groupe->setDescription($data['description'] === null ? null : (string) $data['description']);
        }
        if (isset($data['is_active'])) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (null !== $isActive) {
                $groupe->setIsActive($isActive);
            }
        }

        $groupe->setUpdatedAt(new \DateTimeImmutable());

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $this->syncGroupePermissions($groupe, $data['permissions']);
        }

        return $groupe;
    }

    public function save(Groupe $groupe): void
    {
        $em = $this->getEntityManager();
        $em->persist($groupe);
        $em->flush();
    }

    /**
     * Suppression logique uniquement : jamais de blocage même si des utilisateurs actifs
     * sont rattachés au groupe. Une fois isDelete/isActive passés, User::getEffectivePermissions()
     * exclut automatiquement ce groupe du calcul pour tous ses membres - conformément à la
     * règle métier validée : "les privilèges du groupe sont juste retirés, pas de blocage".
     */
    public function softDelete(Groupe $groupe): void
    {
        $groupe->setIsDelete(true);
        $groupe->setIsActive(false);
        $this->save($groupe);
    }

    public function assignPermission(Groupe $groupe, Permission $permission): bool
    {
        if ($groupe->hasPermission($permission)) {
            return false;
        }

        $groupe->addPermission($permission);
        $this->save($groupe);

        return true;
    }

    public function unassignPermission(Groupe $groupe, Permission $permission): bool
    {
        if (!$groupe->hasPermission($permission)) {
            return false;
        }

        $groupe->removePermission($permission);
        $this->save($groupe);

        return true;
    }

    /**
     * @param array<int> $permissionIds
     * @throws \InvalidArgumentException Si une permission n'existe pas
     */
    public function syncGroupePermissions(Groupe $groupe, array $permissionIds): void
    {
        $em = $this->getEntityManager();
        $permissionRepository = $em->getRepository(Permission::class);

        $permissions = [];
        foreach ($permissionIds as $permissionId) {
            $permission = $permissionRepository->find((int) $permissionId);
            if (!$permission) {
                throw new \InvalidArgumentException(\sprintf('La permission avec l\'ID %d n\'existe pas.', $permissionId));
            }
            $permissions[] = $permission;
        }

        $groupe->getPermissions()->clear();

        foreach ($permissions as $permission) {
            $groupe->addPermission($permission);
        }
    }
}