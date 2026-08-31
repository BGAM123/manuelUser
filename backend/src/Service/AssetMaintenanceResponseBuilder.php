<?php

namespace App\Service;

use App\Entity\AssetMaintenance;
use App\Entity\PieceJointe;

final class AssetMaintenanceResponseBuilder
{
    public function buildDetail(AssetMaintenance $maintenance): array
    {
        return [
            'id' => $maintenance->getId(),
            'etatBien' => $maintenance->getEtatBien() ? [
                'id' => $maintenance->getEtatBien()->getId(),
                'nom' => $maintenance->getEtatBien()->getNom(),
            ] : null,
            'motif' => $maintenance->getMotif(),
            'cout' => $maintenance->getCout(),
            'dateIntervention' => $maintenance->getDateIntervention()?->format('Y-m-d'),
            // Unifiée : réelle si la maintenance est terminée, sinon estimée (si connue).
            'dateRecuperation' => $maintenance->getDateRecuperationUnifiee()?->format('Y-m-d'),
            'dateRecuperationPrevue' => $maintenance->getDateRecuperationPrevue()?->format('Y-m-d'),
            'dateRecuperationReelle' => $maintenance->getDateRecuperation()?->format('Y-m-d'),
            'observations' => $maintenance->getObservations(),
            'statut' => $maintenance->getStatut(),
            'alerteCoutEleve' => $this->isCoutAboveValeurAcquisition($maintenance),
            'piecesJointes' => $this->buildPiecesJointes($maintenance),
            'createdAt' => $maintenance->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function buildList(array $maintenances): array
    {
        return array_map([$this, 'buildDetail'], $maintenances);
    }

    /**
     * Le coût de la maintenance dépasse-t-il la valeur des biens concernés ?
     * AssetMaintenance étant ManyToMany avec Asset : 0 bien -> pas de comparaison possible
     * (false) ; 1 bien -> comparaison directe ; plusieurs biens -> comparaison contre la
     * somme de leurs valeurs (coût total vs valeur combinée).
     */
    private function isCoutAboveValeurAcquisition(AssetMaintenance $maintenance): bool
    {
        $cout = $maintenance->getCout();
        if (!is_numeric($cout)) {
            return false;
        }

        $assets = $maintenance->getAssets();
        if ($assets->isEmpty()) {
            return false;
        }

        $valeurTotale = 0.0;
        foreach ($assets as $asset) {
            $valeur = $asset->getValeur();
            if (!is_numeric($valeur)) {
                return false;
            }
            $valeurTotale += (float) $valeur;
        }

        return (float) $cout > $valeurTotale;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPiecesJointes(AssetMaintenance $maintenance): array
    {
        return $maintenance->getPieceJointes()->map(function (PieceJointe $piece) {
            return [
                'id' => $piece->getId(),
                'nom' => $piece->getNom(),
                'chemin' => $piece->getChemin(),
            ];
        })->toArray();
    }
}
