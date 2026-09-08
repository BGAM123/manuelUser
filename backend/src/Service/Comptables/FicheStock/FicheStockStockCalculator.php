<?php

namespace App\Service\Comptables\FicheStock;

use App\Entity\ConsumableTransfer;

/**
 * Service de calcul du stock pour la Fiche de Stock.
 * Logique adaptée de ConsumableRepository::getStockActuel() et ConsumableTransferRepository::getCurrentStock().
 * Copie isolée volontairement afin de ne pas modifier le module existant.
 */
final class FicheStockStockCalculator
{
    /**
     * Calcule le stock initial avant une date donnée pour un service et un consommable.
     *
     * @param array $movements Mouvements antérieurs à la période
     * @param int $serviceId ID du service concerné
     * @return float Stock initial
     */
    public function calculateInitialStock(array $movements, int $serviceId): float
    {
        $stock = 0.0;

        foreach ($movements as $movement) {
            $statut = $movement['statut'] ?? null;
            $type = $movement['type'] ?? null;
            $quantite = (float) ($movement['quantite'] ?? 0);
            $serviceSource = $movement['service_source_id'] ?? null;
            $serviceDestination = $movement['service_destination_id'] ?? null;
            $quantityConsumed = (float) ($movement['quantity_consumed'] ?? 0);

            // Entrée initiale (ConsumableEntry)
            if ($type === 'ENTRY' && $serviceDestination === $serviceId) {
                $stock += $quantite;
            }
            // Transfert reçu (entrée pour destination)
            elseif ($statut === ConsumableTransfer::STATUT_TRANSFERE && $serviceDestination === $serviceId) {
                $stock += $quantite;
                $stock -= $quantityConsumed;
            }
            // Transfert envoyé (sortie pour source)
            elseif ($statut === ConsumableTransfer::STATUT_TRANSFERE && $serviceSource === $serviceId) {
                $stock -= $quantite;
            }
            // Sortie BSP (sortie pour source)
            elseif ($statut === ConsumableTransfer::STATUT_SORTI && $serviceSource === $serviceId) {
                $stock -= $quantite;
            }
        }

        return max(0.0, $stock);
    }

    /**
     * Calcule le stock évolutif en appliquant les mouvements de la période.
     *
     * @param float $initialStock Stock initial
     * @param array $periodMovements Mouvements de la période
     * @param int $serviceId ID du service concerné
     * @return array Liste des stocks après chaque mouvement
     */
    public function calculateEvolutionaryStock(float $initialStock, array $periodMovements, int $serviceId): array
    {
        $currentStock = $initialStock;
        $stocks = [];

        foreach ($periodMovements as $movement) {
            $statut = $movement['statut'] ?? null;
            $type = $movement['type'] ?? null;
            $quantite = (float) ($movement['quantite'] ?? 0);
            $serviceSource = $movement['service_source_id'] ?? null;
            $serviceDestination = $movement['service_destination_id'] ?? null;
            $quantityConsumed = (float) ($movement['quantity_consumed'] ?? 0);

            // Entrée initiale (ConsumableEntry)
            if ($type === 'ENTRY' && $serviceDestination === $serviceId) {
                $currentStock += $quantite;
            }
            // Transfert reçu (entrée pour destination)
            elseif ($statut === ConsumableTransfer::STATUT_TRANSFERE && $serviceDestination === $serviceId) {
                $currentStock += $quantite;
                $currentStock -= $quantityConsumed;
            }
            // Transfert envoyé (sortie pour source)
            elseif ($statut === ConsumableTransfer::STATUT_TRANSFERE && $serviceSource === $serviceId) {
                $currentStock -= $quantite;
            }
            // Sortie BSP (sortie pour source)
            elseif ($statut === ConsumableTransfer::STATUT_SORTI && $serviceSource === $serviceId) {
                $currentStock -= $quantite;
            }

            $stocks[] = max(0.0, $currentStock);
        }

        return $stocks;
    }

    /**
     * Détermine si un mouvement est une entrée pour le service donné.
     */
    public function isEntry(array $movement, int $serviceId): bool
    {
        $type = $movement['type'] ?? null;
        $statut = $movement['statut'] ?? null;
        $serviceDestination = $movement['service_destination_id'] ?? null;

        if ($type === 'ENTRY' && $serviceDestination === $serviceId) {
            return true;
        }

        if ($statut === ConsumableTransfer::STATUT_TRANSFERE && $serviceDestination === $serviceId) {
            return true;
        }

        return false;
    }

    /**
     * Détermine si un mouvement est une sortie pour le service donné.
     */
    public function isExit(array $movement, int $serviceId): bool
    {
        $statut = $movement['statut'] ?? null;
        $serviceSource = $movement['service_source_id'] ?? null;

        if ($statut === ConsumableTransfer::STATUT_TRANSFERE && $serviceSource === $serviceId) {
            return true;
        }

        if ($statut === ConsumableTransfer::STATUT_SORTI && $serviceSource === $serviceId) {
            return true;
        }

        return false;
    }

    /**
     * Calcule la quantité d'entrée pour un mouvement.
     */
    public function getEntryQuantity(array $movement): float
    {
        return (float) ($movement['quantite'] ?? 0);
    }

    /**
     * Calcule la quantité de sortie pour un mouvement.
     */
    public function getExitQuantity(array $movement): float
    {
        return (float) ($movement['quantite'] ?? 0);
    }

    /**
     * Retourne l'origine ou destination du mouvement.
     */
    public function getOriginDestination(array $movement, int $serviceId): ?string
    {
        $type = $movement['type'] ?? null;
        $statut = $movement['statut'] ?? null;
        $serviceSource = $movement['service_source_nom'] ?? null;
        $serviceDestination = $movement['service_destination_nom'] ?? null;

        // Pour une entrée, retourner l'origine
        if ($this->isEntry($movement, $serviceId)) {
            if ($type === 'ENTRY') {
                return 'Entrée initiale';
            }
            return $serviceSource;
        }

        // Pour une sortie, retourner la destination
        if ($this->isExit($movement, $serviceId)) {
            return $serviceDestination;
        }

        return null;
    }
}
