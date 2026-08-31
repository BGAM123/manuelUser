<?php

namespace App\EventSubscriber;

use App\Entity\Notification;
use App\Entity\ConsumableTransfer;
use App\Entity\AssetAssignment;
use App\Event\AcknowledgementRecordedEvent;
use App\Repository\ConsumableTransferRepository;
use App\Repository\AssetAssignmentRepository;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Boucle de rétroaction : dès qu'un accusé de réception est enregistré, prévient l'émetteur
 * initial (s'il est connu) que le bien/transfert a bien été réceptionné. Démontre le point
 * d'extension par événement du module notifications — un futur listener peut se brancher de
 * la même façon sur AcknowledgementRecordedEvent sans toucher AcknowledgementService.
 */
final class AcknowledgementNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ConsumableTransferRepository $consumableTransferRepository,
        private readonly AssetAssignmentRepository $assetAssignmentRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AcknowledgementRecordedEvent::class => 'onAcknowledgementRecorded',
        ];
    }

    public function onAcknowledgementRecorded(AcknowledgementRecordedEvent $event): void
    {
        $acknowledgement = $event->getAcknowledgement();

        // Les accusés sont centralisés dans AcknowledgementOfReceipt. Pour les
        // transferts de consommables, conserver aussi le statut dénormalisé évite une
        // jointure supplémentaire dans les listes sans en faire la source de vérité.
        if (Notification::SUBJECT_CONSUMABLE_TRANSFER === $acknowledgement->getSubjectType()) {
            $transfer = $this->consumableTransferRepository->getActiveById($acknowledgement->getSubjectId());
            if ($transfer instanceof ConsumableTransfer) {
                $transfer
                    ->setIsAcknowledged(true)
                    ->setAcknowledgedAt($acknowledgement->getAcknowledgedAt());
                $this->consumableTransferRepository->save($transfer);
            }
        }

        // ✅ Pour les affectations de biens, mettre à jour le champ received
        if (Notification::SUBJECT_ASSET_ASSIGNMENT === $acknowledgement->getSubjectType()) {
            $assignment = $this->assetAssignmentRepository->find($acknowledgement->getSubjectId());
            if ($assignment instanceof AssetAssignment) {
                $assignment->setReceived(true);
                $this->assetAssignmentRepository->save($assignment);
            }
        }

        $sender = $event->getSender();
        if (!$sender) {
            return;
        }

        $recipient = $acknowledgement->getRecipient();
        $recipientLabel = $recipient
            ? trim(sprintf('%s %s', $recipient->getFirstName(), $recipient->getLastName()))
            : 'Le destinataire';

        $this->notificationService->notify(
            $sender,
            $acknowledgement->getSubjectType(),
            $acknowledgement->getSubjectId(),
            Notification::TYPE_ACKNOWLEDGED,
            'Accusé de réception reçu',
            sprintf('%s a accusé réception le %s.', $recipientLabel, $acknowledgement->getAcknowledgedAt()?->format('d/m/Y à H:i')),
            $recipient
        );
    }
}
