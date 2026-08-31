<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetExit;
use App\Entity\PieceJointe;
use App\Entity\ExitType; 
use App\Entity\Service;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\AssetExitRepository;
use App\Repository\AssetRepository;
use App\Repository\ExitTypeRepository;
use App\Repository\EtatBienRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AssetExitService
{
    public function __construct(
        private readonly AssetExitRepository $exitRepository,
        private readonly AssetRepository $assetRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ExitTypeRepository $exitTypeRepository,
        private readonly EtatBienRepository $etatBienRepository,
        private readonly ValidatorInterface $validator,
        private readonly FileUploadService $fileUploadService
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
    ): AssetExit {
        if (!isset($payload['asset_id'])) {
            throw new ValidationFailedException(['asset_id' => 'L\'identifiant du bien est requis.']);
        }

        $asset = $this->assetRepository->find($payload['asset_id']);
        if (!$asset) {
            throw new ResourceNotFoundException('Bien introuvable.');
        }

        // Vérifier qu'une sortie n'existe pas déjà pour ce bien
        $existingExit = $this->exitRepository->findByAssetId((int) $payload['asset_id']);
        if ($existingExit) {
            throw new ValidationFailedException(['asset_id' => 'Ce bien possède déjà une sortie.']);
        }

        $exit = new AssetExit();

        // Définir l'utilisateur créateur si fourni
        if ($currentUser !== null) {
            $exit->setCreatedBy($currentUser);
        }
        
        $this->applyPayload($exit, $payload, $asset);
        $this->attachFiles($exit, $documents, $documentLabels);

        $this->validate($exit);
        $this->exitRepository->save($exit);

        // Mettre le statut à SORTIS
        $asset->setStatut('SORTIS');

        // ✅ Si c'est une réforme, appliquer l'état "Réformé"
        $isReforme = $this->isReforme($payload);
        if ($isReforme) {
            $this->applyReformeEtatToAsset($asset);
        }

        // ✅ Sauvegarder le bien avec son nouvel état
        $this->assetRepository->save($asset);

        return $exit;
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function update(
        AssetExit $exit,
        array $payload,
        array $documents = [],
        array $documentLabels = [],
        ?User $currentUser = null
    ): AssetExit {
        $asset = $exit->getAsset();
        
        $this->applyPayload($exit, $payload, $asset);
        $this->attachFiles($exit, $documents, $documentLabels);

        // Définir l'utilisateur modificateur si fourni
        if ($currentUser !== null) {
            $exit->setUpdatedBy($currentUser);
        }

        $this->validate($exit);
        $this->exitRepository->save($exit);

        $isReforme = $this->isReforme($payload);
        if ($asset && $isReforme) {
            $this->applyReformeEtatToAsset($asset);
            $this->assetRepository->save($asset);
        }

        return $exit;
    }

    public function delete(AssetExit $exit): void
    {
        $exit->setDelete(true);
        $this->exitRepository->save($exit);

        $asset = $exit->getAsset();
        if ($asset) {
            $asset->setStatut('ACTIF');
            $this->assetRepository->save($asset);
        }
    }

    public function deletePieceJointe(AssetExit $exit, PieceJointe $pieceJointe): void
    {
        $exit->removePieceJointe($pieceJointe);
        $this->entityManager->flush();

        $this->entityManager->remove($pieceJointe);
        $this->entityManager->flush();

        $this->fileUploadService->deleteFile($pieceJointe->getChemin());
    }

    /**
     * ✅ Vérifie si c'est une réforme
     */
    private function isReforme(array $payload): bool
    {
        // ✅ 1. Vérifier le champ explicite 'reforme'
        if (isset($payload['reforme'])) {
            return filter_var($payload['reforme'], FILTER_VALIDATE_BOOLEAN);
        }

        // ✅ 2. Si 'reforme' n'est pas défini, vérifier le motif
        $motif = strtoupper($payload['motifSortie'] ?? '');
        $reformeMotifs = ['REFORME', 'REFORMER', 'REF', 'REFRME', 'RÉFORME', 'RÉFORMER'];
        if (in_array($motif, $reformeMotifs)) {
            return true;
        }

        // ✅ 3. Vérifier les mots-clés dans les observations
        $observations = strtolower($payload['observations'] ?? '');
        $reformeKeywords = ['réform', 'reform', 'obsolète', 'hors service', 'hors d\'usage', 'détruit', 'cassé', 'usagé', 'rebut'];
        foreach ($reformeKeywords as $keyword) {
            if (strpos($observations, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(AssetExit $exit, array $payload, ?Asset $asset = null): void
    {
        if (array_key_exists('asset_id', $payload)) {
            $asset = $this->assetRepository->find($payload['asset_id']);
            if (!$asset) {
                throw new ResourceNotFoundException('Bien introuvable.');
            }
            $exit->setAsset($asset);
        }

        if (isset($payload['service_id']) && !empty($payload['service_id'])) {
            $service = $this->serviceRepository->find($payload['service_id']);
            if (!$service) {
                throw new ResourceNotFoundException('Service introuvable.');
            }
            $exit->setService($service);
        }

        if (isset($payload['user_id']) && !empty($payload['user_id'])) {
            $user = $this->userRepository->find($payload['user_id']);
            if (!$user) {
                throw new ResourceNotFoundException('Utilisateur introuvable.');
            }
            $exit->setUser($user);
        }

        if (isset($payload['motifSortie']) && !empty($payload['motifSortie'])) {
            $exit->setMotifSortie($payload['motifSortie']);
        }

        // Type de sortie
        $exitTypeId = $payload['exit_type_id'] ?? null;
        
        if ($exitTypeId !== null && $exitTypeId !== '' && $exitTypeId !== 'null') {
            $exitType = $this->exitTypeRepository->findActiveById((int) $exitTypeId);
            if (!$exitType) {
                throw new ResourceNotFoundException('Type de sortie introuvable avec l\'ID: ' . $exitTypeId);
            }
            $exit->setExitType($exitType);
        } elseif ($this->isReforme($payload)) {
            // Si reforme est true et exit_type_id n'est pas fourni, détecter automatiquement
            $this->detectAndSetExitType($exit, $asset, $payload);
        }

        if (isset($payload['dateSortie']) && !empty($payload['dateSortie'])) {
            $exit->setDateSortie(new \DateTime($payload['dateSortie']));
        } elseif (!$exit->getDateSortie()) {
            $exit->setDateSortie(new \DateTime());
        }

        if (isset($payload['protocoleReference']) && !empty($payload['protocoleReference'])) {
            $exit->setProtocoleReference($payload['protocoleReference']);
        }

        if (isset($payload['observations']) && !empty($payload['observations'])) {
            $exit->setObservations($payload['observations']);
        }
    }

    /**
     * Détection automatique du type de sortie
     */
    private function detectAndSetExitType(AssetExit $exit, Asset $asset, array $payload): void
    {
        if ($this->isReforme($payload)) {
            $reformeType = $this->exitTypeRepository->findReformeExitType();
            
            if (!$reformeType) {
                $reformeType = $this->exitTypeRepository->findByNameOrCode('Réforme');
            }
            
            if (!$reformeType) {
                $reformeType = $this->exitTypeRepository->findByNameOrCode('REFORME');
            }
            
            if ($reformeType) {
                $exit->setExitType($reformeType);
            } else {
                throw new ValidationFailedException([
                    'exit_type' => 'Le type de sortie "Réforme" n\'est pas configuré dans le système. Veuillez le créer d\'abord.'
                ]);
            }
        } else {
            throw new ValidationFailedException([
                'exit_type_id' => 'Le type de sortie est requis. Veuillez spécifier un type de sortie valide.'
            ]);
        }
    }

    /**
     * Vérifier si un type de sortie est une "Réforme"
     */
    private function isReformeType(ExitType $exitType): bool
    {
        $nom = strtolower($exitType->getNom() ?? '');
        $code = strtolower($exitType->getCode() ?? '');
        
        return strpos($nom, 'réform') !== false || 
               strpos($nom, 'reform') !== false ||
               strpos($code, 'reform') !== false;
    }

    /**
     * ✅ Appliquer l'état "Réformé" au bien
     * Crée l'état s'il n'existe pas
     */
    private function applyReformeEtatToAsset(Asset $asset): void
    {
        // ✅ 1. Trouver l'état "Réformé"
        $reformeEtat = $this->etatBienRepository->findReformeEtat();
        
        if (!$reformeEtat) {
            // ✅ ESSAYER de trouver par nom exact
            $reformeEtat = $this->etatBienRepository->findByName('Réformé');
        }
        
        if (!$reformeEtat) {
            // ✅ ESSAYER avec "Reformé" (sans accent)
            $reformeEtat = $this->etatBienRepository->findByName('Reformé');
        }
        
        // ✅ 2. Si l'état n'existe pas, le créer
        if (!$reformeEtat) {
            $reformeEtat = new \App\Entity\EtatBien();
            $reformeEtat->setNom('Réformé');
            $reformeEtat->setNumeroOrdre(8);
            $reformeEtat->setDescription('État pour les biens réformés');
            $now = new \DateTimeImmutable();
            $reformeEtat->setCreatedAt($now);
            $reformeEtat->setUpdatedAt($now);
            
            $this->entityManager->persist($reformeEtat);
            $this->entityManager->flush();
        }
        
        // ✅ 3. Ajouter l'état au bien
        $asset->getEtatBiens()->clear();
        $asset->addEtatBien($reformeEtat);
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function attachFiles(AssetExit $exit, array $documents, array $documentLabels): void
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
            $exit->addPieceJointe($piece);
        }
    }

    private function validate(AssetExit $exit): void
    {
        $errors = $this->validator->validate($exit);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($errorMessages);
        }
    }

    /**
     * Retourne le détenteur d'une sortie de bien sous une forme uniforme
     * Si user est renseigné, retourne l'utilisateur
     * Sinon, retourne le service
     */
    public function getDetenteur(AssetExit $exit): ?array
    {
        if ($exit->getUser()) {
            $service = $exit->getUser()->getService();
            return [
                // 'type' => 'USER',
                'id' => $exit->getUser()->getId(),
                'nom' => $exit->getUser()->getLastName(),
                'prenom' => $exit->getUser()->getFirstName(),
                'matricule' => $exit->getUser()->getMatricule(),
                'service' => $service ? [
                    'id' => $service->getId(),
                    'nom' => $service->getNom(),
                ] : null,
            ];
        }
        if ($exit->getService()) {
            return [
                'type' => 'SERVICE',
                'id' => $exit->getService()->getId(),
                'nom' => $exit->getService()->getNom(),
            ];
        }
        return null;
    }
}