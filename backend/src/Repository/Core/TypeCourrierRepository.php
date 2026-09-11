<?php

namespace App\Repository\Core;

use App\Entity\Core\TypeCourrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeCourrier>
 */
class TypeCourrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeCourrier::class);
    }

    /**
     * Trouve un TypeCourrier avec ses relations chargées
     */
    public function findOneWithRelations(int $id): ?TypeCourrier
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.categories', 'c')
            ->leftJoin('t.idTypeParent', 'p')
            ->leftJoin('p.categories', 'pc')
            ->addSelect('c', 'p', 'pc')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

//    /**
//     * @return TypeCourrier[] Returns an array of TypeCourrier objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TypeCourrier
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
