<?php

namespace App\Service;

use App\Entity\ConsumableTransfer;
use App\Entity\Consumable;
use App\Entity\ConsumableBsp;
use App\Entity\Bsp;
use App\Entity\Notification;
use App\Entity\Service;
use App\Entity\PieceJointe;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\ConsumableTransferRepository;
use App\Repository\ConsumableRepository;
use App\Repository\ServiceRepository;
use App\Repository\BspRepository;
use App\Repository\UserRepository;
use App\Repository\PieceJointeRepository;
use App\Security\ConsumableAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\GestionStockConsommableService;

final class ConsumableTransferService
{
    public function __construct(
        private readonly ConsumableTransferRepository $consumableTransferRepository,
        private readonly ConsumableRepository $consumableRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly BspRepository $bspRepository,
        private readonly UserRepository $userRepository,
        private readonly PieceJointeRepository $pieceJointeRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
        // private readonly GestionStockConsommableService $gestionStockService,
        private readonly NotificationService $notificationService,
        private readonly ConsumableStockManager $stockManager,
        private readonly ConsumableTransferStockManager $transferStockManager, // 🔥 NOUVEAU
        private GestionStockConsommableService $gestionStockService, // EXISTANT (à conserver pour BSP)
        private readonly ConsumableAccessChecker $accessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function createBspTransfer(array $payload, array $documents = [], array $documentLabels = [], ?User $currentUser = null): ConsumableTransfer
{
    $this->entityManager->beginTransaction();

    try {
        // Créer le BSP
        $bsp = new Bsp();
        $bsp->setCreatedBy($currentUser);
        $bsp->setNumero($this->bspRepository->generateNextNumero());
        $bsp->setAssetExit(null);

        $this->applyBspPayload($bsp, $payload);

        // quantiteServie
        if (!array_key_exists('quantiteServie', $payload) || $payload['quantiteServie'] === null || $payload['quantiteServie'] === '') {
            if (array_key_exists('quantite', $payload) && $payload['quantite'] !== null) {
                $bsp->setQuantiteServie((int) $payload['quantite']);
            } else {
                $bsp->setQuantiteServie(1);
            }
        }

        // Gérer les pièces jointes BSP
        $this->attachBspFiles($bsp, $documents, $documentLabels);
        $this->validateBsp($bsp);
        $this->entityManager->persist($bsp);

        // Extraire les données du payload pour le transfert
        $consumableId = (int) $payload['consumable_id'];
        $serviceDestinationId = (int) $payload['service_destination_id'];
        $quantite = (string) $payload['quantite'];
        $observations = $payload['observations'] ?? null;

        $serviceSourceId = $this->resolveServiceSourceId($payload, $currentUser, $consumableId);

        // Créer le transfert (1 seule ligne, type BSP)
        $transfer = $this->transferStockManager->createTransfer(
            $consumableId,
            $serviceSourceId,
            $serviceDestinationId,
            $quantite,
            $observations,
            [], // Pas de documents ici, déjà attachés au BSP
            [],
            ConsumableTransfer::TYPE_BSP,
            ConsumableTransfer::STATUT_SORTI
        );

        // Lier le BSP au transfert
        $consumableBsp = new ConsumableBsp();
        $consumableBsp->setConsumableTransfer($transfer);
        $consumableBsp->setBsp($bsp);
        $this->entityManager->persist($consumableBsp);

        $this->entityManager->flush();
        $this->entityManager->commit();

        // Recalculer le stock pour le service source (sans exclusion)
        $this->transferStockManager->recalculateAllStocks($transfer->getConsumable()->getId(), $serviceSourceId);

        // Notifications
        $recipient = $bsp->getBeneficiaire() ?: $this->resolveRecipient($transfer);
        if ($recipient) {
            $this->notificationService->notify(
                $recipient,
                Notification::SUBJECT_CONSUMABLE_TRANSFER,
                $transfer->getId(),
                Notification::TYPE_CREATED,
                'Nouveau BSP',
                sprintf('Un bon de sortie provisoire pour "%s" (%s) vous concerne.', $transfer->getConsumable()?->getNom() ?? '—', $transfer->getQuantite()),
                $currentUser
            );
        }

        return $transfer;
    } catch (\Exception $e) {
        $this->entityManager->rollback();
        throw $e;
    }
}

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function createDirectTransfer(array $payload, array $documents = [], array $documentLabels = [], ?User $currentUser = null): ConsumableTransfer
{
    $this->entityManager->beginTransaction();

    try {
        // Extraire les données du payload
        $consumableId = (int) $payload['consumable_id'];
        $serviceDestinationId = (int) $payload['service_destination_id'];
        $quantite = (string) $payload['quantite'];
        $observations = $payload['observations'] ?? null;

        $serviceSourceId = $this->resolveServiceSourceId($payload, $currentUser, $consumableId);

        // Vérifier si le service source possède ce consommable
        $stockSource = $this->transferStockManager->getCurrentStock($consumableId, $serviceSourceId);
        if ($stockSource <= 0) {
            throw new \Exception('Le service source ne possède pas ce consomptible');
        }

        // Créer le transfert (1 seule ligne)
        $transfer = $this->transferStockManager->createTransfer(
            $consumableId,
            $serviceSourceId,
            $serviceDestinationId,
            $quantite,
            $observations,
            $documents,
            $documentLabels
        );

        // Recalculer le stock pour les DEUX services (sans exclusion)
        $this->transferStockManager->recalculateAllStocks($transfer->getConsumable()->getId(), $serviceSourceId);
        $this->transferStockManager->recalculateAllStocks($transfer->getConsumable()->getId(), $serviceDestinationId);

        // Notifications
        $recipient = $this->resolveRecipient($transfer);
        if ($recipient) {
            $this->notificationService->notify(
                $recipient,
                Notification::SUBJECT_CONSUMABLE_TRANSFER,
                $transfer->getId(),
                Notification::TYPE_CREATED,
                'Nouveau transfert de consomptible',
                sprintf('Un transfert de "%s" (%s) vous a été destiné.', $transfer->getConsumable()?->getNom() ?? '—', $transfer->getQuantite()),
                $currentUser
            );
        }

        $this->entityManager->commit();

        return $transfer;
    } catch (\Exception $e) {
        $this->entityManager->rollback();
        throw $e;
    }
}

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function update(ConsumableTransfer $transfer, array $payload, array $documents = [], array $documentLabels = []): ConsumableTransfer
    {
        $previousConsumable = $transfer->getConsumable();

        // Le type et le statut ne peuvent pas être modifiés
        unset($payload['type']);
        unset($payload['statut']);

        // Service destination
        if (array_key_exists('service_destination_id', $payload)) {
            if ($payload['service_destination_id'] === null || $payload['service_destination_id'] === '') {
                $transfer->setServiceDestination(null);
            } else {
                $service = $this->serviceRepository->getServiceById((int) $payload['service_destination_id']);
                if (!$service) {
                    throw new ValidationFailedException(['service_destination_id' => "Le service avec l'ID {$payload['service_destination_id']} n'existe pas."]);
                }
                $transfer->setServiceDestination($service);
            }
            unset($payload['service_destination_id']);
        }

        // Consomptible
        if (array_key_exists('consumable_id', $payload)) {
            if ($payload['consumable_id'] === null || $payload['consumable_id'] === '') {
                throw new ValidationFailedException(['consumable_id' => "Le consomptible ne peut pas être null."]);
            } else {
                $consumable = $this->consumableRepository->getActiveById((int) $payload['consumable_id']);
                if (!$consumable) {
                    throw new ValidationFailedException(['consumable_id' => "Le consomptible avec l'ID {$payload['consumable_id']} n'existe pas."]);
                }
                $transfer->setConsumable($consumable);
            }
            unset($payload['consumable_id']);
        }

        // Quantité
        if (array_key_exists('quantite', $payload)) {
            if ($payload['quantite'] === null || $payload['quantite'] === '') {
                throw new ValidationFailedException(['quantite' => "La quantité ne peut pas être null."]);
            } else {
                $transfer->setQuantite((string) $payload['quantite']);
            }
            unset($payload['quantite']);
        }

        // Date de transfert
        if (array_key_exists('dateTransfert', $payload)) {
            if ($payload['dateTransfert'] === null || $payload['dateTransfert'] === '') {
                $transfer->setDateTransfert(null);
            } else {
                $transfer->setDateTransfert(new \DateTime($payload['dateTransfert']));
            }
            unset($payload['dateTransfert']);
        }

        // Appliquer les autres champs
        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $setter = 'set' . ucfirst($key);
            if (method_exists($transfer, $setter)) {
                $transfer->$setter($value);
            }
        }

        // Gérer les pièces jointes selon le type
        if ($transfer->getType() === ConsumableTransfer::TYPE_BSP) {
            // Pour BSP, les pièces jointes vont sur le BSP lié
            if ($transfer->getConsumableBsp()) {
                $bsp = $transfer->getConsumableBsp()->getBsp();
                $this->attachBspFiles($bsp, $documents, $documentLabels);
            }
        } else {
            // Pour transfert direct, les pièces jointes vont sur le transfert
            $this->attachTransferFiles($transfer, $documents, $documentLabels);
        }

        $this->validateTransfer($transfer);
        $this->consumableTransferRepository->save($transfer);

        $this->stockManager->recalculateAndPersist($transfer->getConsumable());
        if ($previousConsumable && $previousConsumable->getId() !== $transfer->getConsumable()?->getId()) {
            $this->stockManager->recalculateAndPersist($previousConsumable);
        }

        return $transfer;
    }

