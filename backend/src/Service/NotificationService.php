<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Repository\NotificationRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Point d'entrée générique pour créer et consulter des notifications, indépendant de tout
 * module métier. Un module se branche par un simple appel à notify() avec son propre
 * subjectType — aucune dépendance de ce service vers AssetAssignment/ConsumableTransfer.
 */
final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    public function notify(
        User $recipient,
        string $subjectType,
        int $subjectId,
        string $type,
        string $title,
        string $message,
        ?User $sender = null,
    ): Notification {
        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setSender($sender);
        $notification->setSubjectType($subjectType);
        $notification->setSubjectId($subjectId);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);

        $this->notificationRepository->save($notification);

        return $notification;
    }

    public function markAsRead(Notification $notification, User $currentUser): void
    {
        if ($notification->getRecipient()?->getId() !== $currentUser->getId()) {
            throw new AccessDeniedHttpException("Vous n'êtes pas autorisé à consulter cette notification.");
        }

        if ($notification->isRead()) {
            return;
        }

        $notification->setIsRead(true);
        $notification->setReadAt(new \DateTime());
        $this->notificationRepository->save($notification);
    }

    public function markAllAsReadForUser(User $user): int
    {
        return $this->notificationRepository->markAllAsReadForUser($user);
    }

    /**
     * @return Notification[]
     */
    public function findForUser(User $user, int $page, int $limit, ?bool $isRead = null, ?string $subjectType = null): array
    {
        return $this->notificationRepository->findForUser($user, $page, $limit, $isRead, $subjectType);
    }

    public function countForUser(User $user, ?bool $isRead = null, ?string $subjectType = null): int
    {
        return $this->notificationRepository->countForUser($user, $isRead, $subjectType);
    }

    /**
     * Listing administrateur : toutes les notifications si $recipient est null, ou celles d'un
     * utilisateur choisi sinon. Contrairement à findForUser(), le destinataire n'est pas
     * forcément l'utilisateur connecté — l'accès à ce listing doit être restreint au niveau du
     * contrôleur/route (permission admin).
     *
     * @return Notification[]
     */
    public function findAllForAdmin(int $page, int $limit, ?bool $isRead = null, ?string $subjectType = null, ?User $recipient = null): array
    {
        return $this->notificationRepository->findPaginated($page, $limit, $isRead, $subjectType, $recipient);
    }

    public function countAllForAdmin(?bool $isRead = null, ?string $subjectType = null, ?User $recipient = null): int
    {
        return $this->notificationRepository->countAll($isRead, $subjectType, $recipient);
    }

    public function findOneBySubjectAndType(string $subjectType, int $subjectId, string $type): ?Notification
    {
        return $this->notificationRepository->findOneBySubjectAndType($subjectType, $subjectId, $type);
    }

    /**
     * @param list<int> $subjectIds
     * @return array<int, Notification>
     */
    public function findLatestForSubjects(string $subjectType, array $subjectIds, string $type): array
    {
        return $this->notificationRepository->findLatestForSubjects($subjectType, $subjectIds, $type);
    }

    public function getOwnedOrFail(int $id, User $currentUser): Notification
    {
        $notification = $this->notificationRepository->find($id);
        if (!$notification instanceof Notification) {
            throw new ResourceNotFoundException('La notification demandée est introuvable.');
        }

        if ($notification->getRecipient()?->getId() !== $currentUser->getId()) {
            throw new AccessDeniedHttpException("Vous n'êtes pas autorisé à consulter cette notification.");
        }

        return $notification;
    }
}
