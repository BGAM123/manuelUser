<?php

namespace App\Service;

use App\Entity\ConsumableEntry;
use App\Entity\Consumable;
use App\Entity\Service;
use App\Entity\PieceJointe;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\ConsumableEntryRepository;
use App\Repository\ConsumableRepository;
use App\Repository\ServiceRepository;
use App\Repository\PieceJointeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ConsumableEntryService
{
    public function __construct(
        private readonly ConsumableEntryRepository $consumableEntryRepository,
        private readonly ConsumableRepository $consumableRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly PieceJointeRepository $pieceJointeRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConsumableStockManager $stockManager,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function create(array $payload, array $documents = [], array $documentLabels = []): ConsumableEntry
    {
        $entry = new ConsumableEntry();
        $now = new \DateTimeImmutable();
        $entry->setCreatedAt($now);
        $entry->setUpdatedAt($now);

        // Consomptible (obligatoire)
        if (!array_key_exists('consumable_id', $payload) || $payload['consumable_id'] === null) {
            throw new ValidationFailedException(['consumable_id' => "Le consomptible est obligatoire."]);
        }
        $consumable = $this->consumableRepository->getActiveById((int) $payload['consumable_id']);
        if (!$consumable) {
            throw new ValidationFailedException(['consumable_id' => "Le consomptible avec l'ID {$payload['consumable_id']} n'existe pas."]);
        }
        $entry->setConsumable($consumable);
        unset($payload['consumable_id']);

        // Service (obligatoire)
        if (!array_key_exists('service_id', $payload) || $payload['service_id'] === null) {
            throw new ValidationFailedException(['service_id' => "Le service est obligatoire."]);
        }
        $service = $this->serviceRepository->getActiveById((int) $payload['service_id']);
        if (!$service) {
            throw new ValidationFailedException(['service_id' => "Le service avec l'ID {$payload['service_id']} n'existe pas."]);
        }
        $entry->setService($service);
        unset($payload['service_id']);

        // Quantité (obligatoire)
        if (!array_key_exists('quantite', $payload) || $payload['quantite'] === null || $payload['quantite'] === '') {
            throw new ValidationFailedException(['quantite' => "La quantité est obligatoire."]);
        }
        $entry->setQuantite((string) $payload['quantite']);
        unset($payload['quantite']);

        // Date d'entrée (optionnel, défaut date du jour)
        if (!array_key_exists('dateEntree', $payload) || $payload['dateEntree'] === null || $payload['dateEntree'] === '') {
            // Déjà géré par PrePersist
        } else {
            $entry->setDateEntree(new \DateTime($payload['dateEntree']));
            unset($payload['dateEntree']);
        }

        // Appliquer les autres champs
        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $setter = 'set' . ucfirst($key);
            if (method_exists($entry, $setter)) {
                $entry->$setter($value);
            }
        }

        // Gérer les pièces jointes (factures)
        $this->attachFiles($entry, $documents, $documentLabels);

        $this->assertValid($entry);
        $this->consumableEntryRepository->save($entry);
        $this->stockManager->recalculateAndPersist($entry->getConsumable());

        return $entry;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function update(ConsumableEntry $entry, array $payload, array $documents = [], array $documentLabels = []): ConsumableEntry
    {
        $previousConsumable = $entry->getConsumable();

        // Consomptible (modifiable)
        if (array_key_exists('consumable_id', $payload)) {
            if ($payload['consumable_id'] === null || $payload['consumable_id'] === '') {
                throw new ValidationFailedException(['consumable_id' => "Le consomptible est obligatoire."]);
            }
            $consumable = $this->consumableRepository->getActiveById((int) $payload['consumable_id']);
            if (!$consumable) {
                throw new ValidationFailedException(['consumable_id' => "Le consomptible avec l'ID {$payload['consumable_id']} n'existe pas."]);
            }
            $entry->setConsumable($consumable);
            unset($payload['consumable_id']);
        }

        // Service (modifiable)
        if (array_key_exists('service_id', $payload)) {
            if ($payload['service_id'] === null || $payload['service_id'] === '') {
                throw new ValidationFailedException(['service_id' => "Le service est obligatoire."]);
            }
            $service = $this->serviceRepository->getActiveById((int) $payload['service_id']);
            if (!$service) {
                throw new ValidationFailedException(['service_id' => "Le service avec l'ID {$payload['service_id']} n'existe pas."]);
            }
            $entry->setService($service);
            unset($payload['service_id']);
        }

        // Quantité (modifiable)
        if (array_key_exists('quantite', $payload)) {
            if ($payload['quantite'] === null || $payload['quantite'] === '') {
                throw new ValidationFailedException(['quantite' => "La quantité est obligatoire."]);
            }
            $entry->setQuantite((string) $payload['quantite']);
            unset($payload['quantite']);
        }

        // Date d'entrée (modifiable)
        if (array_key_exists('dateEntree', $payload)) {
            if ($payload['dateEntree'] === null || $payload['dateEntree'] === '') {
                $entry->setDateEntree(null);
            } else {
                $entry->setDateEntree(new \DateTime($payload['dateEntree']));
            }
            unset($payload['dateEntree']);
        }

        // Appliquer les autres champs
        foreach ($payload as $key => $value) {
            $setter = 'set' . ucfirst($key);
            if (method_exists($entry, $setter)) {
                $entry->$setter($value);
            }
        }

        // Gérer les pièces jointes
        $this->attachFiles($entry, $documents, $documentLabels);

        $entry->setUpdatedAt(new \DateTimeImmutable());
        $this->assertValid($entry);
        $this->consumableEntryRepository->save($entry);

        $this->stockManager->recalculateAndPersist($entry->getConsumable());
        if ($previousConsumable && $previousConsumable->getId() !== $entry->getConsumable()?->getId()) {
            $this->stockManager->recalculateAndPersist($previousConsumable);
        }

        return $entry;
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function attachFiles(ConsumableEntry $entry, array $documents, array $documentLabels): void
    {
        foreach ($documents as $index => $file) {
            $nom = $documentLabels[$index] ?? $file->getClientOriginalName();
            $pieceJointe = $this->fileUploadService->upload($file, FileUploadService::KIND_DOCUMENT, $nom);
            
            $entry->addPieceJointe($pieceJointe);
        }
    }

    private function assertValid(ConsumableEntry $entry): void
    {
        $errors = $this->validator->validate($entry);
        if (count($errors) > 0) {
            $violations = [];
            foreach ($errors as $error) {
                $violations[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($violations);
        }
    }

    public function removePieceJointe(ConsumableEntry $entry, int $pieceJointeId): void
    {
        $pieceJointe = $this->pieceJointeRepository->find($pieceJointeId);
        if (!$pieceJointe) {
            throw new ResourceNotFoundException('La pièce jointe demandée est introuvable.');
        }

        $entry->removePieceJointe($pieceJointe);
        $this->consumableEntryRepository->save($entry);
    }
}
