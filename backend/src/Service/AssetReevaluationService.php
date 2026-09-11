<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetReevaluation;
use App\Entity\PieceJointe;
use App\Entity\Service;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\AssetReevaluationRepository;
use App\Repository\AssetRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AssetReevaluationService
{
    public function __construct(
        private readonly AssetReevaluationRepository $reevaluationRepository,
        private readonly AssetRepository $assetRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly FileUploadService $fileUploadService
    ) {
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
    ): AssetReevaluation {
        $reevaluation = new AssetReevaluation();

        // Définir l'utilisateur créateur si fourni
        if ($currentUser !== null) {
            $reevaluation->setCreatedBy($currentUser);
        }

        $this->applyPayload($reevaluation, $payload);
        $this->attachFiles($reevaluation, $documents, $documentLabels);

        $this->validate($reevaluation);
        $this->reevaluationRepository->save($reevaluation);

        return $reevaluation;
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function update(
        AssetReevaluation $reevaluation,
        array $payload,
        array $documents = [],
        array $documentLabels = [],
        ?User $currentUser = null
    ): AssetReevaluation {
        $this->applyPayload($reevaluation, $payload);
        $this->attachFiles($reevaluation, $documents, $documentLabels);

        // Définir l'utilisateur modificateur si fourni
        if ($currentUser !== null) {
            $reevaluation->setUpdatedBy($currentUser);
        }

        $this->validate($reevaluation);
        $this->reevaluationRepository->save($reevaluation);

        return $reevaluation;
    }

    public function delete(AssetReevaluation $reevaluation): void
    {
        $this->entityManager->remove($reevaluation);
        $this->entityManager->flush();
    }

    public function deletePieceJointe(AssetReevaluation $reevaluation, PieceJointe $pieceJointe): void
    {
        $reevaluation->removePieceJointe($pieceJointe);
        $this->entityManager->flush();

        $this->entityManager->remove($pieceJointe);
        $this->entityManager->flush();

        $this->fileUploadService->deleteFile($pieceJointe->getChemin());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(AssetReevaluation $reevaluation, array $payload): void
    {
        if (isset($payload['asset_ids']) && is_array($payload['asset_ids'])) {
            foreach ($payload['asset_ids'] as $assetId) {
                $asset = $this->assetRepository->find($assetId);
                if ($asset) {
                    $reevaluation->addAsset($asset);
                    // ✅ Récupérer la valeur actuelle du bien si elle n'est pas déjà définie
                    if (!isset($payload['valeurActuelle']) && $asset->getValeur() !== null) {
                        $reevaluation->setValeurActuelle($asset->getValeur());
                    }
                }
            }
        }

        if (isset($payload['service_id'])) {
            $service = $this->serviceRepository->find($payload['service_id']);
            if ($service) {
                $reevaluation->setService($service);
            }
        }

         // ✅ Permettre de surcharger manuellement la valeur actuelle
        if (isset($payload['valeurActuelle'])) {
            $reevaluation->setValeurActuelle($payload['valeurActuelle']);
        }

        if (isset($payload['valeurActuelle'])) {
            $reevaluation->setValeurActuelle($payload['valeurActuelle']);
        }

        if (isset($payload['nouvelleValeur'])) {
            $reevaluation->setNouvelleValeur($payload['nouvelleValeur']);
            
            // ✅ Mettre à jour la valeur des biens associés avec la nouvelle valeur
            foreach ($reevaluation->getAssets() as $asset) {
                $asset->setValeur((string) $payload['nouvelleValeur']);
            }
        }

        if (isset($payload['methodeEvaluation'])) {
            $reevaluation->setMethodeEvaluation($payload['methodeEvaluation']);
        }

        if (isset($payload['dateReevaluation']) && !empty($payload['dateReevaluation'])) {
            $reevaluation->setDateReevaluation(new \DateTime($payload['dateReevaluation']));
        }

        if (isset($payload['motif'])) {
            $reevaluation->setMotif($payload['motif']);
        }

        if (isset($payload['observations'])) {
            $reevaluation->setObservations($payload['observations']);
        }
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function attachFiles(AssetReevaluation $reevaluation, array $documents, array $documentLabels): void
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
            $reevaluation->addPieceJointe($piece);
        }
    }

    private function validate(AssetReevaluation $reevaluation): void
    {
        $errors = $this->validator->validate($reevaluation);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($errorMessages);
        }
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
