<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function save(Notification $notification): void
    {
        $this->getEntityManager()->persist($notification);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Notification[]
     */
    public function findForUser(User $user, int $page, int $limit, ?bool $isRead = null, ?string $subjectType = null): array
    {
        return $this->findPaginated($page, $limit, $isRead, $subjectType, $user);
    }

    public function countForUser(User $user, ?bool $isRead = null, ?string $subjectType = null): int
    {
        return $this->countAll($isRead, $subjectType, $user);
    }

    /**
     * Listing générique, non scopé sur un destinataire — réservé à un usage administrateur
     * (lister toutes les notifications, ou celles d'un utilisateur choisi via $recipient).
     * findForUser()/countForUser() restent la voie utilisée par l'endpoint self-service, où le
     * destinataire est toujours l'utilisateur connecté.
     *
     * @return Notification[]
     */
    public function findPaginated(int $page, int $limit, ?bool $isRead = null, ?string $subjectType = null, ?User $recipient = null): array
    {
        $qb = $this->baseQuery($isRead, $subjectType, $recipient)
            ->orderBy('n.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countAll(?bool $isRead = null, ?string $subjectType = null, ?User $recipient = null): int
    {
        $qb = $this->baseQuery($isRead, $subjectType, $recipient)
            ->select('COUNT(n.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function markAllAsReadForUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':true')
            ->set('n.readAt', ':now')
            ->where('n.recipient = :user')
            ->andWhere('n.isRead = :false')
            ->setParameter('true', true)
            ->setParameter('false', false)
            ->setParameter('now', new \DateTime())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Dernière notification d'un type donné pour un sujet — sert notamment à retrouver
     * l'émetteur initial (sender) lors d'un accusé de réception.
     */
    public function findOneBySubjectAndType(string $subjectType, int $subjectId, string $type): ?Notification
    {
        return $this->createQueryBuilder('n')
            ->where('n.subjectType = :subjectType')
            ->andWhere('n.subjectId = :subjectId')
            ->andWhere('n.type = :type')
            ->setParameter('subjectType', $subjectType)
            ->setParameter('subjectId', $subjectId)
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Version batch de findOneBySubjectAndType, indexée par subjectId — évite le N+1 dans
     * les listings.
     *
     * @param list<int> $subjectIds
     * @return array<int, Notification>
     */
    public function findLatestForSubjects(string $subjectType, array $subjectIds, string $type): array
    {
        if ([] === $subjectIds) {
            return [];
        }

        $notifications = $this->createQueryBuilder('n')
            ->where('n.subjectType = :subjectType')
            ->andWhere('n.subjectId IN (:subjectIds)')
            ->andWhere('n.type = :type')
            ->setParameter('subjectType', $subjectType)
            ->setParameter('subjectIds', $subjectIds)
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($notifications as $notification) {
            // La dernière notification l'emporte grâce au tri ASC + écrasement.
            $result[$notification->getSubjectId()] = $notification;
        }

        return $result;
    }

    private function baseQuery(?bool $isRead, ?string $subjectType, ?User $recipient): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('n');

        if (null !== $recipient) {
            $qb->andWhere('n.recipient = :recipient')
                ->setParameter('recipient', $recipient);
        }

        if (null !== $isRead) {
            $qb->andWhere('n.isRead = :isRead')
                ->setParameter('isRead', $isRead);
        }

        if (null !== $subjectType) {
            $qb->andWhere('n.subjectType = :subjectType')
                ->setParameter('subjectType', $subjectType);
        }

        return $qb;
    }
}
