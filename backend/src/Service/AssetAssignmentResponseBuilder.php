<?php

namespace App\Service;

use App\Entity\AcknowledgementOfReceipt;
use App\Entity\AssetAssignment;
use App\Entity\Notification;
use App\Entity\PieceJointe;

final class AssetAssignmentResponseBuilder
{
    public function __construct(
        private readonly AssetAssignmentService $assignmentService,
        private readonly AcknowledgementService $acknowledgementService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function buildDetail(AssetAssignment $assignment): array
    {
        $ack = $this->acknowledgementService->findForSubject(Notification::SUBJECT_ASSET_ASSIGNMENT, $assignment->getId());
        $notification = $this->notificationService->findOneBySubjectAndType(Notification::SUBJECT_ASSET_ASSIGNMENT, $assignment->getId(), Notification::TYPE_CREATED);

        return $this->build($assignment, $ack, $notification);
    }

    public function buildList(array $assignments): array
    {
        $ids = array_values(array_filter(array_map(fn (AssetAssignment $a) => $a->getId(), $assignments)));
        $acks = $this->acknowledgementService->findForSubjects(Notification::SUBJECT_ASSET_ASSIGNMENT, $ids);
        $notifications = $this->notificationService->findLatestForSubjects(Notification::SUBJECT_ASSET_ASSIGNMENT, $ids, Notification::TYPE_CREATED);

        return array_map(
            fn (AssetAssignment $a) => $this->build($a, $acks[$a->getId()] ?? null, $notifications[$a->getId()] ?? null),
            $assignments
        );
    }

    private function build(AssetAssignment $assignment, ?AcknowledgementOfReceipt $ack, ?Notification $notification): array
    {
        $service = $assignment->getService();

        // Utilisateur affecté directement au bien
        $assignedUser = $assignment->getUser();
        $assignedUserData = null;
        if ($assignedUser) {
            $assignedUserData = [
                'id' => $assignedUser->getId(),
                'nom' => $assignedUser->getLastName(),
                'prenom' => $assignedUser->getFirstName(),
                'matricule' => $assignedUser->getMatricule(),
                'email' => $assignedUser->getEmail(),
                // Service/poste de rattachement de l'utilisateur affecté —
                // nécessaire pour la colonne "Destination" du tableau des
                // affectations quand le bien est affecté à un individu plutôt
                // qu'à un service (ajout 2026-09-01).
                'service' => $assignedUser->getService() ? [
                    'id' => $assignedUser->getService()->getId(),
                    'nom' => $assignedUser->getService()->getNom(),
                ] : null,
            ];
        }

        // Utilisateur lié au service (si différent de l'utilisateur affecté)
        $serviceUserData = null;
        if ($service && (!$assignedUser || $assignedUser->getService()?->getId() !== $service->getId())) {
            $serviceUserData = $this->assignmentService->findUserByService($service);
        }

        return [
            'id' => $assignment->getId(),
            'typeAffectation' => $assignment->getTypeAffectation(),
            'dateDebut' => $assignment->getDateDebut()?->format('Y-m-d'),
            'dateFin' => $assignment->getDateFin()?->format('Y-m-d'),
            'commentaire' => $assignment->getCommentaire(),
            'service' => $this->buildService($service),
            'localisation' => $service ? $this->buildLocalisation($service) : null,
            'utilisateur' => $assignedUserData, // Utilisateur affecté au bien
            'utilisateurDuService' => $serviceUserData, // Utilisateur lié au service (si différent)
            'piecesJointes' => $this->buildPiecesJointes($assignment),
            'createdAt' => $assignment->getCreatedAt()?->format('Y-m-d H:i:s'),
            'createdBy' => $assignment->getCreatedBy() ? [
                'id' => $assignment->getCreatedBy()->getId(),
                'firstName' => $assignment->getCreatedBy()->getFirstName(),
                'lastName' => $assignment->getCreatedBy()->getLastName(),
            ] : null,
            'accuseReception' => $this->buildAccuseReception($ack),
            'notification' => $this->buildNotificationStatus($notification),
        ];
    }

    private function buildAccuseReception(?AcknowledgementOfReceipt $ack): array
    {
        if (!$ack) {
            return ['effectue' => false, 'date' => null, 'par' => null, 'commentaire' => null];
        }

        $recipient = $ack->getRecipient();

        return [
            'effectue' => true,
            'date' => $ack->getAcknowledgedAt()?->format('Y-m-d H:i:s'),
            'par' => $recipient ? [
                'id' => $recipient->getId(),
                'nom' => $recipient->getLastName(),
                'prenom' => $recipient->getFirstName(),
            ] : null,
            'commentaire' => $ack->getComment(),
        ];
    }

    private function buildNotificationStatus(?Notification $notification): array
    {
        return [
            'lue' => $notification?->isRead() ?? false,
            'dateLecture' => $notification?->getReadAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function buildService(?object $service): ?array
    {
        if (!$service) {
            return null;
        }

        return [
            'id' => $service->getId(),
            'nom' => $service->getNom(),
        ];
    }

    private function buildLocalisation(?object $service): ?array
    {
        if (!$service) {
            return null;
        }

        $region = $service->getRegion();
        $departement = $service->getDepartement();
        $arrondissement = $service->getArrondissement();

        if (!$region && !$departement && !$arrondissement) {
            return null;
        }

        return [
            'region' => $region ? [
                'id' => $region->getId(),
                'nom' => $region->getNom(),
            ] : null,
            'departement' => $departement ? [
                'id' => $departement->getId(),
                'nom' => $departement->getNom(),
            ] : null,
            'arrondissement' => $arrondissement ? [
                'id' => $arrondissement->getId(),
                'nom' => $arrondissement->getNom(),
            ] : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPiecesJointes(AssetAssignment $assignment): array
    {
        return $assignment->getPieceJointes()->map(function (PieceJointe $piece) {
            return [
                'id' => $piece->getId(),
                'nom' => $piece->getNom(),
                'chemin' => $piece->getChemin(),
            ];
        })->toArray();
    }
}
