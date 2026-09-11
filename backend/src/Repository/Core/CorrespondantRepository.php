<?php

namespace App\Repository\Core;

use App\Entity\Core\Correspondant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CorrespondantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Correspondant::class);
    }

    /**
     * Recherche des correspondants avec filtres et recherche textuelle
     */
    public function findByFilters(array $criteria = [], ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('c');
        
        // Appliquer les critères de filtrage
        foreach ($criteria as $field => $value) {
            if ($value !== null) {
                $qb->andWhere("c.$field = :$field")
                   ->setParameter($field, $value);
            }
        }
        
        // Appliquer la recherche textuelle
        if ($search && !empty(trim($search))) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('c.nom', ':search'),
                    $qb->expr()->like('c.prenom', ':search'),
                    $qb->expr()->like('c.email', ':search'),
                    $qb->expr()->like('c.telephone', ':search'),
                    $qb->expr()->like('c.adresse', ':search')
                )
            )
            ->setParameter('search', '%' . $search . '%');
        }
        
        // Ordre par défaut
        $qb->orderBy('c.createdAt', 'DESC');
        
        return $qb->getQuery()->getResult();
    }
}
