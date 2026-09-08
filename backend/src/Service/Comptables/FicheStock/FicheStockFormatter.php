<?php

namespace App\Service\Comptables\FicheStock;

use App\Entity\Consumable;
use App\Entity\Service;

/**
 * Formate les données de la fiche de stock selon la structure demandée.
 */
final class FicheStockFormatter
{
    /**
     * Formate l'en-tête global de la réponse.
     *
     * @return array En-tête formaté
     */
    public function formatHeader(): array
    {
        return [
            'service' => null,
            'numeroNomenclature' => null,
            'designation' => null,
            'codeMateriel' => null,
            'especeUnite' => null,
            'prixUnitaire' => null,
        ];
    }

    /**
     * Formate les lignes de mouvements de la fiche de stock.
     *
     * @param array $movements Mouvements de la période
     * @param array $stocks Stocks calculés
     * @param int $serviceId ID du service
     * @param FicheStockStockCalculator $calculator Calculateur de stock
     * @return array Lignes formatées
     */
    public function formatMovements(array $movements, array $stocks, int $serviceId, FicheStockStockCalculator $calculator): array
    {
        $lines = [];

        foreach ($movements as $index => $movement) {
            $isEntry = $calculator->isEntry($movement, $serviceId);
            $isExit = $calculator->isExit($movement, $serviceId);
            $entrees = $isEntry ? $calculator->getEntryQuantity($movement) : 0;
            $sorties = $isExit ? $calculator->getExitQuantity($movement) : 0;
            $enStock = $stocks[$index] ?? 0;
            $stockInitial = $index === 0 ? $enStock - $entrees + $sorties : null;

            $lines[] = [
                'date' => $movement['date'] ?? '',
                'origineDestination' => $calculator->getOriginDestination($movement, $serviceId),
                'stockInitial' => $stockInitial,
                'quantites' => [
                    'entrees' => $entrees,
                    'sorties' => $sorties,
                    'enStock' => $enStock,
                ],
                'numeroBlBsp' => null,
                'observations' => '',
            ];
        }

        return $lines;
    }

    /**
     * Formate une fiche de stock complète.
     *
     * @param Consumable $consumable Consommable
     * @param Service $service Service
     * @param array $movements Lignes de mouvements
     * @return array Fiche complète
     */
    public function formatFiche(Consumable $consumable, Service $service, array $movements): array
    {
        return [
            'consumable' => [
                'id' => $consumable->getId(),
                // 'nom' => $consumable->getNom(),
                // 'unite_mesure' => $consumable->getUnite_mesure(),
            ],
            'service' => [
                'id' => $service->getId(),
                'nom' => $service->getNom(),
            ],
            'movements' => $movements,
        ];
    }

    /**
     * Formate la réponse complète avec pagination.
     *
     * @param array $fiches Liste des fiches
     * @param int $page Page actuelle
     * @param int $limit Limite par page
     * @param int $total Nombre total de fiches
     * @return array Réponse formatée
     */
    public function formatResponse(array $fiches, int $page, int $limit, int $total): array
    {
        $totalPages = (int) ceil($total / $limit);

        return [
            'header' => $this->formatHeader(),
            'fiches' => $fiches,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ];
    }
}
