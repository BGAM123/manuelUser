<?php

namespace App\Repository;

use App\Entity\AcknowledgementOfReceipt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AcknowledgementOfReceipt>
 */
class AcknowledgementOfReceiptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AcknowledgementOfReceipt::class);
    }

    public function save(AcknowledgementOfReceipt $acknowledgement): void
    {
        $this->getEntityManager()->persist($acknowledgement);
        $this->getEntityManager()->flush();
    }

    public function findOneBySubject(string $subjectType, int $subjectId): ?AcknowledgementOfReceipt
    {
        return $this->findOneBy(['subjectType' => $subjectType, 'subjectId' => $subjectId]);
    }

    /**
     * Version batch de findOneBySubject, indexée par subjectId — évite le N+1 dans les listings.
     *
     * @param list<int> $subjectIds
     * @return array<int, AcknowledgementOfReceipt>
     */
    public function findForSubjects(string $subjectType, array $subjectIds): array
    {
        if ([] === $subjectIds) {
            return [];
        }

        $acknowledgements = $this->createQueryBuilder('a')
            ->where('a.subjectType = :subjectType')
            ->andWhere('a.subjectId IN (:subjectIds)')
            ->setParameter('subjectType', $subjectType)
            ->setParameter('subjectIds', $subjectIds)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($acknowledgements as $acknowledgement) {
            $result[$acknowledgement->getSubjectId()] = $acknowledgement;
        }

        return $result;
    }
}
