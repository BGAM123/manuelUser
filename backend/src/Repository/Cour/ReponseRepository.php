<?php

namespace App\Repository\Cour;

use App\Entity\Cour\Reponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reponse>
 */
class ReponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reponse::class);
    }

    /**
     * @return int[]
     */
    public function findIdsByTransmissionId(int $transmissionId, ?bool $isDelete = null): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT id FROM cour_reponse WHERE JSON_CONTAINS(id_transmission, :transmissionId)';
        $params = [
            'transmissionId' => json_encode($transmissionId),
        ];

        if ($isDelete !== null) {
            $sql .= ' AND is_delete = :isDelete';
            $params['isDelete'] = (int) $isDelete;
        }

        $rows = $conn->fetchAllAssociative($sql, $params);

        return array_values(array_map('intval', array_column($rows, 'id')));
    }

//    /**
//     * @return Reponse[] Returns an array of Reponse objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Reponse
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
