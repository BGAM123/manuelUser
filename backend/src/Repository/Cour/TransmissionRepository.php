<?php

namespace App\Repository\Cour;

use App\Entity\Cour\Transmission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transmission>
 */
class TransmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transmission::class);
    }

    /**
     * Vérifie si un utilisateur (émetteur) a une transmission avec accusé de réception pour un courrier donné
     * 
     * @param int $courrierId L'ID du courrier
     * @param int $emetteurId L'ID de l'émetteur
     * @return bool True si une transmission reçue existe, False sinon
     */
    public function hasReceivedTransmissionForCourrier(int $courrierId, int $emetteurId): bool
    {
        $result = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.idCourrier = :courrierId')
            ->andWhere('t.idEmetteur = :emetteurId')
            ->andWhere('t.accuseReception = :received')
            ->andWhere('t.isDelete = :notDeleted')
            ->setParameter('courrierId', $courrierId)
            ->setParameter('emetteurId', $emetteurId)
            ->setParameter('received', true)
            ->setParameter('notDeleted', false)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }


	/**
     * Récupère la dernière transmission pour un courrier donné (la plus récente selon createdAt)
     * 
     * @param int $courrierId L'ID du courrier
     * @return Transmission|null La dernière transmission ou null si aucune transmission n'existe
     */
    public function findLastTransmissionByCourrier(int $courrierId): ?Transmission
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.idServiceDestinataire', 'sd')
            ->addSelect('sd')
            ->andWhere('t.idCourrier = :courrierId')
            ->andWhere('t.isDelete = :notDeleted')
            ->setParameter('courrierId', $courrierId)
            ->setParameter('notDeleted', false)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

//    /**
//     * @return Transmission[] Returns an array of Transmission objects
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

//    public function findOneBySomeField($value): ?Transmission
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
