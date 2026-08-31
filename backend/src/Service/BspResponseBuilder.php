<?php

namespace App\Service;

use App\Entity\Bsp;
use App\Entity\PieceJointe;

final class BspResponseBuilder
{
    public function buildDetail(Bsp $bsp): array
    {
        $assetExit = $bsp->getAssetExit();

        return [
            'id' => $bsp->getId(),
            'numero' => $bsp->getNumero(),
            'assetExit' => $assetExit ? [
                'id' => $assetExit->getId(),
                'motifSortie' => $assetExit->getMotifSortie(),
                'asset' => $this->buildAsset($assetExit->getAsset()),
            ] : null,
            'service' => $this->buildService($bsp->getService()),
            'beneficiaire' => $this->buildUser($bsp->getBeneficiaire()),
            'createdBy' => $this->buildUser($bsp->getCreatedBy()),
            'quantiteDemandee' => $bsp->getQuantiteDemandee(),
            'quantiteAccordee' => $bsp->getQuantiteAccordee(),
            'quantiteServie' => $bsp->getQuantiteServie(),
            'observations' => $bsp->getObservations(),
            'dateEtablissement' => $bsp->getDateEtablissement()?->format('Y-m-d'),
            'piecesJointes' => $this->buildPiecesJointes($bsp),
            'createdAt' => $bsp->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function buildList(array $bsps): array
    {
        return array_map([$this, 'buildDetail'], $bsps);
    }

    private function buildAsset(?object $asset): ?array
    {
        if (!$asset) {
            return null;
        }

        return [
            'id' => $asset->getId(),
            'reference' => $asset->getReference(),
            'nom' => $asset->getNom(),
        ];
    }

    private function buildService(?object $service): ?array
    {
        if (!$service) {
            return null;
        }

        return [
            'id' => $service->getId(),
            'nom' => $service->getNom(),
        ];
    }

    private function buildUser(?object $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'nom' => $user->getLastName(),
            'prenom' => $user->getFirstName(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPiecesJointes(Bsp $bsp): array
    {
        return $bsp->getPieceJointes()->map(function (PieceJointe $piece) {
            return [
                'id' => $piece->getId(),
                'nom' => $piece->getNom(),
                'chemin' => $piece->getChemin(),
            ];
        })->toArray();
    }
}
