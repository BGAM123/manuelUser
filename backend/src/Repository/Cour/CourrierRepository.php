<?php

namespace App\Repository\Cour;

use App\Entity\Core\Service;
use App\Entity\Cour\Courrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Courrier>
 */
class CourrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Courrier::class);
    }

    
/**
 * Compte le nombre de courriers pour un mois spécifique d'une année
 * Le compteur est réinitialisé chaque mois
 */
public function countCourriersForYearMonth(int $annee, int $mois): int
{
    $qb = $this->createQueryBuilder('c');
    
    // Créer les dates de début et fin du mois
    $debutMois = new \DateTimeImmutable("$annee-$mois-01 00:00:00");
    $finMois = (clone $debutMois)->modify('last day of this month')->setTime(23, 59, 59);
    
    $qb->select('COUNT(c.id)')
        ->where('c.createdAt BETWEEN :debutMois AND :finMois')
        ->setParameter('debutMois', $debutMois)
        ->setParameter('finMois', $finMois);
    
    return (int) $qb->getQuery()->getSingleScalarResult();
}


//    /**
//     * @return Courrier[] Returns an array of Courrier objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Courrier
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
