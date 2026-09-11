<?php

namespace App\Repository\Cour;

use App\Entity\Cour\CourrierInterne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CourrierInterne>
 */
class CourrierInterneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourrierInterne::class);
    }
}
