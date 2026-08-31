<?php
// src/Repository/ExitTypeRepository.php

namespace App\Repository;  // ⚠️ C'est Repository, pas Service

use App\Entity\ExitType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExitType>
 */
class ExitTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExitType::class);
    }

    public function save(ExitType $exitType): void
    {
        $this->getEntityManager()->persist($exitType);
        $this->getEntityManager()->flush();
    }

    public function remove(ExitType $exitType): void
    {
        $this->getEntityManager()->remove($exitType);
        $this->getEntityManager()->flush();
    }

    public function findActiveById(int $id): ?ExitType
    {
        return $this->createQueryBuilder('et')
            ->where('et.id = :id')
            ->andWhere('et.isDelete = false')
            ->andWhere('et.isActive = true')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('et')
            ->where('et.isDelete = false')
            ->andWhere('et.isActive = true')
            ->orderBy('et.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCode(string $code): ?ExitType
    {
        return $this->createQueryBuilder('et')
            ->where('et.code = :code')
            ->andWhere('et.isDelete = false')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche des types de sortie par nom ou code
     */
    public function searchByTerm(string $searchTerm): array
    {
        return $this->createQueryBuilder('et')
            ->where('et.isDelete = false')
            ->andWhere('et.isActive = true')
            ->andWhere('et.nom LIKE :search OR et.code LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('et.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

     /**
     * ✅ NOUVEAU : Trouve le type de sortie "Réforme" (insensible à la casse)
     */
    public function findReformeExitType(): ?ExitType
    {
        return $this->createQueryBuilder('et')
            ->where('et.isDelete = false')
            ->andWhere('et.isActive = true')
            ->andWhere('LOWER(et.nom) LIKE :nom OR LOWER(et.code) LIKE :code')
            ->setParameter('nom', '%réform%')
            ->setParameter('code', '%réform%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * ✅ NOUVEAU : Trouve un type de sortie par son nom ou code (insensible à la casse)
     */
    public function findByNameOrCode(string $search): ?ExitType
    {
        return $this->createQueryBuilder('et')
            ->where('et.isDelete = false')
            ->andWhere('et.isActive = true')
            ->andWhere('LOWER(et.nom) = LOWER(:search) OR LOWER(et.code) = LOWER(:search)')
            ->setParameter('search', $search)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    

    /**
     * Récupère la liste paginée des types de sortie avec recherche et filtres
     */
    public function findPaginated(
        int $page, 
        int $limit, 
        bool $includeInactive = false, 
        ?string $search = null,
        string $orderBy = 'nom',
        string $orderDir = 'ASC'
    ): array {
        $qb = $this->createQueryBuilder('et')
            ->where('et.isDelete = false');

        if (!$includeInactive) {
            $qb->andWhere('et.isActive = true');
        }

        if ($search && !empty(trim($search))) {
            $qb->andWhere('et.nom LIKE :search OR et.code LIKE :search')
                ->setParameter('search', '%' . trim($search) . '%');
        }

        $qb->orderBy('et.' . $orderBy, $orderDir);

        $totalQb = clone $qb;
        $totalCount = (int) $totalQb->select('COUNT(et.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        return [
            'items' => $items,
            'total' => $totalCount
        ];
    }
}