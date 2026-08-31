<?php

namespace App\Service;

use App\Entity\AcknowledgementOfReceipt;
use App\Entity\Notification;
use App\Entity\User;
use App\Event\AcknowledgementRecordedEvent;
use App\Exception\ValidationFailedException;
use App\Repository\AcknowledgementOfReceiptRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Point d'entrée générique pour les accusés de réception, indépendant de tout module métier.
 * L'unicité "une seule fois par transaction" est vérifiée ici (défense en profondeur avec la
 * contrainte unique en base) et la boucle de rétroaction vers l'émetteur initial est déclenchée
 * via un événement plutôt qu'un appel direct, afin que de futurs modules puissent réagir à un
 * accusé de réception sans modifier ce service.
 */
final class AcknowledgementService
{
    public function __construct(
        private readonly AcknowledgementOfReceiptRepository $acknowledgementRepository,
        private readonly NotificationService $notificationService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function acknowledge(User $currentUser, string $subjectType, int $subjectId, ?string $comment = null): AcknowledgementOfReceipt
    {
        if ($this->acknowledgementRepository->findOneBySubject($subjectType, $subjectId)) {
            throw new ValidationFailedException(
                ['acknowledgement' => 'Un accusé de réception a déjà été enregistré pour cette transaction.'],
                'Un accusé de réception a déjà été enregistré pour cette transaction.'
            );
        }

        $acknowledgement = new AcknowledgementOfReceipt();
        $acknowledgement->setSubjectType($subjectType);
        $acknowledgement->setSubjectId($subjectId);
        $acknowledgement->setRecipient($currentUser);
        $acknowledgement->setComment($comment);

        $this->acknowledgementRepository->save($acknowledgement);

        $creationNotification = $this->notificationService->findOneBySubjectAndType($subjectType, $subjectId, Notification::TYPE_CREATED);
        $sender = $creationNotification?->getSender();

        $this->eventDispatcher->dispatch(new AcknowledgementRecordedEvent($acknowledgement, $sender));

        return $acknowledgement;
    }

    public function findForSubject(string $subjectType, int $subjectId): ?AcknowledgementOfReceipt
    {
        return $this->acknowledgementRepository->findOneBySubject($subjectType, $subjectId);
    }

    /**
     * @param list<int> $subjectIds
     * @return array<int, AcknowledgementOfReceipt>
     */
    public function findForSubjects(string $subjectType, array $subjectIds): array
    {
        return $this->acknowledgementRepository->findForSubjects($subjectType, $subjectIds);
    }
}
