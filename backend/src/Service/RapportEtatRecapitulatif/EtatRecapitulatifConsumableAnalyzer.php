<?php

namespace App\Service\RapportEtatRecapitulatif;

use App\Entity\Consumable;

/**
 * Analyseur de consommables pour l'État Récapitulatif Mensuel.
 * 
 * Responsabilités :
 * - Analyser les consommables
 * - Calculer les stocks, entrées et sorties pour chaque consommable
 * - Formater les lignes de résultat
 */
final class EtatRecapitulatifConsumableAnalyzer
{
    public function __construct(
        private readonly EtatRecapitulatifStockCalculator $stockCalculator,
    ) {}

    /**
     * Analyse les consommables et génère les lignes de l'état récapitulatif.
     * 
     * @param array<Consumable> $consumables Consommables à analyser
     * @param \DateTimeInterface $periodeDebut Date de début de période
     * @param \DateTimeInterface $periodeFin Date de fin de période
     * @param array|null $serviceIds IDs des services (optionnel, tableau d'entiers)
     * @return array Lignes de l'état récapitulatif
     */
    public function analyze(
        array $consumables,
        \DateTimeInterface $periodeDebut,
        \DateTimeInterface $periodeFin,
        ?array $serviceIds = null
    ): array {
        $lines = [];

        foreach ($consumables as $consumable) {
            $line = $this->analyzeConsumable($consumable, $periodeDebut, $periodeFin, $serviceIds);
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Analyse un consommable individuel.
     * 
     * @param Consumable $consumable Consommable à analyser
     * @param \DateTimeInterface $periodeDebut Date de début de période
     * @param \DateTimeInterface $periodeFin Date de fin de période
     * @param array|null $serviceIds IDs des services (optionnel, tableau d'entiers)
     * @return array Ligne de l'état récapitulatif
     */
    private function analyzeConsumable(
        Consumable $consumable,
        \DateTimeInterface $periodeDebut,
        \DateTimeInterface $periodeFin,
        ?array $serviceIds = null
    ): array {
        $consumableId = $consumable->getId();

        // Prix unitaire
        $prixUnitaire = $consumable->getPrixInitial() ? (float) $consumable->getPrixInitial() : 0;

        // 1. Stock pendant la période ($periodeDebut à $periodeFin)
        $stockAuEntre = $this->stockCalculator->calculateStockForConsumableAtDate(
            $consumableId,
            $serviceIds,
            $periodeDebut,
            $periodeFin
        );
        $stockAuEntre['valeur'] = $stockAuEntre['quantite'] * $prixUnitaire;

        // 2. Entrées pendant la période ($periodeDebut à $periodeFin)
        $entrees = $this->stockCalculator->calculateEntreesForConsumable(
            $consumableId,
            $serviceIds,
            $periodeDebut,
            $periodeFin
        );
        $entrees['valeur'] = $entrees['quantite'] * $prixUnitaire;

        // 3. Sorties pendant la période ($periodeDebut à $periodeFin)
        $sorties = $this->stockCalculator->calculateSortiesForConsumable(
            $consumableId,
            $serviceIds,
            $periodeDebut,
            $periodeFin
        );
        $sorties['valeur'] = $sorties['quantite'] * $prixUnitaire;

        // 4. Stock à la fin de la période (après $periodeFin)
        $stockAuSortie = [
            'quantite' => max(0, $stockAuEntre['quantite'] + $entrees['quantite'] - $sorties['quantite']),
            'valeur' => max(0, $stockAuEntre['valeur'] + $entrees['valeur'] - $sorties['valeur'])
        ];

        return [
            'designation' => $consumable->getNom(),
            'prixUnitaire' => $prixUnitaire,
            'stockAuSortie' => $stockAuEntre,
            'stockAuEntre' => $stockAuSortie,
            'entrees' => $entrees,
            'sorties' => $sorties,
        ];
    }
}