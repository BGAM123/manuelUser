<?php

namespace App\Service;

use App\Entity\AssetReevaluation;
use App\Entity\PieceJointe;

final class AssetReevaluationResponseBuilder
{
    public function buildDetail(AssetReevaluation $reevaluation): array
    {
        return [
            'id' => $reevaluation->getId(),
            // 'valeurActuelle' => $reevaluation->getValeurActuelle(),
            'nouvelleValeur' => $reevaluation->getNouvelleValeur(),
            'methodeEvaluation' => $reevaluation->getMethodeEvaluation(),
            'service' => $reevaluation->getService() ? [
                'id' => $reevaluation->getService()->getId(),
                'nom' => $reevaluation->getService()->getNom(),
            ] : null,
            'dateReevaluation' => $reevaluation->getDateReevaluation()?->format('Y-m-d'),
            'motif' => $reevaluation->getMotif(),
            'observations' => $reevaluation->getObservations(),
            'piecesJointes' => $this->buildPiecesJointes($reevaluation),
            'createdAt' => $reevaluation->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function buildList(array $reevaluations): array
    {
        return array_map([$this, 'buildDetail'], $reevaluations);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPiecesJointes(AssetReevaluation $reevaluation): array
    {
        return $reevaluation->getPieceJointes()->map(function (PieceJointe $piece) {
            return [
                'id' => $piece->getId(),
                'nom' => $piece->getNom(),
                'chemin' => $piece->getChemin(),
            ];
        })->toArray();
    }
}
