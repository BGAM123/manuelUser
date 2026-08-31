<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetAssignment;
use App\Entity\Notification;
use App\Entity\PieceJointe;
use App\Entity\Service;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\AssetAssignmentRepository;
use App\Repository\AssetRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AssetAssignmentService
{
    public function __construct(
        private readonly AssetAssignmentRepository $assignmentRepository,
        private readonly AssetRepository $assetRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly FileUploadService $fileUploadService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function create(
        array $payload,
        array $documents = [],
        array $documentLabels = [],
        ?User $currentUser = null
    ): AssetAssignment {
        $assignment = new AssetAssignment();
        $assignment->setAssignedBy($currentUser);

        // Définir l'utilisateur créateur si fourni
        if ($currentUser !== null) {
            $assignment->setCreatedBy($currentUser);
        }

        $this->applyPayload($assignment, $payload);
        $this->attachFiles($assignment, $documents, $documentLabels);

        // ✅ Gérer le champ detenteur : passer l'ancien détenteur à false et le nouveau à true
        $this->manageDetenteurStatus($assignment);

        $this->validate($assignment);
        $this->assignmentRepository->save($assignment);

        $this->notifyRecipient($assignment, $currentUser);

        return $assignment;
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function update(
        AssetAssignment $assignment,
        array $payload,
        array $documents = [],
        array $documentLabels = [],
        ?User $currentUser = null
    ): AssetAssignment {
        $this->applyPayload($assignment, $payload);
        $this->attachFiles($assignment, $documents, $documentLabels);

        // Définir l'utilisateur modificateur si fourni
        if ($currentUser !== null) {
            $assignment->setUpdatedBy($currentUser);
        }

        $this->validate($assignment);
        $this->assignmentRepository->save($assignment);

        return $assignment;
    }

    public function delete(AssetAssignment $assignment): void
    {
        $assignment->setDelete(true);
        $this->assignmentRepository->save($assignment);
    }

    public function deletePieceJointe(AssetAssignment $assignment, PieceJointe $pieceJointe): void
    {
        $assignment->removePieceJointe($pieceJointe);
        $this->entityManager->flush();

        $this->entityManager->remove($pieceJointe);
        $this->entityManager->flush();

        $this->fileUploadService->deleteFile($pieceJointe->getChemin());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(AssetAssignment $assignment, array $payload): void
    {
        if (isset($payload['asset_id'])) {
            $asset = $this->assetRepository->find($payload['asset_id']);
            if (!$asset) {
                throw new ResourceNotFoundException('Bien introuvable.');
            }
            $assignment->setAsset($asset);
        }

        if (isset($payload['user_id'])) {
            $user = $this->userRepository->find($payload['user_id']);
            if (!$user) {
                throw new ResourceNotFoundException('Utilisateur introuvable.');
            }
            $assignment->setUser($user);
        }

        // if (isset($payload['service_id'])) {
        //     $service = $this->serviceRepository->find($payload['service_id']);
        //     if (!$service) {
        //         throw new ResourceNotFoundException('Service introuvable.');
        //     }
        //     $assignment->setService($service);
        // }

        if (isset($payload['typeAffectation'])) {
            $assignment->setTypeAffectation($payload['typeAffectation']);
        }

        if (isset($payload['dateDebut']) && !empty($payload['dateDebut'])) {
            $assignment->setDateDebut(new \DateTime($payload['dateDebut']));
        }

        if (isset($payload['dateFin']) && !empty($payload['dateFin'])) {
            $assignment->setDateFin(new \DateTime($payload['dateFin']));
        }

        if (isset($payload['commentaire'])) {
            $assignment->setCommentaire($payload['commentaire']);
        }
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function attachFiles(AssetAssignment $assignment, array $documents, array $documentLabels): void
    {
        $documentLabels = UploadedFilesNormalizer::parseLabelList($documentLabels);

        foreach (array_values($documents) as $index => $document) {
            if (!$document instanceof UploadedFile) {
                continue;
            }
            if (UPLOAD_ERR_NO_FILE === $document->getError()) {
                continue;
            }
            if (!$document->isValid()) {
                throw new ValidationFailedException([
                    'piecesJointes' => 'Fichier document invalide : ' . ($document->getErrorMessage() ?: 'erreur d\'upload'),
                ]);
            }

            $label = $documentLabels[$index] ?? null;
            $piece = $this->fileUploadService->upload(
                $document,
                FileUploadService::KIND_GENERIC,
                $label,
                true
            );
            $assignment->addPieceJointe($piece);
        }
    }

    private function validate(AssetAssignment $assignment): void
    {
        $errors = $this->validator->validate($assignment);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($errorMessages);
        }
    }

    /**
     * Recherche automatiquement si le service est associé à un utilisateur
     */
    public function findUserByService(?Service $service): ?array
    {
        if (!$service) {
            return null;
        }

        $user = $this->userRepository->findOneBy(['service' => $service, 'isDelete' => false, 'isActive' => true]);
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
        ];
    }

    /**
     * Destinataire réel de l'affectation : l'utilisateur affecté directement, sinon
     * l'utilisateur rattaché au service. Utilisé à la fois pour notifier à la création et
     * pour vérifier l'éligibilité d'un utilisateur à accuser réception.
     */
    public function resolveRecipient(AssetAssignment $assignment): ?User
    {
        if ($assignment->getUser()) {
            return $assignment->getUser();
        }

        if ($assignment->getService()) {
            return $this->userRepository->findActiveByServiceId($assignment->getService()->getId());
        }

        return null;
    }

    private function notifyRecipient(AssetAssignment $assignment, ?User $currentUser): void
    {
        $recipient = $this->resolveRecipient($assignment);
        if (!$recipient) {
            return;
        }

        $this->notificationService->notify(
            $recipient,
            Notification::SUBJECT_ASSET_ASSIGNMENT,
            $assignment->getId(),
            Notification::TYPE_CREATED,
            'Nouvelle affectation de bien',
            sprintf('Le bien "%s" vous a été affecté.', $assignment->getAsset()?->getNom() ?? ('#' . $assignment->getAsset()?->getId())),
            $currentUser
        );
    }

    /**
     * Crée automatiquement une affectation lors de la création d'un bien
     * @param Asset $asset
     * @param int|null $serviceId
     * @param \App\Entity\User|null $user
     * @return AssetAssignment|null
     */
    public function createAutomaticAssignmentOnAssetCreate(Asset $asset, ?int $serviceId = null, ?\App\Entity\User $user = null): ?AssetAssignment
    {
        if (!$serviceId && !$user) {
            return null;
        }

        $assignment = new AssetAssignment();
        $assignment->setAsset($asset);
        $assignment->setTypeAffectation('AFFECTATION');

        // Utiliser la date de création du bien comme date d'affectation, sinon la date d'acquisition
        $dateDebut = $asset->getCreatedAt() ?: $asset->getDateAcquisition();
        if ($dateDebut instanceof \DateTimeImmutable) {
            $dateDebut = \DateTime::createFromImmutable($dateDebut);
        }
        $assignment->setDateDebut($dateDebut);

        if ($user) {
            $assignment->setUser($user);
            $assignment->setCommentaire('Affectation initiale lors de la création du bien (utilisateur).');
        } elseif ($serviceId) {
            $service = $this->serviceRepository->find($serviceId);
            if (!$service) {
                return null;
            }
            $assignment->setService($service);
            $assignment->setCommentaire('Affectation initiale lors de la création du bien (service).');
        }

        // ✅ Passer la nouvelle affectation à true (c'est la première, pas d'ancien détenteur)
        $assignment->setDetenteur(true);

        // ✅ Sauvegarder l'affectation
        $this->assignmentRepository->save($assignment);

        return $assignment;
    }

    /**
     * Crée automatiquement une affectation lors de la modification d'un bien avec changement de service
     */
    public function createAutomaticAssignmentOnAssetUpdate(Asset $asset, ?int $newServiceId): ?AssetAssignment
    {
        if (!$newServiceId) {
            return null;
        }

        // Récupérer le service actuel du bien
        $currentServices = $asset->getServices();
        if ($currentServices->isEmpty()) {
            return null;
        }

        $currentService = $currentServices->first();
        if ($currentService->getId() === $newServiceId) {
            // Le service n'a pas changé
            return null;
        }

        $newService = $this->serviceRepository->find($newServiceId);
        if (!$newService) {
            return null;
        }

        $assignment = new AssetAssignment();
        $assignment->setAsset($asset);
        $assignment->setService($newService);
        $assignment->setTypeAffectation('AFFECTATION');
        $assignment->setDateDebut(new \DateTime());
        $assignment->setCommentaire('Affectation automatique suite à la modification du bien.');

        // ✅ Gérer le statut de détenteur : passer l'ancien détenteur à false et le nouveau à true
        $this->manageDetenteurStatus($assignment);

        $this->assignmentRepository->save($assignment);

        return $assignment;
    }

    /**
     * ✅ Restitue un bien avec logique automatique
     * @param Asset $asset
     * @param array<string, mixed> $payload
     * @param User|null $currentUser
     * @return AssetAssignment
     */
    public function restituerAsset(Asset $asset, array $payload, ?User $currentUser): AssetAssignment
    {
        // ✅ Logique automatique : dateDebut = date du jour si non fournie
        $dateDebut = new \DateTime();
        if (isset($payload['dateDebut']) && !empty($payload['dateDebut'])) {
            $dateDebut = new \DateTime($payload['dateDebut']);
        }

        // ✅ Logique automatique : si user_id non fourni, utiliser userRestitution du bien
        $userId = $payload['user_id'] ?? null;
        if (null === $userId) {
            $userRestitution = $asset->getUserRestitution();
            if ($userRestitution) {
                $userId = $userRestitution->getId();
            }
        }

        // ✅ Logique automatique : si affectation en cours, renseigner dateFin (date fournie ou date du jour)
        $lastAssignment = $asset->getAssignments()->last();
        if ($lastAssignment && $lastAssignment->isDetenteur() && null === $lastAssignment->getDateFin()) {
            $dateFin = isset($payload['dateFin']) && !empty($payload['dateFin']) 
                ? new \DateTime($payload['dateFin']) 
                : new \DateTime();
            $lastAssignment->setDateFin($dateFin);
            $lastAssignment->setDetenteur(false);
            $this->assignmentRepository->save($lastAssignment);
        }

        // Créer la nouvelle affectation de restitution
        $assignment = new AssetAssignment();
        $assignment->setAsset($asset);
        $assignment->setTypeAffectation('RESTITUTION');
        $assignment->setDateDebut($dateDebut);
        $assignment->setDetenteur(true);
        $assignment->setCommentaire($payload['commentaire'] ?? 'Restitution du bien.');

        if ($userId) {
            $user = $this->userRepository->find($userId);
            if (!$user) {
                throw new ResourceNotFoundException('Utilisateur introuvable.');
            }
            $assignment->setUser($user);
        }

        // Définir l'utilisateur créateur si fourni
        if ($currentUser !== null) {
            $assignment->setCreatedBy($currentUser);
            $assignment->setAssignedBy($currentUser);
        }

        $this->validate($assignment);
        $this->assignmentRepository->save($assignment);

        // ✅ Personnaliser le message de notification pour les restitutions
        $recipient = $this->resolveRecipient($assignment);
        if ($recipient) {
            $this->notificationService->notify(
                $recipient,
                Notification::SUBJECT_ASSET_ASSIGNMENT,
                $assignment->getId(),
                Notification::TYPE_CREATED,
                'Restitution de bien',
                sprintf('Le bien "%s" vous a été restitué.', $assignment->getAsset()?->getNom() ?? ('#' . $assignment->getAsset()?->getId())),
                $currentUser
            );
        }

        return $assignment;
    }

    /**
     * ✅ Gère le statut de détenteur : passe l'ancien détenteur à false et le nouveau à true
     */
    private function manageDetenteurStatus(AssetAssignment $newAssignment): void
    {
        $asset = $newAssignment->getAsset();
        if (!$asset) {
            return;
        }

        // Récupérer toutes les affectations du bien
        $existingAssignments = $this->assignmentRepository->findBy([
            'asset' => $asset,
            'isDelete' => false,
        ]);

        // Passer tous les détenteurs existants à false (sauf la nouvelle affectation)
        foreach ($existingAssignments as $existingAssignment) {
            // Ignorer la nouvelle affectation si elle est déjà dans la liste (cas rare)
            if ($existingAssignment === $newAssignment) {
                continue;
            }
            if ($existingAssignment->isDetenteur()) {
                $existingAssignment->setDetenteur(false);
                $this->entityManager->persist($existingAssignment);
            }
        }

        // Passer la nouvelle affectation à true
        $newAssignment->setDetenteur(true);
    }
}
