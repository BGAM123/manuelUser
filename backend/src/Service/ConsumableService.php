<?php

namespace App\Service;

use App\Entity\Consumable;
use App\Entity\Category;
use App\Entity\AssetType;
use App\Entity\AssetSubType;
use App\Entity\PieceJointe;
use App\Entity\ConsumableEntry;
use App\Entity\ConsumableTransfer;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\ConsumableRepository;
use App\Repository\CategoryRepository;
use App\Repository\AssetTypeRepository;
use App\Repository\AssetSubTypeRepository;
use App\Repository\PieceJointeRepository;
use App\Repository\ServiceRepository;
use App\Repository\ConsumableEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ConsumableService
{
    public function __construct(
        private readonly ConsumableRepository $consumableRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly AssetTypeRepository $assetTypeRepository,
        private readonly AssetSubTypeRepository $assetSubTypeRepository,
        private readonly PieceJointeRepository $pieceJointeRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly ConsumableEntryRepository $consumableEntryRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConsumableTransferStockManager $stockManager,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function create(array $payload, array $documents = [], array $documentLabels = []): Consumable
    {
        $consumable = new Consumable();
        $now = new \DateTimeImmutable();
        $consumable->setCreatedAt($now);
        $consumable->setUpdatedAt($now);

        // Quantité de départ
        if (!array_key_exists('quantite', $payload) || $payload['quantite'] === null || $payload['quantite'] === '') {
            $consumable->setQuantite('0');
        } else {
            $consumable->setQuantite((string) $payload['quantite']);
            unset($payload['quantite']);
        }

        // Prix initial (optionnel)
        if (array_key_exists('prixInitial', $payload) && $payload['prixInitial'] !== null && $payload['prixInitial'] !== '') {
            $consumable->setPrixInitial((string) $payload['prixInitial']);
            unset($payload['prixInitial']);
        }

        // Prix total (optionnel)
        if (array_key_exists('prixTotal', $payload) && $payload['prixTotal'] !== null && $payload['prixTotal'] !== '') {
            $consumable->setPrixTotal((string) $payload['prixTotal']);
            unset($payload['prixTotal']);
        }

        // Nom (obligatoire)
        if (array_key_exists('nom', $payload)) {
            if ($payload['nom'] === null || $payload['nom'] === '') {
                throw new ValidationFailedException(['nom' => 'Le nom du consomptible est obligatoire.']);
            }
            $consumable->setNom((string) $payload['nom']);
            unset($payload['nom']);
        } else {
            throw new ValidationFailedException(['nom' => 'Le nom du consomptible est obligatoire.']);
        }

        // Description (optionnel)
        if (array_key_exists('description', $payload)) {
            if ($payload['description'] === null || $payload['description'] === '') {
                $consumable->setDescription(null);
            } else {
                $consumable->setDescription((string) $payload['description']);
            }
            unset($payload['description']);
        }

        // Description (optionnel)
        if (array_key_exists('unite_mesure', $payload)) {
            if ($payload['unite_mesure'] === null || $payload['unite_mesure'] === '') {
                $consumable->setUnite_mesure(null);
            } else {
                $consumable->setUnite_mesure((string) $payload['unite_mesure']);
            }
            unset($payload['unite_mesure']);
        }

        // Catégorie (optionnel)
        if (array_key_exists('category_id', $payload) && $payload['category_id'] !== null && $payload['category_id'] !== '') {
            $category = $this->categoryRepository->getActiveCategoryById((int) $payload['category_id']);
            if (!$category) {
                throw new ValidationFailedException(['category_id' => "La catégorie avec l'ID {$payload['category_id']} n'existe pas."]);
            }
            $consumable->setCategory($category);
            unset($payload['category_id']);
        }

        // Type de bien (optionnel)
        if (array_key_exists('asset_type_id', $payload) && $payload['asset_type_id'] !== null && $payload['asset_type_id'] !== '') {
            $assetType = $this->assetTypeRepository->getActiveAssetTypeById((int) $payload['asset_type_id']);
            if (!$assetType) {
                throw new ValidationFailedException(['asset_type_id' => "Le type de bien avec l'ID {$payload['asset_type_id']} n'existe pas."]);
            }
            $consumable->setAssetType($assetType);
            unset($payload['asset_type_id']);
        }

        // Sous-type de bien (optionnel)
        if (array_key_exists('asset_sub_type_id', $payload) && $payload['asset_sub_type_id'] !== null && $payload['asset_sub_type_id'] !== '') {
            $assetSubType = $this->assetSubTypeRepository->getActiveAssetSubTypeById((int) $payload['asset_sub_type_id']);
            if (!$assetSubType) {
                throw new ValidationFailedException(['asset_sub_type_id' => "Le sous-type de bien avec l'ID {$payload['asset_sub_type_id']} n'existe pas."]);
            }
            $consumable->setAssetSubType($assetSubType);
            unset($payload['asset_sub_type_id']);
        }

        // Service (optionnel)
        $service = null;
        if (array_key_exists('service_id', $payload) && $payload['service_id'] !== null && $payload['service_id'] !== '') {
            $service = $this->serviceRepository->getServiceById((int) $payload['service_id']);
            if (!$service) {
                throw new ValidationFailedException(['service_id' => "Le service avec l'ID {$payload['service_id']} n'existe pas."]);
            }
            $consumable->setService($service);
            unset($payload['service_id']);
        }

        // Appliquer les autres champs
        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $setter = 'set' . ucfirst($key);
            if (method_exists($consumable, $setter)) {
                $consumable->$setter($value);
            }
        }

        // Gérer les pièces jointes
        $this->attachFiles($consumable, $documents, $documentLabels);

        $this->assertValid($consumable);
        $this->consumableRepository->save($consumable);

        // 🔥 CRÉER L'ENTRÉE ET LE TRANSFERT INITIAL
        if ($service && (float) $consumable->getQuantite() > 0) {
            $quantite = (string) $consumable->getQuantite();
            $serviceId = $service->getId();

            // 1. Créer l'entrée (ConsumableEntry) - pour l'historique
            $this->createConsumableEntry($consumable, $service, $quantite);

            // 2. Créer le transfert initial (ConsumableTransfer) - pour le stock
            try {
                $this->stockManager->createInitialTransfer(
                    $consumable,
                    $serviceId,
                    $quantite
                );
            } catch (\Exception $e) {
                error_log('Erreur lors de la création du transfert initial: ' . $e->getMessage());
            }
        }

        return $consumable;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function update(Consumable $consumable, array $payload, array $documents = [], array $documentLabels = []): Consumable
    {
        // Quantité (maintenant modifiable)
        if (array_key_exists('quantite', $payload) && $payload['quantite'] !== null && $payload['quantite'] !== '') {
            $consumable->setQuantite((string) $payload['quantite']);
            unset($payload['quantite']);
        }

        // Prix initial (modifiable)
        if (array_key_exists('prixInitial', $payload)) {
            if ($payload['prixInitial'] === null || $payload['prixInitial'] === '') {
                $consumable->setPrixInitial(null);
            } else {
                $consumable->setPrixInitial((string) $payload['prixInitial']);
            }
            unset($payload['prixInitial']);
        }

        // Prix total (modifiable)
        if (array_key_exists('prixTotal', $payload)) {
            if ($payload['prixTotal'] === null || $payload['prixTotal'] === '') {
                $consumable->setPrixTotal(null);
            } else {
                $consumable->setPrixTotal((string) $payload['prixTotal']);
            }
            unset($payload['prixTotal']);
        }

        // Catégorie
        if (array_key_exists('category_id', $payload)) {
            if ($payload['category_id'] === null || $payload['category_id'] === '') {
                $consumable->setCategory(null);
            } else {
                $category = $this->categoryRepository->getActiveCategoryById((int) $payload['category_id']);
                if (!$category) {
                    throw new ValidationFailedException(['category_id' => "La catégorie avec l'ID {$payload['category_id']} n'existe pas."]);
                }
                $consumable->setCategory($category);
            }
            unset($payload['category_id']);
        }

        if (array_key_exists('unite_mesure', $payload)) {
            if ($payload['unite_mesure'] === null || $payload['unite_mesure'] === '') {
                $consumable->setUnite_mesure(null);
            } else {
                $consumable->setUnite_mesure((string) $payload['unite_mesure']);
            }
            unset($payload['unite_mesure']);
        }

        // Type de bien
        if (array_key_exists('asset_type_id', $payload)) {
            if ($payload['asset_type_id'] === null || $payload['asset_type_id'] === '') {
                $consumable->setAssetType(null);
            } else {
                $assetType = $this->assetTypeRepository->getActiveAssetTypeById((int) $payload['asset_type_id']);
                if (!$assetType) {
                    throw new ValidationFailedException(['asset_type_id' => "Le type de bien avec l'ID {$payload['asset_type_id']} n'existe pas."]);
                }
                $consumable->setAssetType($assetType);
            }
            unset($payload['asset_type_id']);
        }

        // Sous-type de bien
        if (array_key_exists('asset_sub_type_id', $payload)) {
            if ($payload['asset_sub_type_id'] === null || $payload['asset_sub_type_id'] === '') {
                $consumable->setAssetSubType(null);
            } else {
                $assetSubType = $this->assetSubTypeRepository->getActiveAssetSubTypeById((int) $payload['asset_sub_type_id']);
                if (!$assetSubType) {
                    throw new ValidationFailedException(['asset_sub_type_id' => "Le sous-type de bien avec l'ID {$payload['asset_sub_type_id']} n'existe pas."]);
                }
                $consumable->setAssetSubType($assetSubType);
            }
            unset($payload['asset_sub_type_id']);
        }

        // Service (optionnel)
        $newService = null;
        
        if (array_key_exists('service_id', $payload)) {
            if ($payload['service_id'] === null || $payload['service_id'] === '') {
                $consumable->setService(null);
            } else {
                $newService = $this->serviceRepository->getServiceById((int) $payload['service_id']);
                if (!$newService) {
                    throw new ValidationFailedException(['service_id' => "Le service avec l'ID {$payload['service_id']} n'existe pas."]);
                }
                $consumable->setService($newService);
            }
            unset($payload['service_id']);
        }

        // Appliquer les autres champs
        foreach ($payload as $key => $value) {
            $setter = 'set' . ucfirst($key);
            if (method_exists($consumable, $setter)) {
                $consumable->$setter($value);
            }
        }

        // Gérer les pièces jointes
        $this->attachFiles($consumable, $documents, $documentLabels);

        $consumable->setUpdatedAt(new \DateTimeImmutable());
        $this->assertValid($consumable);
        $this->consumableRepository->save($consumable);

        // 🔥 GÉRER L'ENTRÉE ET LE TRANSFERT INITIAL SI UN NOUVEAU SERVICE EST DÉFINI
        if ($newService && (float) $consumable->getQuantite() > 0) {
            $quantite = (string) $consumable->getQuantite();
            
            // 1. Créer l'entrée pour l'historique
            $this->createConsumableEntry($consumable, $newService, $quantite);

            // 2. Vérifier si un transfert initial existe déjà
            $existingInitial = $this->entityManager->createQueryBuilder()
                ->select('ct')
                ->from('App\Entity\ConsumableTransfer', 'ct')
                ->where('ct.consumable = :consumableId')
                ->andWhere('ct.serviceDestination = :serviceId')
                ->andWhere('ct.statut = :statut')
                ->andWhere('ct.isDelete = false')
                ->setParameter('consumableId', $consumable->getId())
                ->setParameter('serviceId', $newService->getId())
                ->setParameter('statut', 'INITIAL')
                ->getQuery()
                ->getOneOrNullResult();

            if (!$existingInitial) {
                // Créer un nouveau transfert initial
                try {
                    $this->stockManager->createInitialTransfer(
                        $consumable,
                        $newService->getId(),
                        $quantite
                    );
                } catch (\Exception $e) {
                    error_log('Erreur lors de la création du transfert initial: ' . $e->getMessage());
                }
            } else {
                // Mettre à jour le transfert initial existant : ce n'est pas un transfert
                // réel, la quantité transférée reste 0, seul le stock d'ouverture change.
                $existingInitial->setQuantite('0');
                $existingInitial->setStockActuel($quantite);
                $this->entityManager->flush();
            }
        }

        return $consumable;
    }

    /**
     * 🔥 CRÉER UNE ENTRÉE POUR L'HISTORIQUE
     */
    private function createConsumableEntry(Consumable $consumable, \App\Entity\Service $service, string $quantite): void
    {
        $consumableEntry = new ConsumableEntry();
        $now = new \DateTime();
        $consumableEntry->setConsumable($consumable);
        $consumableEntry->setService($service);
        $consumableEntry->setQuantite($quantite);
        $consumableEntry->setDateEntree($now);
        $consumableEntry->setCreatedAt($now);
        $consumableEntry->setUpdatedAt($now);

        $this->entityManager->persist($consumableEntry);
        $this->entityManager->flush();
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function attachFiles(Consumable $consumable, array $documents, array $documentLabels): void
    {
        foreach ($documents as $index => $file) {
            $nom = $documentLabels[$index] ?? $file->getClientOriginalName();
            $pieceJointe = $this->fileUploadService->upload($file, FileUploadService::KIND_DOCUMENT, $nom);
            
            $consumable->addPieceJointe($pieceJointe);
        }
    }

    private function assertValid(Consumable $consumable): void
    {
        $errors = $this->validator->validate($consumable);
        if (count($errors) > 0) {
            $violations = [];
            foreach ($errors as $error) {
                $violations[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($violations);
        }
    }

    public function removePieceJointe(Consumable $consumable, int $pieceJointeId): void
    {
        $pieceJointe = $this->pieceJointeRepository->find($pieceJointeId);
        if (!$pieceJointe) {
            throw new ResourceNotFoundException('La pièce jointe demandée est introuvable.');
        }

        $consumable->removePieceJointe($pieceJointe);
        $this->consumableRepository->save($consumable);
    }
}