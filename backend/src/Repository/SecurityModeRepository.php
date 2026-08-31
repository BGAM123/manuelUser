<?php

namespace App\Repository;

use App\Entity\SecurityMode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecurityMode>
 */
class SecurityModeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityMode::class);
    }

    public function save(SecurityMode $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SecurityMode $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve un mode de sécurisation par son nom (non supprimé).
     */
    public function findOneByNom(string $nom): ?SecurityMode
    {
        return $this->createQueryBuilder('sm')
            ->where('sm.nom = :nom')
            ->andWhere('sm.isDelete = false')
            ->setParameter('nom', $nom)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un nom existe déjà (pour un mode non supprimé).
     */
    public function existsByNom(string $nom, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('sm')
            ->select('COUNT(sm.id)')
            ->where('sm.nom = :nom')
            ->andWhere('sm.isDelete = false')
            ->setParameter('nom', $nom);

        if ($excludeId !== null) {
            $qb->andWhere('sm.id != :id')
               ->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
