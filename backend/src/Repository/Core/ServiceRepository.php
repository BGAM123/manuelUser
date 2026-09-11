<?php

namespace App\Repository\Core;

use App\Entity\Core\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Vérifie si un service parent a déjà un enfant avec le même nom
     * 
     * @param Service|null $parent Le service parent
     * @param string $nom Le nom du service enfant à vérifier
     * @param int|null $excludeId ID du service à exclure de la recherche (pour les modifications)
     * @return bool True si un enfant avec ce nom existe déjà, False sinon
     */
    public function hasChildWithSameName(?Service $parent, string $nom, ?int $excludeId = null): bool
    {
        // Si pas de parent, on ne vérifie pas (services racines peuvent avoir le même nom)
        if ($parent === null) {
            return false;
        }

        $qb = $this->createQueryBuilder('s')
            ->where('s.idServiceParent = :parent')
            ->andWhere('s.nom = :nom')
            ->andWhere('s.isDelete = :isDelete')
            ->setParameter('parent', $parent)
            ->setParameter('nom', trim($nom))
            ->setParameter('isDelete', false);

        // Exclure le service actuel en cas de modification
        if ($excludeId !== null) {
            $qb->andWhere('s.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

//    /**
//     * @return Service[] Returns an array of Service objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Service
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
