<?php

namespace App\Repository\Core;

use App\Entity\Core\ClasseCourrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClasseCourrier>
 *
 * @method ClasseCourrier|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClasseCourrier|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClasseCourrier[]    findAll()
 * @method ClasseCourrier[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClasseCourrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClasseCourrier::class);
    }

    /**
     * Trouve toutes les classes de courrier ordonnées par nom
     *
     * @return ClasseCourrier[]
     */
    public function findAllOrderedByNom(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des classes de courrier par nom (recherche partielle)
     *
     * @param string $nom
     * @return ClasseCourrier[]
     */
    public function searchByNom(string $nom): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.nom LIKE :nom')
            ->setParameter('nom', '%' . $nom . '%')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
