<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetMaintenance;
use App\Entity\EtatBien;
use App\Entity\PieceJointe;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\AssetMaintenanceRepository;
use App\Repository\AssetRepository;
use App\Repository\EtatBienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AssetMaintenanceService
{
    public function __construct(
        private readonly AssetMaintenanceRepository $maintenanceRepository,
        private readonly AssetRepository $assetRepository,
        private readonly EtatBienRepository $etatBienRepository,
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
    ): AssetMaintenance {
        $maintenance = new AssetMaintenance();

        // Une maintenance qui vient d'être créée démarre toujours ouverte (EN COURS) : la date
        // de récupération ne peut être posée que via terminate() (endpoint dédié), jamais à la
        // création — sinon un bien peut se retrouver "en maintenance" déjà terminée d'office.
        $this->applyPayload($maintenance, $payload, allowDateRecuperation: false);
        $this->attachFiles($maintenance, $documents, $documentLabels);

        // Définir l'utilisateur créateur si fourni
        if ($currentUser !== null) {
            $maintenance->setCreatedBy($currentUser);
        }

        $this->validate($maintenance);
        $this->maintenanceRepository->save($maintenance);
        $this->syncAssetStatuts($maintenance->getAssets());

        return $maintenance;
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function update(
        AssetMaintenance $maintenance,
        array $payload,
        array $documents = [],
        array $documentLabels = [],
        ?User $currentUser = null
    ): AssetMaintenance {
        // Inclure les biens retirés de la maintenance : ils doivent éventuellement
        // repasser à ACTIF après la mise à jour.
        $affectedAssets = $maintenance->getAssets()->toArray();
        $this->applyPayload($maintenance, $payload);
        $this->attachFiles($maintenance, $documents, $documentLabels);

        // Définir l'utilisateur modificateur si fourni
        if ($currentUser !== null) {
            $maintenance->setUpdatedBy($currentUser);
        }

        $this->validate($maintenance);
        $this->maintenanceRepository->save($maintenance);
        $this->syncAssetStatuts(array_merge($affectedAssets, $maintenance->getAssets()->toArray()));

        return $maintenance;
    }

    public function delete(AssetMaintenance $maintenance): void
    {
        $affectedAssets = iterator_to_array($maintenance->getAssets());

        $this->entityManager->remove($maintenance);
        $this->entityManager->flush();

        $this->syncAssetStatuts($affectedAssets);
    }


    /**
     * ✅ Compte le nombre de maintenances effectuées sur un bien
     * et vérifie si le seuil de la catégorie est dépassé
     */
    public function countMaintenancesForAsset(Asset $asset): int
    {
        return $this->maintenanceRepository->countMaintenancesForAsset($asset);
    }

    /**
     * ✅ Récupère le seuil de la catégorie d'un bien
     */
    public function getMaintenanceThresholdForAsset(Asset $asset): ?int
    {
        $category = $asset->getCategories()->first();
        if (!$category) {
            return null;
        }
        return $category->getSeuil();
    }

    /**
     * ✅ Vérifie si le nombre de maintenances dépasse le seuil
     * Retourne un message clair
     */
    public function checkMaintenanceThreshold(Asset $asset): array
    {
        $seuil = $this->getMaintenanceThresholdForAsset($asset);
        $nombreMaintenances = $this->countMaintenancesForAsset($asset);

        // Si pas de seuil défini
        if ($seuil === null) {
            return [
                'seuil_defini' => false,
                'seuil' => null,
                'atteint' => false,
                'nombre_maintenances' => $nombreMaintenances,
                'message' => 'Aucun seuil défini pour cette catégorie.'
            ];
        }

        $depasse = $nombreMaintenances > $seuil;
        $restant = max(0, $seuil - $nombreMaintenances);

        return [
            'seuil_defini' => true,
            'seuil' => $seuil,
            'nombre_maintenances' => $nombreMaintenances,
            'atteint' => $nombreMaintenances >= $seuil,
            'depasse' => $depasse,
            'restant' => $restant,
            'message' => $depasse
                ? sprintf(
                    '⚠️ ATTENTION : Ce bien a déjà %d maintenance(s). ' .
                    'La catégorie autorise un maximum de %d maintenance(s). ' .
                    'Seuil dépassé de %d maintenance(s).',
                    $nombreMaintenances,
                    $seuil,
                    $nombreMaintenances - $seuil
                )
                : sprintf(
                    '✓ Ce bien a %d maintenance(s) sur %d autorisées. ' .
                    'Encore %d maintenance(s) possible(s).',
                    $nombreMaintenances,
                    $seuil,
                    $restant
                )
        ];
    }

    /**
     * Termine une maintenance en cours : pose dateRecuperation (aujourd'hui par défaut),
     * et repasse chaque bien concerné à ACTIF s'il n'a plus aucune autre maintenance ouverte.
     * Le contrôleur est responsable de vérifier au préalable qu'elle n'est pas déjà terminée
     * (même convention que SoftDeleteAssetTypeController pour les conflits d'état).
     */
    public function terminate(AssetMaintenance $maintenance, ?\DateTimeInterface $dateRecuperation = null): AssetMaintenance
    {
        $maintenance->setDateRecuperation($dateRecuperation ?? new \DateTime());
        $this->maintenanceRepository->save($maintenance);
        $this->syncAssetStatuts($maintenance->getAssets());

        return $maintenance;
    }

    /**
     * Aligne Asset::$statut sur l'existence d'une maintenance ouverte pour chaque bien
     * (EN MAINTENANCE s'il en a au moins une, ACTIF sinon). Interroge la base plutôt que
     * la collection en mémoire pour rester correct après un flush qui vient de créer,
     * clôturer ou supprimer une maintenance.
     *
     * @param iterable<Asset> $assets
     */
    private function syncAssetStatuts(iterable $assets): void
    {
        foreach ($assets as $asset) {
            $openMaintenance = $this->maintenanceRepository->findOpenForAsset($asset);
            $asset->setStatut(null !== $openMaintenance ? 'EN MAINTENANCE' : 'ACTIF');
        }

        $this->entityManager->flush();
    }

    public function deletePieceJointe(AssetMaintenance $maintenance, PieceJointe $pieceJointe): void
    {
        $maintenance->removePieceJointe($pieceJointe);
        $this->entityManager->flush();

        $this->entityManager->remove($pieceJointe);
        $this->entityManager->flush();

        $this->fileUploadService->deleteFile($pieceJointe->getChemin());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(AssetMaintenance $maintenance, array $payload, bool $allowDateRecuperation = true): void
    {
        // Lors d'une modification, asset_ids représente la sélection complète : les
        // anciennes liaisons doivent donc être remplacées, pas seulement complétées.
        if (array_key_exists('asset_ids', $payload)) {
            $assetIds = is_array($payload['asset_ids']) ? $payload['asset_ids'] : [$payload['asset_ids']];
            $assets = [];
            foreach ($assetIds as $assetId) {
                if (!is_scalar($assetId) || '' === (string) $assetId || !ctype_digit((string) $assetId)) {
                    throw new \InvalidArgumentException(json_encode(['asset_ids' => 'Chaque ID de bien doit être un entier valide.']));
                }
                $asset = $this->assetRepository->getActiveById((int) $assetId);
                if (null === $asset) {
                    throw new ResourceNotFoundException(sprintf('Bien introuvable : %s.', $assetId));
                }
                // Vérifier que le bien n'a pas le statut SORTIS
                if (in_array($asset->getStatut(), ['SORTIS', 'SORTIE'], true)) {
                    throw new ValidationFailedException([
                        'asset_ids' => 'Le bien a le statut SORTIS et ne peut pas être inclus dans une maintenance.'
                    ]);
                }
                $assets[] = $asset;
            }

            foreach ($maintenance->getAssets()->toArray() as $asset) {
                $maintenance->removeAsset($asset);
            }
            foreach ($assets as $asset) {
                $maintenance->addAsset($asset);
            }
        }

        if (array_key_exists('etat_bien_id', $payload)) {
            $etatBien = $this->etatBienRepository->find($payload['etat_bien_id']);
            if (null === $etatBien) {
                throw new ResourceNotFoundException('État du bien introuvable.');
            }
            $maintenance->setEtatBien($etatBien);
        }

        if (isset($payload['motif'])) {
            $maintenance->setMotif($payload['motif']);
        }

        if (isset($payload['cout'])) {
            $maintenance->setCout($payload['cout']);
        }

        if (array_key_exists('dateIntervention', $payload)) {
            $maintenance->setDateIntervention($this->parseDate($payload['dateIntervention'], 'dateIntervention'));
        }

        // dateRecuperationPrevue : estimation initiale, saisissable à la création et modifiable
        // ensuite, ne pilote jamais le statut. Si le payload de création envoie 'dateRecuperation'
        // au lieu de 'dateRecuperationPrevue' (habitude issue de l'ancien comportement de l'API),
        // on l'interprète comme une estimation plutôt que de la rejeter silencieusement.
        if (array_key_exists('dateRecuperationPrevue', $payload)) {
            $maintenance->setDateRecuperationPrevue($this->parseDate($payload['dateRecuperationPrevue'], 'dateRecuperationPrevue'));
        } elseif (!$allowDateRecuperation && array_key_exists('dateRecuperation', $payload)) {
            $maintenance->setDateRecuperationPrevue($this->parseDate($payload['dateRecuperation'], 'dateRecuperation'));
        }

        if ($allowDateRecuperation && array_key_exists('dateRecuperation', $payload)) {
            $maintenance->setDateRecuperation($this->parseDate($payload['dateRecuperation'], 'dateRecuperation'));
        }

        if (isset($payload['observations'])) {
            $maintenance->setObservations($payload['observations']);
        }
    }

    private function parseDate(mixed $value, string $field): ?\DateTimeInterface
    {
        if (null === $value || '' === trim((string) $value)) {
            return null;
        }

        try {
            return new \DateTime((string) $value);
        } catch (\Exception) {
            throw new \InvalidArgumentException(json_encode([$field => 'La date fournie est invalide.']));
        }
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function attachFiles(AssetMaintenance $maintenance, array $documents, array $documentLabels): void
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
            $maintenance->addPieceJointe($piece);
        }
    }

    private function validate(AssetMaintenance $maintenance): void
    {
        $errors = $this->validator->validate($maintenance);
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
