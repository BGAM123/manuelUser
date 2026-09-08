<?php

namespace App\Service\Comptables\LivreJournal;

use App\Entity\Asset;
use App\Entity\Consumable;

/**
 * Service pour le calcul de l'imputation budgétaire dans le Livre Journal.
 * 
 * Logique adaptée de InventaireService::buildAssetRow() (ligne 275)
 * Copie isolée volontairement pour éviter de modifier le module existant.
 */
final class LivreJournalImputationService
{
    /**
     * Calcule l'imputation budgétaire pour un bien.
     * 
     * Logique adaptée de InventaireService::buildAssetRow()
     * 'imputation_budgetaire' => $asset->getValeur() ? (float) $asset->getValeur() : null,
     * 
     * @param Asset $asset Le bien concerné
     * @return float|null L'imputation budgétaire (valeur du bien)
     */
    public function calculateForAsset(Asset $asset): ?float
    {
        // Logique adaptée de InventaireService::buildAssetRow()
        return $asset->getValeur() ? (float) $asset->getValeur() : null;
    }

    /**
     * Calcule l'imputation budgétaire pour un consommable.
     * 
     * Pour les consommables, on utilise le prix initial comme référence,
     * similaire à la logique utilisée pour les biens.
     * 
     * @param Consumable $consumable Le consommable concerné
     * @return float|null L'imputation budgétaire (prix initial)
     */
    public function calculateForConsumable(Consumable $consumable): ?float
    {
        // Pour les consommables, on utilise le prix initial
        // Si null, on peut utiliser le prix total divisé par la quantité initiale
        $prixInitial = $consumable->getPrixInitial();
        
        if ($prixInitial) {
            return (float) $prixInitial;
        }
        
        // Fallback : calculer à partir du prix total si disponible
        $prixTotal = $consumable->getPrixTotal();
        $quantite = $consumable->getQuantite();
        
        if ($prixTotal && $quantite && $quantite > 0) {
            return (float) $prixTotal / (float) $quantite;
        }
        
        return null;
    }
}
