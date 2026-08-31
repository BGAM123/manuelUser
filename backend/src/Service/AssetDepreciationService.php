<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetDepreciation;
use App\Entity\PieceJointe;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\AssetDepreciationRepository;
use App\Repository\AssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AssetDepreciationService
{
    public function __construct(
        private readonly AssetDepreciationRepository $depreciationRepository,
        private readonly AssetRepository $assetRepository,
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
    ): AssetDepreciation {
        $depreciation = new AssetDepreciation();

        // Définir l'utilisateur créateur si fourni
        if ($currentUser !== null) {
            $depreciation->setCreatedBy($currentUser);
        }

        $this->applyPayload($depreciation, $payload);
        $this->attachFiles($depreciation, $documents, $documentLabels);

        $this->validate($depreciation);
        $this->depreciationRepository->save($depreciation);

        return $depreciation;
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function update(
        AssetDepreciation $depreciation,
        array $payload,
        array $documents = [],
        array $documentLabels = [],
        ?User $currentUser = null
    ): AssetDepreciation {
        $this->applyPayload($depreciation, $payload);
        $this->attachFiles($depreciation, $documents, $documentLabels);

        // Définir l'utilisateur modificateur si fourni
        if ($currentUser !== null) {
            $depreciation->setUpdatedBy($currentUser);
        }

        $this->validate($depreciation);
        $this->depreciationRepository->save($depreciation);

        return $depreciation;
    }

    public function delete(AssetDepreciation $depreciation): void
    {
        $this->entityManager->remove($depreciation);
        $this->entityManager->flush();
    }

    public function deletePieceJointe(AssetDepreciation $depreciation, PieceJointe $pieceJointe): void
    {
        $depreciation->removePieceJointe($pieceJointe);
        $this->entityManager->flush();

        $this->entityManager->remove($pieceJointe);
        $this->entityManager->flush();

        $this->fileUploadService->deleteFile($pieceJointe->getChemin());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(AssetDepreciation $depreciation, array $payload): void
    {
        if (isset($payload['asset_ids']) && is_array($payload['asset_ids'])) {
            foreach ($payload['asset_ids'] as $assetId) {
                $asset = $this->assetRepository->find($assetId);
                if ($asset) {
                    $depreciation->addAsset($asset);
                }
            }
        }

        if (isset($payload['typeDepreciation'])) {
            $depreciation->setTypeDepreciation($payload['typeDepreciation']);
        }

        if (isset($payload['methodeAmortissement'])) {
            $depreciation->setMethodeAmortissement($payload['methodeAmortissement']);
        }

        if (isset($payload['dureeVie'])) {
            $depreciation->setDureeVie((int) $payload['dureeVie']);
        }

        if (isset($payload['valeurActuelle'])) {
            $depreciation->setValeurActuelle($payload['valeurActuelle']);
            
            // ✅ Mettre à jour la valeur des biens associés avec la valeur actuelle
            foreach ($depreciation->getAssets() as $asset) {
                $asset->setValeur((string) $payload['montantDepreciation']);
            }
        }

        if (isset($payload['tauxDepreciation'])) {
            $depreciation->setTauxDepreciation($payload['tauxDepreciation']);
        }

        if (isset($payload['montantDepreciation'])) {
            $depreciation->setMontantDepreciation($payload['montantDepreciation']);
        }

        if (isset($payload['dateDepreciation']) && !empty($payload['dateDepreciation'])) {
            $depreciation->setDateDepreciation(new \DateTime($payload['dateDepreciation']));
        }

        if (isset($payload['motif'])) {
            $depreciation->setMotif($payload['motif']);
        }

        if (isset($payload['observations'])) {
            $depreciation->setObservations($payload['observations']);
        }
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function attachFiles(AssetDepreciation $depreciation, array $documents, array $documentLabels): void
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
            $depreciation->addPieceJointe($piece);
        }
    }

    private function validate(AssetDepreciation $depreciation): void
    {
        $errors = $this->validator->validate($depreciation);
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