    private function resolveServiceSourceId(array $payload, ?User $currentUser, int $consumableId): int
    {
        if (!$currentUser) {
            throw new ValidationFailedException(['authentication' => 'Un utilisateur connecté est obligatoire pour effectuer un transfert.']);
        }

        $requestedSourceId = $payload['service_source_id'] ?? null;
        if ($requestedSourceId !== null && $requestedSourceId !== '') {
            if (!$this->accessChecker->isAdmin($currentUser)) {
                throw new ValidationFailedException(['service_source_id' => 'Seul un administrateur peut choisir le service source.']);
            }

            $sourceId = (int) $requestedSourceId;
            if (!$this->serviceRepository->getServiceById($sourceId)) {
                throw new ValidationFailedException(['service_source_id' => "Le service avec l'ID {$sourceId} n'existe pas."]);
            }

            return $sourceId;
        }

        if ($this->accessChecker->isAdmin($currentUser)) {
            $consumable = $this->consumableRepository->getActiveById($consumableId);
            $ownerServiceId = $consumable?->getService()?->getId();
            if ($ownerServiceId !== null) {
                return $ownerServiceId;
            }
        }

        $serviceId = $currentUser->getService()?->getId();
        if ($serviceId === null) {
            throw new ValidationFailedException(['service_source_id' => 'Votre utilisateur doit être rattaché à un service pour effectuer un transfert.']);
        }

        return $serviceId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyTransferPayload(ConsumableTransfer $transfer, array $payload): void
    {
        // Consomptible (obligatoire)
        if (!array_key_exists('consumable_id', $payload) || $payload['consumable_id'] === null) {
            throw new ValidationFailedException(['consumable_id' => "Le consomptible est obligatoire."]);
        }
        $consumable = $this->consumableRepository->getActiveById((int) $payload['consumable_id']);
        if (!$consumable) {
            throw new ValidationFailedException(['consumable_id' => "Le consomptible avec l'ID {$payload['consumable_id']} n'existe pas."]);
        }
        $transfer->setConsumable($consumable);
        unset($payload['consumable_id']);

        // Service destination (obligatoire)
        if (!array_key_exists('service_destination_id', $payload) || $payload['service_destination_id'] === null) {
            throw new ValidationFailedException(['service_destination_id' => "Le service de destination est obligatoire."]);
        }
        $service = $this->serviceRepository->getServiceById((int) $payload['service_destination_id']);
        if (!$service) {
            throw new ValidationFailedException(['service_destination_id' => "Le service avec l'ID {$payload['service_destination_id']} n'existe pas."]);
        }
        $transfer->setServiceDestination($service);
        unset($payload['service_destination_id']);

        // Quantité (obligatoire)
        if (!array_key_exists('quantite', $payload) || $payload['quantite'] === null || $payload['quantite'] === '') {
            throw new ValidationFailedException(['quantite' => "La quantité est obligatoire."]);
        }
        $transfer->setQuantite((string) $payload['quantite']);
        unset($payload['quantite']);

        // Date de transfert (optionnel, défaut date du jour)
        if (!array_key_exists('dateTransfert', $payload) || $payload['dateTransfert'] === null || $payload['dateTransfert'] === '') {
            // Déjà géré par PrePersist
        } else {
            $transfer->setDateTransfert(new \DateTime($payload['dateTransfert']));
            unset($payload['dateTransfert']);
        }

        // Appliquer les autres champs
        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $setter = 'set' . ucfirst($key);
            if (method_exists($transfer, $setter)) {
                $transfer->$setter($value);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyBspPayload(Bsp $bsp, array $payload): void
    {
        // Service (optionnel)
        if (array_key_exists('service_id', $payload) && $payload['service_id'] !== null) {
            $service = $this->serviceRepository->find($payload['service_id']);
            if (!$service) {
                throw new ResourceNotFoundException('Service introuvable.');
            }
            $bsp->setService($service);
        }

        // Bénéficiaire (optionnel)
        if (array_key_exists('beneficiaire_id', $payload) && $payload['beneficiaire_id'] !== null) {
            $beneficiaire = $this->userRepository->find($payload['beneficiaire_id']);
            if (!$beneficiaire) {
                throw new ResourceNotFoundException('Bénéficiaire introuvable.');
            }
            $bsp->setBeneficiaire($beneficiaire);
        }

        // Quantité demandée (optionnel)
        if (array_key_exists('quantiteDemandee', $payload) && $payload['quantiteDemandee'] !== null && $payload['quantiteDemandee'] !== '') {
            $bsp->setQuantiteDemandee((int) $payload['quantiteDemandee']);
        }

        // Quantité accordée (optionnel)
        if (array_key_exists('quantiteAccordee', $payload) && $payload['quantiteAccordee'] !== null && $payload['quantiteAccordee'] !== '') {
            $bsp->setQuantiteAccordee((int) $payload['quantiteAccordee']);
        }

        // Quantité servie (optionnel)
        if (array_key_exists('quantiteServie', $payload) && $payload['quantiteServie'] !== null && $payload['quantiteServie'] !== '') {
            $bsp->setQuantiteServie((int) $payload['quantiteServie']);
        }

        // Date d'établissement (optionnel)
        if (array_key_exists('dateEtablissement', $payload) && $payload['dateEtablissement'] !== null && $payload['dateEtablissement'] !== '') {
            $bsp->setDateEtablissement(new \DateTime($payload['dateEtablissement']));
        }

        // Observations (optionnel)
        if (array_key_exists('observations', $payload) && $payload['observations'] !== null && $payload['observations'] !== '') {
            $bsp->setObservations($payload['observations']);
        }
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function attachBspFiles(Bsp $bsp, array $documents, array $documentLabels): void
    {
        foreach ($documents as $index => $file) {
            $nom = $documentLabels[$index] ?? $file->getClientOriginalName();
            $pieceJointe = $this->fileUploadService->upload($file, FileUploadService::KIND_DOCUMENT, $nom);
            
            $bsp->addPieceJointe($pieceJointe);
        }
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function attachTransferFiles(ConsumableTransfer $transfer, array $documents, array $documentLabels): void
    {
        foreach ($documents as $index => $file) {
            $nom = $documentLabels[$index] ?? $file->getClientOriginalName();
            $pieceJointe = $this->fileUploadService->upload($file, FileUploadService::KIND_DOCUMENT, $nom);
            
            $transfer->addPieceJointe($pieceJointe);
        }
    }

    /**
     * Rejette le transfert BSP si le stock disponible du consomptible est nul/épuisé ou
     * inférieur à la quantité demandée.
     */
    private function assertStockAvailable(ConsumableTransfer $transfer): void
    {
        $consumable = $transfer->getConsumable();
        if (!$consumable) {
            return;
        }

        $stockActuel = $this->consumableRepository->getStockActuel((int) $consumable->getId());
        $quantiteDemandee = (float) $transfer->getQuantite();

        if ($stockActuel <= 0 || $quantiteDemandee > $stockActuel) {
            throw new ValidationFailedException(
                ['quantite' => "Impossible d'effectuer le transfert : le stock est épuisé."],
                "Impossible d'effectuer le transfert : le stock est épuisé."
            );
        }
    }

    private function validateTransfer(ConsumableTransfer $transfer): void
    {
        $errors = $this->validator->validate($transfer);
        if (count($errors) > 0) {
            $violations = [];
            foreach ($errors as $error) {
                $violations[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($violations);
        }
    }

    private function validateBsp(Bsp $bsp): void
    {
        $errors = $this->validator->validate($bsp);
        if (count($errors) > 0) {
            $violations = [];
            foreach ($errors as $error) {
                $violations[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($violations);
        }
    }

    /**
     * Destinataire réel du transfert : le bénéficiaire du BSP lié si renseigné, sinon
     * l'utilisateur rattaché au service de destination. Utilisé pour notifier à la création
     * et pour vérifier l'éligibilité d'un utilisateur à accuser réception.
     */
    public function resolveRecipient(ConsumableTransfer $transfer): ?User
    {
        $bsp = $transfer->getConsumableBsp()?->getBsp();
        if ($bsp?->getBeneficiaire()) {
            return $bsp->getBeneficiaire();
        }

        if ($transfer->getServiceDestination()) {
            return $this->userRepository->findActiveByServiceId($transfer->getServiceDestination()->getId());
        }

        return null;
    }

    public function removePieceJointe(ConsumableTransfer $transfer, int $pieceJointeId): void
    {
        $pieceJointe = $this->pieceJointeRepository->find($pieceJointeId);
        if (!$pieceJointe) {
            throw new ResourceNotFoundException('La pièce jointe demandée est introuvable.');
        }

        $transfer->removePieceJointe($pieceJointe);
        $this->consumableTransferRepository->save($transfer);
    }
}
