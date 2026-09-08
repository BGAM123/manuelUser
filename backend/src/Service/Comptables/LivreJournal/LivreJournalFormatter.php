<?php

namespace App\Service\Comptables\LivreJournal;

/**
 * Formatter pour structurer les données du Livre Journal.
 * 
 * Responsabilités :
 * - Formater chaque ligne selon la structure attendue
 * - Calculer les imputations budgétaires
 * - Assurer la cohérence des données
 */
final class LivreJournalFormatter
{
    public function __construct(
        private readonly LivreJournalImputationService $imputationService,
    ) {}

    /**
     * Formate une ligne du Livre Journal.
     * 
     * @param array $rawData Données brutes de l'analyseur
     * @return array Données formatées pour la réponse
     */
    public function formatLine(array $rawData): array
    {
        $type = $rawData['type'];
        $prixUnitaire = $rawData['prixUnitaire'];
        
        // Calculer l'imputation budgétaire
        $imputationBudgetaire = null;
        if ($prixUnitaire !== null) {
            $imputationBudgetaire = $prixUnitaire;
        }

        return [
            'type' => $type,
            'id' => $rawData['type'] === 'BIEN' ? $rawData['assetId'] : $rawData['consumableId'],
            'numeroOrdre' => null, // Sera assigné après tri et pagination
            'date' => $rawData['date'],
            'origineDestination' => $rawData['origineDestination'],
            'imputationBudgetaire' => $imputationBudgetaire,
            'numeroOrdreClasse' => $rawData['numeroOrdreClasse'],
            'designation' => $rawData['designation'],
            'uniteMesure' => $rawData['uniteMesure'],
            'prixUnitaire' => $prixUnitaire,
            'entree' => [
                'quantite' => $rawData['entree']['quantite'],
                'valeur' => $rawData['entree']['valeur'],
            ],
            'sortie' => [
                'quantite' => $rawData['sortie']['quantite'],
                'valeur' => $rawData['sortie']['valeur'],
            ],
            'observations' => $rawData['observations'],
        ];
    }

    /**
     * Applique la numérotation finale après tri et pagination.
     * 
     * @param array $lines Lignes du Livre Journal
     * @param int $startOffset Offset de départ pour la numérotation
     * @return array Lignes numérotées
     */
    public function applyNumerotation(array $lines, int $startOffset = 0): array
    {
        foreach ($lines as $index => $line) {
            $lines[$index]['numeroOrdre'] = $startOffset + $index + 1;
        }

        return $lines;
    }
}
