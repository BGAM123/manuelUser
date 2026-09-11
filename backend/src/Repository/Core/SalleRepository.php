<?php

namespace App\Repository\Core;

use App\Entity\Core\Salle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Salle>
 */
class SalleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Salle::class);
    }

    /**
     * Trouve une salle active par son nom
     */
    public function findActiveByNom(string $nom): ?Salle
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.nom = :nom')
            ->andWhere('s.isActive = :isActive')
            ->andWhere('s.isDelete = :isDelete')
            ->setParameter('nom', $nom)
            ->setParameter('isActive', true)
            ->setParameter('isDelete', false)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère toutes les salles actives
     * @return Salle[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isActive = :isActive')
            ->andWhere('s.isDelete = :isDelete')
            ->setParameter('isActive', true)
            ->setParameter('isDelete', false)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
