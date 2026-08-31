<?php

namespace App\Service;

use App\Entity\AssetDepreciation;
use App\Entity\PieceJointe;

final class AssetDepreciationResponseBuilder
{
    public function buildDetail(AssetDepreciation $depreciation): array
    {
        return [
            'id' => $depreciation->getId(),
            'typeDepreciation' => $depreciation->getTypeDepreciation(),
            'methodeAmortissement' => $depreciation->getMethodeAmortissement(),
            'dureeVie' => $depreciation->getDureeVie(),
            // 'valeurActuelle' => $depreciation->getValeurActuelle(),
            'tauxDepreciation' => $depreciation->getTauxDepreciation(),
            'montantDepreciation' => $depreciation->getMontantDepreciation(),
            'dateDepreciation' => $depreciation->getDateDepreciation()?->format('Y-m-d'),
            'motif' => $depreciation->getMotif(),
            'observations' => $depreciation->getObservations(),
            'piecesJointes' => $this->buildPiecesJointes($depreciation),
            'createdAt' => $depreciation->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function buildList(array $depreciations): array
    {
        return array_map([$this, 'buildDetail'], $depreciations);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPiecesJointes(AssetDepreciation $depreciation): array
    {
        return $depreciation->getPieceJointes()->map(function (PieceJointe $piece) {
            return [
                'id' => $piece->getId(),
                'nom' => $piece->getNom(),
                'chemin' => $piece->getChemin(),
            ];
        })->toArray();
    }
}
