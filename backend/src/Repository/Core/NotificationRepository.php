<?php

namespace App\Repository\Core;

use App\Entity\Core\Notification;
use App\Entity\Core\User;
use App\Entity\Core\Service;
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

    public function findLatestTransmissionNotificationMetaForServiceAndTransmissionId(Service $service, int $transmissionId): ?array
    {
        if ($transmissionId <= 0) {
            return null;
        }

        $queryBuilder = $this->createQueryBuilder('n')
            ->where('n.service = :service')
            ->andWhere('n.type IN (:types)')
            ->setParameter('service', $service)
            ->setParameter('types', ['transmission', 'transmission_add', 'transmission_copie']);

        // data est stocké en JSON : éviter les collisions (ex: 12 dans 123) en cherchant une fin `,` ou `}`.
        // MySQL affiche souvent le JSON avec des espaces (ex: "transmission_id": 123).
        // Le % après ":" rend la recherche tolérante aux espaces/tabs.
        $queryBuilder
            ->andWhere('(
                n.data LIKE :num_comma
                OR n.data LIKE :num_end
                OR n.data LIKE :str_comma
                OR n.data LIKE :str_end
            )')
            ->setParameter('num_comma', '%"transmission_id":%' . $transmissionId . ',%')
            ->setParameter('num_end', '%"transmission_id":%' . $transmissionId . '}%')
            ->setParameter('str_comma', '%"transmission_id":%"' . $transmissionId . '",%')
            ->setParameter('str_end', '%"transmission_id":%"' . $transmissionId . '"}%')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(1);

        $notification = $queryBuilder->getQuery()->getOneOrNullResult();
        if (!$notification instanceof Notification) {
            return null;
        }

        return [
            'id' => $notification->getId(),
            'is_read' => $notification->isRead(),
        ];
    }

    public function markTransmissionNotificationsAsReadByServiceAndTransmissionId(
        Service $service,
        int $transmissionId,
        ?\DateTimeImmutable $now = null
    ): int {
        return $this->markTransmissionNotificationsAsReadByServiceAndTransmissionIds($service, [$transmissionId], $now);
    }

    /**
     * Marque comme lues les notifications d'un service pour une ou plusieurs transmissions
     * (types: transmission, transmission_add, transmission_copie).
     *
     * Note: `data` est stocké en JSON. On filtre via LIKE sur la clé `transmission_id` pour rester compatible
     * avec l'existant, en évitant les collisions (ex: 12 dans 123) grâce à une fin `,` ou `}`.
     *
     * @param Service $service
     * @param int[] $transmissionIds
     */
    public function markTransmissionNotificationsAsReadByServiceAndTransmissionIds(
        Service $service,
        array $transmissionIds,
        ?\DateTimeImmutable $now = null
    ): int {
        $transmissionIds = array_values(array_unique(array_map('intval', $transmissionIds)));
        $transmissionIds = array_values(array_filter($transmissionIds, static fn (int $id) => $id > 0));

        if (empty($transmissionIds)) {
            return 0;
        }

        $now ??= new \DateTimeImmutable();

        $queryBuilder = $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->set('n.updatedAt', ':now')
            ->where('n.service = :service')
            ->andWhere('n.type IN (:types)')
            ->andWhere('n.isRead = false')
            ->setParameter('service', $service)
            ->setParameter('types', ['transmission', 'transmission_add', 'transmission_copie'])
            ->setParameter('now', $now);

        $orX = $queryBuilder->expr()->orX();
        foreach ($transmissionIds as $idx => $id) {
            $orX->add("n.data LIKE :tid_{$idx}_num_comma");
            $orX->add("n.data LIKE :tid_{$idx}_num_end");
            $orX->add("n.data LIKE :tid_{$idx}_str_comma");
            $orX->add("n.data LIKE :tid_{$idx}_str_end");

            // MySQL affiche souvent le JSON avec des espaces (ex: "transmission_id": 123).
            // Le % après ":" rend la recherche tolérante aux espaces/tabs.
            $queryBuilder
                ->setParameter("tid_{$idx}_num_comma", '%"transmission_id":%' . $id . ',%')
                ->setParameter("tid_{$idx}_num_end", '%"transmission_id":%' . $id . '}%')
                ->setParameter("tid_{$idx}_str_comma", '%"transmission_id":%"' . $id . '",%')
                ->setParameter("tid_{$idx}_str_end", '%"transmission_id":%"' . $id . '"}%');
        }

        $queryBuilder->andWhere($orX);

        return (int) $queryBuilder->getQuery()->execute();
    }

    public function markTransmissionReponseNotificationsAsReadByServiceAndTransmissionReponseId(
        Service $service,
        int $transmissionReponseId,
        ?\DateTimeImmutable $now = null
    ): int {
        return $this->markTransmissionReponseNotificationsAsReadByServiceAndTransmissionReponseIds(
            $service,
            [$transmissionReponseId],
            $now
        );
    }

    /**
     * Marque comme lues les notifications d'un service pour une ou plusieurs transmissions reponse.
     *
     * Note: `data` est stocké en JSON. On filtre via LIKE sur la clé `transmission_reponse_id` en évitant
     * les collisions (ex: 12 dans 123) grâce à une fin `,` ou `}`.
     *
     * @param Service $service
     * @param int[] $transmissionReponseIds
     */
    public function markTransmissionReponseNotificationsAsReadByServiceAndTransmissionReponseIds(
        Service $service,
        array $transmissionReponseIds,
        ?\DateTimeImmutable $now = null
    ): int {
        $transmissionReponseIds = array_values(array_unique(array_map('intval', $transmissionReponseIds)));
        $transmissionReponseIds = array_values(array_filter($transmissionReponseIds, static fn (int $id) => $id > 0));

        if (empty($transmissionReponseIds)) {
            return 0;
        }

        $now ??= new \DateTimeImmutable();

        $queryBuilder = $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->set('n.updatedAt', ':now')
            ->where('n.service = :service')
            ->andWhere('n.isRead = false')
            ->setParameter('service', $service)
            ->setParameter('now', $now);

        $orX = $queryBuilder->expr()->orX();
        foreach ($transmissionReponseIds as $idx => $id) {
            $orX->add("n.data LIKE :trid_{$idx}_num_comma");
            $orX->add("n.data LIKE :trid_{$idx}_num_end");
            $orX->add("n.data LIKE :trid_{$idx}_str_comma");
            $orX->add("n.data LIKE :trid_{$idx}_str_end");

            // MySQL affiche souvent le JSON avec des espaces (ex: "transmission_reponse_id": 123).
            // Le % après ":" rend la recherche tolérante aux espaces/tabs.
            $queryBuilder
                ->setParameter("trid_{$idx}_num_comma", '%"transmission_reponse_id":%' . $id . ',%')
                ->setParameter("trid_{$idx}_num_end", '%"transmission_reponse_id":%' . $id . '}%')
                ->setParameter("trid_{$idx}_str_comma", '%"transmission_reponse_id":%"' . $id . '",%')
                ->setParameter("trid_{$idx}_str_end", '%"transmission_reponse_id":%"' . $id . '"}%');
        }

        $queryBuilder->andWhere($orX);

        return (int) $queryBuilder->getQuery()->execute();
    }

    /**
     * Trouve toutes les notifications d'un utilisateur
     *
     * @param User $user
     * @param bool|null $isRead Filtre par statut de lecture (null = tous)
     * @return Notification[]
     */
    public function findByUser(User $user, ?bool $isRead = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC');

        if ($isRead !== null) {
            $qb->andWhere('n.isRead = :isRead')
               ->setParameter('isRead', $isRead);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve toutes les notifications d'un service
     *
     * @param Service $service
     * @param bool|null $isRead Filtre par statut de lecture (null = tous)
     * @return Notification[]
     */
    public function findByService(Service $service, ?bool $isRead = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.service = :service')
            ->setParameter('service', $service)
            ->orderBy('n.createdAt', 'DESC');

        if ($isRead !== null) {
            $qb->andWhere('n.isRead = :isRead')
               ->setParameter('isRead', $isRead);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve toutes les notifications pour un utilisateur ou son service
     *
     * @param User $user
     * @param bool|null $isRead Filtre par statut de lecture (null = tous)
     * @return Notification[]
     */
    public function findByUserOrService(User $user, ?bool $isRead = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->setParameter('user', $user);

        // Si l'utilisateur a un service, inclure aussi les notifications du service
        if ($user->getIdService() !== null) {
            $qb->orWhere('n.service = :service')
               ->setParameter('service', $user->getIdService());
        }

        if ($isRead !== null) {
            $qb->andWhere('n.isRead = :isRead')
               ->setParameter('isRead', $isRead);
        }

        $qb->orderBy('n.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les notifications par type
     *
     * @param User $user
     * @param string $type
     * @return Notification[]
     */
    public function findByUserAndType(User $user, string $type): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les notifications non lues d'un utilisateur
     *
     * @param User $user
     * @return int
     */
    public function countUnreadByUser(User $user): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Compte les notifications non lues d'un service
     *
     * @param Service $service
     * @return int
     */
    public function countUnreadByService(Service $service): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.service = :service')
            ->andWhere('n.isRead = false')
            ->setParameter('service', $service)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les notifications non lues pour un utilisateur ou son service
     *
     * @param User $user
     * @return int
     */
    public function countUnreadByUserOrService(User $user): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user);

        if ($user->getIdService() !== null) {
            $qb->orWhere('n.service = :service AND n.isRead = false')
               ->setParameter('service', $user->getIdService());
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     *
     * @param User $user
     * @return int Nombre de notifications marquées comme lues
     */
    public function markAllAsReadByUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Marque toutes les notifications d'un service comme lues
     *
     * @param Service $service
     * @return int Nombre de notifications marquées comme lues
     */
    public function markAllAsReadByService(Service $service): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->where('n.service = :service')
            ->andWhere('n.isRead = false')
            ->setParameter('service', $service)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les anciennes notifications lues (plus de X jours)
     *
     * @param int $days Nombre de jours
     * @return int Nombre de notifications supprimées
     */
    public function deleteOldReadNotifications(int $days = 30): int
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.isRead = true')
            ->andWhere('n.readAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Recherche les notifications par texte
     *
     * @param User $user
     * @param string $searchTerm
     * @return Notification[]
     */
    public function searchByUser(User $user, string $searchTerm): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.titre LIKE :search OR n.message LIKE :search')
            ->setParameter('user', $user)
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime les notifications d'un service pour une ou plusieurs transmissions.
     *
     * @param Service $service
     * @param int[] $transmissionIds
     */
    public function deleteTransmissionNotificationsByServiceAndTransmissionIds(
        Service $service,
        array $transmissionIds
    ): int {
        $transmissionIds = array_values(array_unique(array_map('intval', $transmissionIds)));
        $transmissionIds = array_values(array_filter($transmissionIds, static fn (int $id) => $id > 0));

        if (empty($transmissionIds)) {
            return 0;
        }

        $queryBuilder = $this->createQueryBuilder('n')
            ->delete()
            ->where('n.service = :service')
            ->andWhere('n.type IN (:types)')
            ->setParameter('service', $service)
            ->setParameter('types', ['transmission', 'transmission_add', 'transmission_copie']);

        $orX = $queryBuilder->expr()->orX();
        foreach ($transmissionIds as $idx => $id) {
            $orX->add("n.data LIKE :tid_{$idx}_num_comma");
            $orX->add("n.data LIKE :tid_{$idx}_num_end");
            $orX->add("n.data LIKE :tid_{$idx}_str_comma");
            $orX->add("n.data LIKE :tid_{$idx}_str_end");

            $queryBuilder
                ->setParameter("tid_{$idx}_num_comma", '%"transmission_id":%' . $id . ',%')
                ->setParameter("tid_{$idx}_num_end", '%"transmission_id":%' . $id . '}%')
                ->setParameter("tid_{$idx}_str_comma", '%"transmission_id":%"' . $id . '",%')
                ->setParameter("tid_{$idx}_str_end", '%"transmission_id":%"' . $id . '"}%');
        }

        $queryBuilder->andWhere($orX);

        return (int) $queryBuilder->getQuery()->execute();
    }
}
