<?php

namespace App\Repository\Core;

use App\Entity\Core\BordereauTransmission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BordereauTransmission>
 */
class BordereauTransmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BordereauTransmission::class);
    }

    /**
     * Trouve les bordereaux contenant un courrier spécifique
     *
     * @param int $courrierId
     * @return BordereauTransmission[]
     */
    public function findByCourrierIds(int $courrierId): array
    {
        return $this->createQueryBuilder('b')
            ->where('JSON_CONTAINS(b.courrierIds, :courrierId) = 1')
            ->setParameter('courrierId', json_encode($courrierId))
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un bordereau par son numéro de référence
     */
    public function findOneByNumeroReference(string $numeroReference): ?BordereauTransmission
    {
        return $this->createQueryBuilder('b')
            ->where('b.numeroReference = :numeroReference')
            ->setParameter('numeroReference', $numeroReference)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
