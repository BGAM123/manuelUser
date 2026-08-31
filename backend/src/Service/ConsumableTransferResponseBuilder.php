<?php

namespace App\Service;

use App\Entity\AcknowledgementOfReceipt;
use App\Entity\ConsumableTransfer;
use App\Entity\Notification;

final class ConsumableTransferResponseBuilder
{
    public function __construct(
        private readonly AcknowledgementService $acknowledgementService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function buildDetail(ConsumableTransfer $transfer): array
    {
        $ack = $this->acknowledgementService->findForSubject(Notification::SUBJECT_CONSUMABLE_TRANSFER, $transfer->getId());
        $notification = $this->notificationService->findOneBySubjectAndType(Notification::SUBJECT_CONSUMABLE_TRANSFER, $transfer->getId(), Notification::TYPE_CREATED);

        $data = $this->buildBase($transfer);
        $data['pieceJointes'] = array_map(fn ($pj) => [
            'id' => $pj->getId(),
            'nom' => $pj->getNom(),
            'chemin' => $pj->getChemin(),
        ], $transfer->getPieceJointes()->toArray());
        $data['consumableBsp'] = $this->buildConsumableBsp($transfer);

        return $this->withReceptionStatus($data, $ack, $notification);
    }


    /**
     * 🔥 MÉTHODE MODIFIÉE
     * Ajouter stockActuel dans la réponse
     */
    private function buildTransfer(ConsumableTransfer $transfer, bool $detail = false): array
    {
        $data = [
            'id' => $transfer->getId(),
            'consumable' => $this->buildConsumable($transfer->getConsumable()),
            'serviceDestination' => $this->buildService($transfer->getServiceDestination()),
            'serviceSource' => $transfer->getServiceSource() ? $this->buildService($transfer->getServiceSource()) : null,
            'type' => $transfer->getType(),
            'statut' => $transfer->getStatut(),
            'quantite' => $transfer->getQuantite(),
            'stockActuel' => $transfer->getStockActuel(), // 🔥 NOUVEAU CHAMP
            'dateTransfert' => $transfer->getDateTransfert()?->format('Y-m-d'),
            'observations' => $transfer->getObservations(),
            'createdAt' => $transfer->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $transfer->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'isAcknowledged' => $transfer->isAcknowledged(),
            'quantityConsumed' => $transfer->getQuantityConsumed(),
        ];

        // Détail avec plus d'informations
        if ($detail) {
            $data['pieceJointes'] = $this->buildPieceJointes($transfer->getPieceJointes());
            $data['consumableBsp'] = $transfer->getConsumableBsp() ? $this->buildConsumableBsp($transfer->getConsumableBsp()) : null;
            $data['acknowledgedAt'] = $transfer->getAcknowledgedAt()?->format('Y-m-d H:i:s');
            $data['stockActuel'] = $transfer->getStockActuel(); // Déjà ajouté mais on le garde
        }

        return $data;
    }

    /**
     * @param ConsumableTransfer[] $transfers
     */
    public function buildList(array $transfers): array
    {
        $ids = array_values(array_filter(array_map(fn (ConsumableTransfer $t) => $t->getId(), $transfers)));
        $acks = $this->acknowledgementService->findForSubjects(Notification::SUBJECT_CONSUMABLE_TRANSFER, $ids);
        $notifications = $this->notificationService->findLatestForSubjects(Notification::SUBJECT_CONSUMABLE_TRANSFER, $ids, Notification::TYPE_CREATED);

        return array_map(
            fn (ConsumableTransfer $t) => $this->withReceptionStatus($this->buildBase($t), $acks[$t->getId()] ?? null, $notifications[$t->getId()] ?? null),
            $transfers
        );
    }

    private function buildBase(ConsumableTransfer $transfer): array
    {
        return [
            'id' => $transfer->getId(),
            'consumable' => $transfer->getConsumable() ? ['id' => $transfer->getConsumable()->getId(), 'nom' => $transfer->getConsumable()->getNom()] : null,
            'serviceDestination' => $transfer->getServiceDestination() ? ['id' => $transfer->getServiceDestination()->getId(), 'nom' => $transfer->getServiceDestination()->getNom()] : null,
            'serviceSource' => $transfer->getServiceSource() ? ['id' => $transfer->getServiceSource()->getId(), 'nom' => $transfer->getServiceSource()->getNom()] : null,
            'type' => $transfer->getType(),
            'statut' => $transfer->getStatut(),
            'quantite' => $transfer->getQuantite(),
            'stockActuel' => $transfer->getStockActuel(), // 🔥 Ajouté : stock depuis la table consumable_transfer
            'quantityConsumed' => $transfer->getQuantityConsumed(),
            'isAcknowledged' => $transfer->isAcknowledged(),
            'dateTransfert' => $transfer->getDateTransfert()?->format('Y-m-d'),
            'observations' => $transfer->getObservations(),
            'createdAt' => $transfer->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $transfer->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function buildConsumableBsp(ConsumableTransfer $transfer): ?array
    {
        $consumableBsp = $transfer->getConsumableBsp();
        if (!$consumableBsp) {
            return null;
        }

        $bsp = $consumableBsp->getBsp();
        $validateurRetour = $bsp->getValidateurRetour();

        return [
            'id' => $consumableBsp->getId(),
            'bsp' => [
                'id' => $bsp->getId(),
                'numero' => $bsp->getNumero(),
                'retour' => $bsp->isRetour(),
                'dateRetourEffective' => $bsp->getDateRetourEffective()?->format('Y-m-d'),
                'validateurRetour' => $validateurRetour ? [
                    'id' => $validateurRetour->getId(),
                    'nom' => $validateurRetour->getLastName(),
                    'prenom' => $validateurRetour->getFirstName(),
                    'matricule' => $validateurRetour->getMatricule(),
                ] : null,
            ],
        ];
    }

    private function withReceptionStatus(array $data, ?AcknowledgementOfReceipt $ack, ?Notification $notification): array
    {
        $recipient = $ack?->getRecipient();

        $data['accuseReception'] = $ack ? [
            'effectue' => true,
            'date' => $ack->getAcknowledgedAt()?->format('Y-m-d H:i:s'),
            'par' => $recipient ? [
                'id' => $recipient->getId(),
                'nom' => $recipient->getLastName(),
                'prenom' => $recipient->getFirstName(),
            ] : null,
            'commentaire' => $ack->getComment(),
        ] : ['effectue' => false, 'date' => null, 'par' => null, 'commentaire' => null];

        $data['notification'] = [
            'lue' => $notification?->isRead() ?? false,
            'dateLecture' => $notification?->getReadAt()?->format('Y-m-d H:i:s'),
        ];

        return $data;
    }
}
