<?php

namespace App\Repository\Core;

use App\Entity\Core\Archive;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Archive>
 */
class ArchiveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Archive::class);
    }

    /**
     * Récupérer toutes les archives actives et non supprimées
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.isDelete = :isDelete')
            ->andWhere('a.isActive = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher par ID de courrier
     */
    public function findByCourrier(int $idCourrier): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.idCourrier = :idCourrier')
            ->andWhere('a.isDelete = :isDelete')
            ->setParameter('idCourrier', $idCourrier)
            ->setParameter('isDelete', false)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher par ID de transmission
     */
    public function findByTransmission(int $idTransmission): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.idTransmission = :idTransmission')
            ->andWhere('a.isDelete = :isDelete')
            ->setParameter('idTransmission', $idTransmission)
            ->setParameter('isDelete', false)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher par ID de courrier départ
     */
    public function findByCourrierDepart(int $idCourrierDepart): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.idCourrierDepart = :idCourrierDepart')
            ->andWhere('a.isDelete = :isDelete')
            ->setParameter('idCourrierDepart', $idCourrierDepart)
            ->setParameter('isDelete', false)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher par ID de salle
     */
    public function findBySalle(int $idSalle): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.idSalle = :idSalle')
            ->andWhere('a.isDelete = :isDelete')
            ->setParameter('idSalle', $idSalle)
            ->setParameter('isDelete', false)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher par ID de coffre
     */
    public function findByCoffre(int $idCoffre): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.idCoffre = :idCoffre')
            ->andWhere('a.isDelete = :isDelete')
            ->setParameter('idCoffre', $idCoffre)
            ->setParameter('isDelete', false)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher par salle et coffre
     */
    public function findBySalleAndCoffre(int $idSalle, int $idCoffre): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.idSalle = :idSalle')
            ->andWhere('a.idCoffre = :idCoffre')
            ->andWhere('a.isDelete = :isDelete')
            ->setParameter('idSalle', $idSalle)
            ->setParameter('idCoffre', $idCoffre)
            ->setParameter('isDelete', false)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter le nombre d'archives par coffre
     */
    public function countByCoffre(int $idCoffre): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.idCoffre = :idCoffre')
            ->andWhere('a.isDelete = :isDelete')
            ->setParameter('idCoffre', $idCoffre)
            ->setParameter('isDelete', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compter le nombre d'archives par salle
     */
    public function countBySalle(int $idSalle): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.idSalle = :idSalle')
            ->andWhere('a.isDelete = :isDelete')
            ->setParameter('idSalle', $idSalle)
            ->setParameter('isDelete', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
