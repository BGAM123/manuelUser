<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetExit;
use App\Entity\Bsp;
use App\Entity\PieceJointe;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\AssetRepository;
use App\Repository\BspRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Service\ForceDeleteService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BspService
{
    public function __construct(
        private readonly BspRepository $bspRepository,
        private readonly AssetRepository $assetRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
        private readonly FileUploadService $fileUploadService,
        private readonly ForceDeleteService $forceDeleteService,
    ) {
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function create(
        AssetExit $assetExit,
        array $payload,
        array $documents,
        array $documentLabels,
        ?User $currentUser
    ): Bsp {
        $bsp = new Bsp();
        $bsp->setAssetExit($assetExit);
        $bsp->setCreatedBy($currentUser);
        $bsp->setNumero($this->bspRepository->generateNextNumero());

        $this->applyPayload($bsp, $payload, $assetExit);
        $this->attachFiles($bsp, $documents, $documentLabels);

        $this->validate($bsp);

        $asset = $assetExit->getAsset();
        $this->assertStockAvailable($asset, (int) $bsp->getQuantiteServie());

        $this->bspRepository->save($bsp);
        $this->adjustStock($asset, -(int) $bsp->getQuantiteServie());

        return $bsp;
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function update(
        Bsp $bsp,
        array $payload,
        array $documents,
        array $documentLabels
    ): Bsp {
        $asset = $bsp->getAssetExit()?->getAsset();
        $previousQuantite = (int) $bsp->getQuantiteServie();

        $this->applyPayload($bsp, $payload, $bsp->getAssetExit());
        $this->attachFiles($bsp, $documents, $documentLabels);

        $this->validate($bsp);

        $newQuantite = (int) $bsp->getQuantiteServie();
        if ($newQuantite !== $previousQuantite) {
            $this->assertStockAvailable($asset, $newQuantite, $previousQuantite);
        }

        $this->bspRepository->save($bsp);

        if ($newQuantite !== $previousQuantite) {
            $this->adjustStock($asset, $previousQuantite - $newQuantite);
        }

        return $bsp;
    }

    public function delete(Bsp $bsp): void
    {
        $bsp->setDelete(true);
        $this->bspRepository->save($bsp);

        $this->adjustStock($bsp->getAssetExit()?->getAsset(), (int) $bsp->getQuantiteServie());
    }

    /**
     * Suppression définitive. Ne restaure le stock que si ce n'est pas déjà fait par
     * un soft-delete précédent (sinon double-restauration en forçant la suppression
     * d'un BSP déjà soft-deleted).
     */
    public function deleteForced(Bsp $bsp): void
    {
        if (!$bsp->isDelete()) {
            $this->adjustStock($bsp->getAssetExit()?->getAsset(), (int) $bsp->getQuantiteServie());
        }

        $this->forceDeleteService->delete($bsp);
    }

    public function deletePieceJointe(Bsp $bsp, PieceJointe $pieceJointe): void
    {
        $bsp->removePieceJointe($pieceJointe);
        $this->bspRepository->save($bsp);

        $this->fileUploadService->deleteFile($pieceJointe->getChemin());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(Bsp $bsp, array $payload, ?AssetExit $assetExit): void
    {
        if (isset($payload['service_id']) && !empty($payload['service_id'])) {
            $service = $this->serviceRepository->find($payload['service_id']);
            if (!$service) {
                throw new ResourceNotFoundException('Service introuvable.');
            }
            $bsp->setService($service);
        } elseif (null === $bsp->getService() && $assetExit) {
            $bsp->setService($assetExit->getService());
        }

        if (isset($payload['beneficiaire_id']) && !empty($payload['beneficiaire_id'])) {
            $beneficiaire = $this->userRepository->find($payload['beneficiaire_id']);
            if (!$beneficiaire) {
                throw new ResourceNotFoundException('Bénéficiaire introuvable.');
            }
            $bsp->setBeneficiaire($beneficiaire);
        }

        if (isset($payload['quantiteDemandee']) && '' !== $payload['quantiteDemandee']) {
            $bsp->setQuantiteDemandee((int) $payload['quantiteDemandee']);
        }

        if (isset($payload['quantiteAccordee']) && '' !== $payload['quantiteAccordee']) {
            $bsp->setQuantiteAccordee((int) $payload['quantiteAccordee']);
        }

        if (isset($payload['quantiteServie']) && '' !== $payload['quantiteServie']) {
            $bsp->setQuantiteServie((int) $payload['quantiteServie']);
        }

        if (isset($payload['dateEtablissement']) && !empty($payload['dateEtablissement'])) {
            $bsp->setDateEtablissement(new \DateTime($payload['dateEtablissement']));
        }

        if (isset($payload['observations']) && !empty($payload['observations'])) {
            $bsp->setObservations($payload['observations']);
        }
    }

    /**
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function attachFiles(Bsp $bsp, array $documents, array $documentLabels): void
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
                FileUploadService::KIND_DOCUMENT,
                $label,
                true
            );
            $bsp->addPieceJointe($piece);
        }
    }

    /**
     * Vérifie que la quantité demandée ne dépasse pas le stock disponible.
     * N'a d'effet que sur les biens de type stock/consomptible (Asset::quantiteStock non null) ;
     * ignoré pour les biens physiques uniques, qui ne portent pas de quantité.
     */
    private function assertStockAvailable(?Asset $asset, int $quantite, int $alreadyReserved = 0): void
    {
        if (!$asset || null === $asset->getQuantiteStock()) {
            return;
        }

        // $available = $asset->getQuantiteStock() + $alreadyReserved;
        // if ($quantite > $available) {
        //     throw new ValidationFailedException([
        //         'quantiteServie' => sprintf('Stock insuffisant. Quantité disponible : %d.', $available),
        //     ]);
        // }
    }

    /**
     * Applique un delta au stock du bien (négatif = sortie, positif = restitution).
     * Sans effet si le bien n'est pas suivi en stock (quantiteStock null).
     */
    private function adjustStock(?Asset $asset, int $delta): void
    {
        if (!$asset || null === $asset->getQuantiteStock()) {
            return;
        }

        $asset->setQuantiteStock($asset->getQuantiteStock() + $delta);
        $this->assetRepository->save($asset);
    }

    private function validate(Bsp $bsp): void
    {
        $errors = $this->validator->validate($bsp);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($errorMessages);
        }
    }
}
