<?php

namespace App\Service;

use App\Entity\AssetExit;
use App\Entity\PieceJointe;

final class AssetExitResponseBuilder
{
    public function __construct(
        private readonly AssetExitService $assetExitService
    ) {
    }

    public function buildDetail(AssetExit $exit): array
    {
        $bsps = $this->buildBsps($exit);

        return [
            'id' => $exit->getId(),
            'exitType' => $this->buildExitType($exit->getExitType()),
            'motifSortie' => $exit->getMotifSortie(),
            'dateSortie' => $exit->getDateSortie()?->format('Y-m-d'),
            'protocoleReference' => $exit->getProtocoleReference(),
            'observations' => $exit->getObservations(),
            'asset' => $this->buildAsset($exit->getAsset()),
            'service' => $this->buildService($exit->getService()),
            'detenteur' => $this->assetExitService->getDetenteur($exit),
            'piecesJointes' => $this->buildPiecesJointes($exit),
            'bspsCount' => count($bsps),
            'bsps' => $bsps,
            'createdAt' => $exit->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function buildList(array $exits): array
    {
        return array_map([$this, 'buildDetail'], $exits);
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
            'statut' => $asset->getStatut(),
            'etatBien' => $this->buildEtatBien($asset->getEtatBiens()),
        ];
    }


    private function buildEtatBien($etats): ?array
    {
        if ($etats->isEmpty()) {
            return null;
        }

        $etat = $etats->first();
        return [
            'id' => $etat->getId(),
            'nom' => $etat->getNom(),
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

     private function buildExitType(?object $exitType): ?array // Nouvelle méthode
    {
        if (!$exitType) {
            return null;
        }

        return [
            'id' => $exitType->getId(),
            'nom' => $exitType->getNom(),
            'code' => $exitType->getCode(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPiecesJointes(AssetExit $exit): array
    {
        return $exit->getPieceJointes()->map(function (PieceJointe $piece) {
            return [
                'id' => $piece->getId(),
                'nom' => $piece->getNom(),
                'chemin' => $piece->getChemin(),
            ];
        })->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildBsps(AssetExit $exit): array
    {
        $bsps = [];
        foreach ($exit->getBsps() as $bsp) {
            if ($bsp->isDelete()) {
                continue;
            }
            $bsps[] = [
                'id' => $bsp->getId(),
                'numero' => $bsp->getNumero(),
                'quantiteServie' => $bsp->getQuantiteServie(),
                'beneficiaire' => $this->buildUser($bsp->getBeneficiaire()),
            ];
        }

        return $bsps;
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
}
