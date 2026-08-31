<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetReformRequest;
use App\Entity\EtatBien;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\AssetRepository;
use App\Repository\AssetReformRequestRepository;
use App\Repository\EtatBienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AssetReformService
{
    public function __construct(
        private readonly AssetReformRequestRepository $reformRequestRepository,
        private readonly AssetRepository $assetRepository,
        private readonly EtatBienRepository $etatBienRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly FileUploadService $fileUploadService
    ) {
    }

    /**
     * @param list<int> $assetIds
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     * @return array<int, AssetReformRequest> indexed by asset_id
     */
    public function createReformRequests(array $assetIds, array $documents = [], array $documentLabels = [], ?User $createdBy = null): array
    {
        if (empty($assetIds)) {
            throw new ValidationFailedException(['asset_ids' => 'Au moins un ID de bien est requis.']);
        }

        if (empty($documents)) {
            throw new ValidationFailedException(['piecesJointes' => 'Une pièce justificative est obligatoire pour demander la réforme des biens.']);
        }

        // Récupérer l'état "À RÉFORMER"
        $aReformerEtat = $this->etatBienRepository->findOneBy(['nom' => 'À RÉFORMER', 'isDelete' => false]);
        if (!$aReformerEtat) {
            throw new ResourceNotFoundException("L'état 'À RÉFORMER' n'existe pas. Veuillez le créer d'abord.");
        }

        // Récupérer l'état "RÉFORMÉ"
        $reformeEtat = $this->etatBienRepository->findOneBy(['nom' => 'RÉFORMÉ', 'isDelete' => false]);
        if (!$reformeEtat) {
            throw new ResourceNotFoundException("L'état 'RÉFORMÉ' n'existe pas. Veuillez le créer d'abord.");
        }

        $requests = [];
        $errors = [];

        foreach ($assetIds as $assetId) {
            try {
                $request = $this->createReformRequestForAsset($assetId, $aReformerEtat, $reformeEtat, $documents, $documentLabels, $createdBy);
                $requests[$assetId] = $request;
            } catch (ValidationFailedException $e) {
                $errors["asset_{$assetId}"] = $e->getErrors();
            } catch (ResourceNotFoundException $e) {
                $errors["asset_{$assetId}"] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            throw new ValidationFailedException($errors);
        }

        return $requests;
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function createReformRequestForAsset(
        int $assetId,
        EtatBien $aReformerEtat,
        EtatBien $reformeEtat,
        array $documents,
        array $documentLabels,
        ?User $createdBy
    ): AssetReformRequest {
        $asset = $this->assetRepository->find($assetId);
        if (!$asset) {
            throw new ResourceNotFoundException("Le bien #{$assetId} n'existe pas.");
        }

        // Vérifier si le bien est déjà RÉFORMÉ
        foreach ($asset->getEtatBiens() as $etat) {
            if ($etat->getNom() === 'RÉFORMÉ' && !$etat->isDelete()) {
                throw new ValidationFailedException(['asset' => "Ce bien est déjà réformé."]);
            }
        }

        // Vérifier si une demande en attente existe déjà
        $existingRequest = $this->reformRequestRepository->findPendingByAssetId($assetId);
        if ($existingRequest) {
            throw new ValidationFailedException(['asset' => 'Une demande de réforme est déjà en attente pour ce bien.']);
        }

        // Sauvegarder l'état précédent avant de passer à À RÉFORMER
        $previousEtats = [];
        foreach ($asset->getEtatBiens() as $etat) {
            if (!$etat->isDelete()) {
                $previousEtats[] = $etat->getNom();
            }
        }
        $previousEtatString = implode(', ', $previousEtats);

        // Créer la demande
        $request = new AssetReformRequest();
        $request->setAsset($asset);
        $request->setStatut(AssetReformRequest::STATUT_EN_ATTENTE);
        $request->setPreviousEtat($previousEtatString ?: null);
        $request->setCreatedBy($createdBy);

        // Attacher les pièces jointes
        $documentLabels = UploadedFilesNormalizer::parseLabelList($documentLabels);
        foreach (array_values($documents) as $index => $document) {
            if (!$document instanceof UploadedFile) {
                continue;
            }
            if (UPLOAD_ERR_NO_FILE === $document->getError()) {
                continue;
            }
            if (!$document->isValid()) {
                throw new ValidationFailedException(['piecesJointes' => 'Fichier invalide : ' . $document->getErrorMessage()]);
            }

            $label = $documentLabels[$index] ?? null;
            if (null === $label || '' === trim((string) $label)) {
                $label = $document->getClientOriginalName();
            }

            $piece = $this->fileUploadService->upload(
                $document,
                FileUploadService::KIND_DOCUMENT,
                (string) $label,
                true
            );
            $request->addPieceJointe($piece);
        }

        $this->validate($request);
        $this->reformRequestRepository->save($request);

        // Mettre le bien à l'état À RÉFORMER
        $asset->syncEtatBiens([$aReformerEtat]);
        $this->assetRepository->save($asset);

        return $request;
    }

    /**
     * @param list<int> $assetIds
     * @return array<int, array> indexed by asset_id
     */
    public function validateReformRequests(array $assetIds, string $statut, User $validatedBy): array
    {
        if (empty($assetIds)) {
            throw new ValidationFailedException(['asset_ids' => 'Au moins un ID de bien est requis.']);
        }

        if (!in_array($statut, [AssetReformRequest::STATUT_VALIDEE, AssetReformRequest::STATUT_REJETEE], true)) {
            throw new ValidationFailedException(['statut' => 'Statut invalide. Valeurs autorisées: VALIDEE, REJETEE']);
        }

        // Récupérer l'état "RÉFORMÉ" pour validation
        $reformeEtat = null;
        if ($statut === AssetReformRequest::STATUT_VALIDEE) {
            $reformeEtat = $this->etatBienRepository->findOneBy(['nom' => 'RÉFORMÉ', 'isDelete' => false]);
            if (!$reformeEtat) {
                throw new ResourceNotFoundException("L'état 'RÉFORMÉ' n'existe pas.");
            }
        }

        // Récupérer les demandes en attente
        $pendingRequests = $this->reformRequestRepository->findPendingByAssetIds($assetIds);
        $results = [];
        $errors = [];

        foreach ($assetIds as $assetId) {
            if (!isset($pendingRequests[$assetId])) {
                $errors["asset_{$assetId}"] = "Aucune demande de réforme en attente n'a été trouvée pour ce bien.";
                continue;
            }

            try {
                if ($statut === AssetReformRequest::STATUT_VALIDEE) {
                    $result = $this->validateReformRequest($pendingRequests[$assetId], $reformeEtat, $validatedBy);
                } else {
                    $result = $this->rejectReformRequest($pendingRequests[$assetId], $validatedBy);
                }
                $results[$assetId] = $result;
            } catch (ValidationFailedException $e) {
                $errors["asset_{$assetId}"] = $e->getErrors();
            }
        }

        if (!empty($errors) && !empty($results)) {
            // Transaction partielle : on retourne les erreurs mais on a quand même traité certains biens
            throw new ValidationFailedException($errors);
        }

        if (!empty($errors)) {
            throw new ValidationFailedException($errors);
        }

        return $results;
    }

    private function validateReformRequest(AssetReformRequest $request, EtatBien $reformeEtat, User $validatedBy): array
    {
        if ($request->getPieceJointes()->isEmpty()) {
            throw new ValidationFailedException(['pieceJointe' => 'La demande de réforme doit avoir une pièce justificative.']);
        }

        // Transaction : valider la demande et mettre le bien à RÉFORMÉ
        $this->entityManager->beginTransaction();
        try {
            $request->setStatut(AssetReformRequest::STATUT_VALIDEE);
            $request->setValidatedAt(new \DateTimeImmutable());
            $request->setValidatedBy($validatedBy);
            $this->reformRequestRepository->save($request);

            $asset = $request->getAsset();
            $asset->syncEtatBiens([$reformeEtat]);
            $this->assetRepository->save($asset);

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollBack();
            throw $e;
        }

        return [
            'requestId' => $request->getId(),
            'assetId' => $asset->getId(),
            'assetReference' => $asset->getReference(),
            'validatedAt' => $request->getValidatedAt()->format('Y-m-d H:i:s'),
            'validatedBy' => $validatedBy->getLastName() . ' ' . $validatedBy->getFirstName(),
        ];
    }

    private function rejectReformRequest(AssetReformRequest $request, User $validatedBy): array
    {
        // Transaction : rejeter la demande et restaurer l'état précédent du bien
        $this->entityManager->beginTransaction();
        try {
            $request->setStatut(AssetReformRequest::STATUT_REJETEE);
            $request->setValidatedAt(new \DateTimeImmutable());
            $request->setValidatedBy($validatedBy);
            $this->reformRequestRepository->save($request);

            $asset = $request->getAsset();
            
            // Restaurer l'état précédent
            $previousEtatString = $request->getPreviousEtat();
            if ($previousEtatString) {
                $previousEtatNames = array_map('trim', explode(',', $previousEtatString));
                $previousEtats = [];
                foreach ($previousEtatNames as $etatName) {
                    $etat = $this->etatBienRepository->findOneBy(['nom' => $etatName, 'isDelete' => false]);
                    if ($etat) {
                        $previousEtats[] = $etat;
                    }
                }
                
                if (!empty($previousEtats)) {
                    $asset->syncEtatBiens($previousEtats);
                } else {
                    // Si aucun état précédent n'est trouvé, retirer l'état À RÉFORMER
                    $currentEtats = [];
                    foreach ($asset->getEtatBiens() as $etat) {
                        if ($etat->getNom() !== 'À RÉFORMER') {
                            $currentEtats[] = $etat;
                        }
                    }
                    $asset->syncEtatBiens($currentEtats);
                }
            } else {
                // Si aucun état précédent n'est enregistré, retirer l'état À RÉFORMER
                $currentEtats = [];
                foreach ($asset->getEtatBiens() as $etat) {
                    if ($etat->getNom() !== 'À RÉFORMER') {
                        $currentEtats[] = $etat;
                    }
                }
                $asset->syncEtatBiens($currentEtats);
            }
            
            $this->assetRepository->save($asset);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollBack();
            throw $e;
        }

        return [
            'requestId' => $request->getId(),
            'assetId' => $asset->getId(),
            'assetReference' => $asset->getReference(),
            'validatedAt' => $request->getValidatedAt()->format('Y-m-d H:i:s'),
            'validatedBy' => $validatedBy->getLastName() . ' ' . $validatedBy->getFirstName(),
            'previousEtat' => $request->getPreviousEtat(),
        ];
    }

    private function validate(AssetReformRequest $request): void
    {
        $errors = $this->validator->validate($request);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($errorMessages);
        }
    }
}
