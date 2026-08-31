<?php

namespace App\Repository;

use App\Entity\AssetDepreciation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetDepreciation>
 */
class AssetDepreciationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetDepreciation::class);
    }

    public function save(AssetDepreciation $depreciation): void
    {
        $this->getEntityManager()->persist($depreciation);
        $this->getEntityManager()->flush();
    }

    public function remove(AssetDepreciation $depreciation): void
    {
        $this->getEntityManager()->remove($depreciation);
        $this->getEntityManager()->flush();
    }
}
