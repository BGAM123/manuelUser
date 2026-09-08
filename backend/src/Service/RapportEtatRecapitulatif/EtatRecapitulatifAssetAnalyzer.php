<?php

namespace App\Service\RapportEtatRecapitulatif;

use App\Entity\Asset;
use App\Entity\AssetType;

/**
 * Analyseur de biens pour l'État Récapitulatif Mensuel.
 * 
 * Responsabilités :
 * - Analyser les biens et les agréger par AssetType
 * - Calculer les stocks, entrées et sorties pour chaque AssetType
 * - Formater les lignes de résultat
 */
final class EtatRecapitulatifAssetAnalyzer
{
    public function __construct(
        private readonly EtatRecapitulatifStockCalculator $stockCalculator,
    ) {}

    /**
     * Analyse les biens et génère les lignes de l'état récapitulatif (une ligne par bien).
     * 
     * @param array<Asset> $assets Biens à analyser
     * @param \DateTimeInterface $periodeDebut Date de début de période
     * @param \DateTimeInterface $periodeFin Date de fin de période
     * @param int|null $serviceId ID du service (optionnel)
     * @return array Lignes de l'état récapitulatif
     */
    public function analyze(
        array $assets,
        \DateTimeInterface $periodeDebut,
        \DateTimeInterface $periodeFin,
        ?int $serviceId = null
    ): array {
        $lines = [];

        // Analyser chaque bien individuellement
        foreach ($assets as $asset) {
            $line = $this->analyzeAsset(
                $asset,
                $periodeDebut,
                $periodeFin,
                $serviceId
            );

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Analyse un bien individuel.
     * 
     * @param Asset $asset Bien à analyser
     * @param \DateTimeInterface $periodeDebut Date de début de période
     * @param \DateTimeInterface $periodeFin Date de fin de période
     * @param int|null $serviceId ID du service (optionnel)
     * @return array Ligne de l'état récapitulatif
     */
    private function analyzeAsset(
        Asset $asset,
        \DateTimeInterface $periodeDebut,
        \DateTimeInterface $periodeFin,
        ?int $serviceId = null
    ): array {
        $assetId = $asset->getId();

        // Prix unitaire : valeur du bien
        $prixUnitaire = $asset->getValeur() ? (float) $asset->getValeur() : 0;
        
        // Prix unitaire initial : valeur initiale du bien
        $prixUnitaireInitial = $asset->getValeurInitiale() ? (float) $asset->getValeurInitiale() : 0;

        // Quantité de stock du bien (null pour un bien unique, renseigné pour un bien de type stock)
        $quantiteStock = $asset->getQuantiteStock() ?: 1;

        // stockAuEntre : stock au début de la période
        $stockAuEntre = [
            'quantite' => $quantiteStock,
            'valeur' => $prixUnitaireInitial,
        ];

        // stockAuSortie : stock à la fin de la période
        $stockAuSortie = [
            'quantite' => $quantiteStock,
            'valeur' => $prixUnitaire,
        ];

        // entrees : entrées pendant la période
        $entrees = $stockAuEntre;

        // sorties : sorties pendant la période
        $sorties = $stockAuSortie;

        return [
            'designation' => $asset->getNom(),
            'prixUnitaire' => $prixUnitaire,
            'stockAuEntre' => $stockAuEntre,
            'stockAuSortie' => $stockAuSortie,
            'entrees' => $entrees,
            'sorties' => $sorties,
        ];
    }
}