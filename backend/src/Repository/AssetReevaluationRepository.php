<?php

namespace App\Repository;

use App\Entity\AssetReevaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetReevaluation>
 */
class AssetReevaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetReevaluation::class);
    }

    public function save(AssetReevaluation $reevaluation): void
    {
        $this->getEntityManager()->persist($reevaluation);
        $this->getEntityManager()->flush();
    }

    public function remove(AssetReevaluation $reevaluation): void
    {
        $this->getEntityManager()->remove($reevaluation);
        $this->getEntityManager()->flush();
    }
}
