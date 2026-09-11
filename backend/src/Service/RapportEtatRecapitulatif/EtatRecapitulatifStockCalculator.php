<?php

namespace App\Service\RapportEtatRecapitulatif;

use Doctrine\DBAL\Connection;

/**
 * Service de calcul des stocks pour l'État Récapitulatif Mensuel.
 * 
 * Responsabilités :
 * - Calculer le stock initial (avant la période)
 * - Calculer les entrées sur une période
 * - Calculer les sorties sur une période
 * - Calculer le stock final
 */
final class EtatRecapitulatifStockCalculator
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * ================================================================
     * 📍 BLOC 1 : calculateStockForConsumableAtDate()
     * ================================================================
     * 
     * 🎯 RÔLE : Calcule le stock PENDANT une période (comme entrees/sorties)
     * 
     * � UTILISÉ POUR :
     * - stockAuEntre (stock pendant période)
     * 
     * 🔍 LOGIQUE DE LA MÉTHODE :
     * 1. Récupère les ENTRIES pendant la période → $totalEntries
     * 2. Récupère les TRANSFERT reçus pendant la période → $totalEntrees
     * 
     * FORMULE : Stock = ENTRIES + TRANSFERT_REÇUS
     */
    public function calculateStockForConsumableAtDate(
        int $consumableId,
        ?array $serviceIds = null,
        \DateTimeInterface $periodeDebut,
        \DateTimeInterface $periodeFin
    ): array {
        $conn = $this->connection;
        $consumableIdEscaped = (int) $consumableId;
        $debutStr = $periodeDebut->format('Y-m-d');
        $finStr = $periodeFin->format('Y-m-d');
        
        // ----------------------------------------------------------------
        // 📍 SOUS-BLOC 1.1 : Récupération des ENTRIES pendant période
        // ----------------------------------------------------------------
        $sqlEntries = "SELECT COALESCE(SUM(ce.quantite), 0)
                       FROM consumable_entry ce
                       WHERE ce.consumable_id = {$consumableIdEscaped}
                       AND ce.is_delete = 0
                       AND ce.date_entree >= '{$debutStr}'
                       AND ce.date_entree <= '{$finStr}'";
        
        if ($serviceIds !== null) {
            $serviceIdsEscaped = array_map('intval', $serviceIds);
            $serviceIdsIn = implode(',', $serviceIdsEscaped);
            $sqlEntries .= " AND ce.service_id IN ({$serviceIdsIn})";
        }
        
        $totalEntries = (float) $conn->executeQuery($sqlEntries)->fetchOne();
        
        // ----------------------------------------------------------------
        // 📍 SOUS-BLOC 1.2 : Récupération des TRANSFERT reçus pendant période
        // ----------------------------------------------------------------
        $sqlEntrees = "SELECT COALESCE(SUM(ct.quantite), 0)
                       FROM consumable_transfer ct
                       WHERE ct.consumable_id = {$consumableIdEscaped}
                       AND ct.is_delete = 0
                       AND ct.type = 'TRANSFERT_DIRECT'
                    --    AND ct.statut = 'TRANSFERE'
                       AND ct.date_transfert >= '{$debutStr}'
                       AND ct.date_transfert <= '{$finStr}'";
        
        if ($serviceIds !== null) {
            $serviceIdsEscaped = array_map('intval', $serviceIds);
            $serviceIdsIn = implode(',', $serviceIdsEscaped);
            $sqlEntrees .= " AND ct.service_destination_id IN ({$serviceIdsIn})";
        }


        $sqlBsp = "SELECT COALESCE(SUM(ct.quantite), 0)
                   FROM consumable_transfer ct
                   WHERE ct.consumable_id = {$consumableIdEscaped}
                   AND ct.is_delete = 0
                   AND ct.type = 'BSP'
                --    AND ct.statut = 'SORTI'  // ⚠️ COMMENTÉ !
                   AND ct.date_transfert >= '{$debutStr}'
                   AND ct.date_transfert <= '{$finStr}'";
        
        if ($serviceIds !== null) {
            $serviceIdsEscaped = array_map('intval', $serviceIds);
            $serviceIdsIn = implode(',', $serviceIdsEscaped);
            $sqlBsp .= " AND ct.service_destination_id IN ({$serviceIdsIn})";
        }
        
        $totalBsp = (float) $conn->executeQuery($sqlBsp)->fetchOne();
        
        $totalEntrees = (float) $conn->executeQuery($sqlEntrees)->fetchOne();
        
        // ----------------------------------------------------------------
        // 📍 SOUS-BLOC 1.3 : CALCUL FINAL DU STOCK
        // ----------------------------------------------------------------
        // FORMULE : Stock = ENTRIES + TRANSFERT_REÇUS
        $quantite = $totalEntrees + $totalBsp;
        
        return ['quantite' => $quantite, 'valeur' => null];
    }

    /**
     * ================================================================
     * 📍 BLOC 2 : calculateEntreesForConsumable()
     * ================================================================
     * 
     * 🎯 RÔLE : Calcule les entrées PENDANT une période
     * 
     * 📊 UTILISÉ POUR :
     * - entrees (dans analyzeConsumable())
     * 
     * 📍 EMPLACEMENT DANS analyzeConsumable() :
     * $entrees = $this->stockCalculator->calculateEntreesForConsumable(
     *     $consumableId, $serviceId, $periodeDebut, $periodeFin
     * );
     * 
     * 🔍 LOGIQUE DE LA MÉTHODE :
     * 1. Récupère les INITIAL créés pendant la période → $totalInitial
     * 2. Récupère les ENTRIES (consumable_entry) pendant la période → $totalEntries
     * 3. Récupère les TRANSFERT reçus pendant la période → $totalEntrees
     * 
     * ⚠️ PROBLÈME IDENTIFIÉ ICI :
     * Ligne ~155 : return ['quantite' => $totalEntries - $totalEntrees, ...];
     * -> Ceci ne prend PAS en compte $totalInitial
     * -> Et soustrait $totalEntrees au lieu d'ajouter !
     * 
     * ✅ Devrait être : $totalInitial + $totalEntries + $totalEntrees
     */
    public function calculateEntreesForConsumable(
        int $consumableId,
        ?array $serviceIds = null,
        \DateTimeInterface $periodeDebut,
        \DateTimeInterface $periodeFin
    ): array {
        $conn = $this->connection;
        $consumableIdEscaped = (int) $consumableId;
        $debutStr = $periodeDebut->format('Y-m-d');
        $finStr = $periodeFin->format('Y-m-d');

        // ----------------------------------------------------------------
        // 📍 SOUS-BLOC 2.1 : Récupération des INITIAL créés pendant période
        // ----------------------------------------------------------------
        // 🎯 RÔLE : Récupère les stocks initiaux créés dans la période
        // 📊 SOURCE : Table consumable_transfer
        // ➕ IMPACT : + quantite (entrée)
        // 📌 UTILISÉ POUR : entrees (pendant période)
        $sqlInitial = "SELECT COALESCE(SUM(ct.quantite), 0)
                       FROM consumable_transfer ct
                       WHERE ct.consumable_id = {$consumableIdEscaped}
                       AND ct.is_delete = 0
                       AND ct.type = 'INITIAL'
                       AND ct.statut = 'INITIAL'
                       AND ct.date_transfert >= '{$debutStr}'
                       AND ct.date_transfert <= '{$finStr}'";
        
        if ($serviceIds !== null) {
            $serviceIdsEscaped = array_map('intval', $serviceIds);
            $serviceIdsIn = implode(',', $serviceIdsEscaped);
            $sqlInitial .= " AND ct.service_destination_id IN ({$serviceIdsIn})";
        }
        
        $totalInitial = (float) $conn->executeQuery($sqlInitial)->fetchOne();
        
        // ----------------------------------------------------------------
        // 📍 SOUS-BLOC 2.2 : Récupération des ENTRIES pendant période
        // ----------------------------------------------------------------
        // 🎯 RÔLE : Récupère les entrées (consumable_entry) dans la période
        // 📊 SOURCE : Table consumable_entry
        // ➕ IMPACT : + quantite (entrée)
        // 📌 UTILISÉ POUR : entrees (pendant période)
        $sqlEntries = "SELECT COALESCE(SUM(ce.quantite), 0)
                       FROM consumable_entry ce
                       WHERE ce.consumable_id = {$consumableIdEscaped}
                       AND ce.is_delete = 0
                       AND ce.date_entree >= '{$debutStr}'
                       AND ce.date_entree <= '{$finStr}'";
        
        if ($serviceIds !== null) {
            $serviceIdsEscaped = array_map('intval', $serviceIds);
            $serviceIdsIn = implode(',', $serviceIdsEscaped);
            $sqlEntries .= " AND ce.service_id IN ({$serviceIdsIn})";
        }
        
        $totalEntries = (float) $conn->executeQuery($sqlEntries)->fetchOne();
        
        // ----------------------------------------------------------------
        // 📍 SOUS-BLOC 2.3 : Récupération des TRANSFERT reçus pendant période
        // ----------------------------------------------------------------
        // 🎯 RÔLE : Récupère les transferts reçus dans la période
        // 📊 SOURCE : Table consumable_transfer
        // ➕ IMPACT : + quantite (entrée)
        // 📌 UTILISÉ POUR : entrees (pendant période)
        $sqlEntrees = "SELECT COALESCE(SUM(ct.quantite), 0)
                       FROM consumable_transfer ct
                       WHERE ct.consumable_id = {$consumableIdEscaped}
                       AND ct.is_delete = 0
                       AND ct.type = 'TRANSFERT_DIRECT'
                       AND ct.statut = 'TRANSFERE'
                       AND ct.date_transfert >= '{$debutStr}'
                       AND ct.date_transfert <= '{$finStr}'";
        
        if ($serviceIds !== null) {
            $serviceIdsEscaped = array_map('intval', $serviceIds);
            $serviceIdsIn = implode(',', $serviceIdsEscaped);
            $sqlEntrees .= " AND ct.service_destination_id IN ({$serviceIdsIn})";
        }
        
        
        $totalEntrees = (float) $conn->executeQuery($sqlEntrees)->fetchOne();


        $sqlBsp = "SELECT COALESCE(SUM(ct.quantite), 0)
                   FROM consumable_transfer ct
                   WHERE ct.consumable_id = {$consumableIdEscaped}
                   AND ct.is_delete = 0
                   AND ct.type = 'BSP'
                --    AND ct.statut = 'SORTI'  // ⚠️ COMMENTÉ !
                   AND ct.date_transfert >= '{$debutStr}'
                   AND ct.date_transfert <= '{$finStr}'";
        
        if ($serviceIds !== null) {
            $serviceIdsEscaped = array_map('intval', $serviceIds);
            $serviceIdsIn = implode(',', $serviceIdsEscaped);
            $sqlBsp .= " AND ct.service_destination_id IN ({$serviceIdsIn})";
        }
        
        $totalBsp = (float) $conn->executeQuery($sqlBsp)->fetchOne();
        
        // ----------------------------------------------------------------
        // 📍 SOUS-BLOC 2.4 : CALCUL FINAL DES ENTREES
        // ----------------------------------------------------------------
        // ⚠️⚠️⚠️ PROBLÈME ICI ⚠️⚠️⚠️
        // Actuellement : 'quantite' => $totalEntries - $totalEntrees
        // -> $totalInitial n'est PAS inclus !
        // -> $totalEntrees est SOUSTRAIT au lieu d'être AJOUTÉ !
        // 
        // ✅ Devrait être : $totalInitial + $totalEntries + $totalEntrees
        // 
        // FORMULE : ENTREES = INITIAL + ENTRIES + TRANSFERT_REÇUS
        // 
        // 📌 C'EST ICI QUE entrees (5156) est calculé !
        return [
            'quantite' => $totalEntries - $totalBsp,
            // 'quantite' => $totalEntrees,
            'valeur' => null
        ];



        // 2. ENTRIES (consumable_entry) pendant période → + quantite
    $sqlEntries = "SELECT COALESCE(SUM(ce.quantite), 0)
                   FROM consumable_entry ce
                   WHERE ce.consumable_id = {$consumableIdEscaped}
                   AND ce.is_delete = 0
                   AND ce.date_entree >= '{$debutStr}'
                   AND ce.date_entree <= '{$finStr}'";
    
    if ($serviceIds !== null && !empty($serviceIds)) {
        $serviceIdsEscaped = array_map('intval', $serviceIds);
        $serviceIdsIn = implode(',', $serviceIdsEscaped);
        $sqlEntries .= " AND ce.service_id IN ({$serviceIdsIn})";
    }
    
    $totalEntries = (float) $conn->executeQuery($sqlEntries)->fetchOne();

    // 3. TRANSFERT reçus pendant période → + quantite
    $sqlEntrees = "SELECT COALESCE(SUM(ct.quantite), 0)
                   FROM consumable_transfer ct
                   WHERE ct.consumable_id = {$consumableIdEscaped}
                   AND ct.is_delete = 0
                   AND ct.type = 'TRANSFERT_DIRECT'
                   AND ct.statut = 'TRANSFERE'
                   AND ct.date_transfert >= '{$debutStr}'
                   AND ct.date_transfert <= '{$finStr}'";
    
    if ($serviceIds !== null && !empty($serviceIds)) {
        $serviceIdsEscaped = array_map('intval', $serviceIds);
        $serviceIdsIn = implode(',', $serviceIdsEscaped);
        $sqlEntrees .= " AND ct.service_destination_id IN ({$serviceIdsIn})";
    }
    
    $totalEntrees = (float) $conn->executeQuery($sqlEntrees)->fetchOne();

    // ✅ Entrées = INITIAL + ENTRIES + TRANSFERT_REÇUS
    return [
        'quantite' => $totalInitial + $totalEntries + $totalEntrees,
        'valeur' => null
    ];
    }

    /**
     * ================================================================
     * 📍 BLOC 3 : calculateSortiesForConsumable()
     * ================================================================
     * 
     * 🎯 RÔLE : Calcule les sorties PENDANT une période
     * 
     * 📊 UTILISÉ POUR :
     * - sorties (dans analyzeConsumable())
     * 
     * 📍 EMPLACEMENT DANS analyzeConsumable() :
     * $sorties = $this->stockCalculator->calculateSortiesForConsumable(
     *     $consumableId, $serviceId, $periodeDebut, $periodeFin
     * );
     * 
     * 🔍 LOGIQUE DE LA MÉTHODE :
     * 1. Récupère les BSP pendant la période → $totalBsp
     * 2. Récupère les TRANSFERT envoyés pendant la période → $totalTransfer
     * 
     * ✅ Logique correcte ici !
     * 
     * ⚠️ Mais filtre 'statut = SORTI' est COMMENTÉ (ligne ~205)
     * ⚠️ Et filtre 'service_source_id IS NOT NULL' est COMMENTÉ (ligne ~216)
     */
    public function calculateSortiesForConsumable(
        int $consumableId,
        ?array $serviceIds = null,
        \DateTimeInterface $periodeDebut,
        \DateTimeInterface $periodeFin
    ): array {
        $conn = $this->connection;
        $consumableIdEscaped = (int) $consumableId;
        $debutStr = $periodeDebut->format('Y-m-d');
        $finStr = $periodeFin->format('Y-m-d');

        // ----------------------------------------------------------------
        // 📍 SOUS-BLOC 3.1 : Récupération des BSP pendant période
        // ----------------------------------------------------------------
        // 🎯 RÔLE : Récupère les bonnes de sortie dans la période
        // 📊 SOURCE : Table consumable_transfer
        // ➖ IMPACT : - quantite (sortie)
        // 🔗 FILTRE : type = 'BSP'
        // ⚠️ statut = 'SORTI' est COMMENTÉ ! (ligne 205)
        // 📌 UTILISÉ POUR : sorties (pendant période)
        $sqlBsp = "SELECT COALESCE(SUM(ct.quantite), 0)
                   FROM consumable_transfer ct
                   WHERE ct.consumable_id = {$consumableIdEscaped}
                   AND ct.is_delete = 0
                   AND ct.type = 'BSP'
                --    AND ct.statut = 'SORTI'  // ⚠️ COMMENTÉ !
                   AND ct.date_transfert >= '{$debutStr}'
                   AND ct.date_transfert <= '{$finStr}'";
        
        if ($serviceIds !== null) {
            $serviceIdsEscaped = array_map('intval', $serviceIds);
            $serviceIdsIn = implode(',', $serviceIdsEscaped);
            $sqlBsp .= " AND ct.service_destination_id IN ({$serviceIdsIn})";
        }
        
        $totalBsp = (float) $conn->executeQuery($sqlBsp)->fetchOne();
        
        // ----------------------------------------------------------------
        // 📍 SOUS-BLOC 3.2 : Récupération des TRANSFERT envoyés pendant période
        // ----------------------------------------------------------------
        // 🎯 RÔLE : Récupère les transferts envoyés dans la période
        // 📊 SOURCE : Table consumable_transfer
        // ➖ IMPACT : - quantite (sortie)
        // 🔗 FILTRE : type = 'TRANSFERT_DIRECT', statut = 'TRANSFERE'
        // ⚠️ service_source_id IS NOT NULL est COMMENTÉ ! (ligne 216)
        // 📌 UTILISÉ POUR : sorties (pendant période)
        $sqlTransfer = "SELECT COALESCE(SUM(ct.quantite), 0)
                        FROM consumable_transfer ct
                        WHERE ct.consumable_id = {$consumableIdEscaped}
                        AND ct.is_delete = 0
                        AND ct.type = 'TRANSFERT_DIRECT'
                        AND ct.statut = 'TRANSFERE'
                        -- AND ct.service_source_id IS NOT NULL  // ⚠️ COMMENTÉ !
                        AND ct.date_transfert >= '{$debutStr}'
                        AND ct.date_transfert <= '{$finStr}'";
        
        if ($serviceIds !== null) {
            $serviceIdsEscaped = array_map('intval', $serviceIds);
            $serviceIdsIn = implode(',', $serviceIdsEscaped);
            $sqlTransfer .= " AND ct.service_source_id IN ({$serviceIdsIn})";
        }
        
        $totalTransfer = (float) $conn->executeQuery($sqlTransfer)->fetchOne();
        
        // ----------------------------------------------------------------
        // 📍 SOUS-BLOC 3.3 : CALCUL FINAL DES SORTIES
        // ----------------------------------------------------------------
        // ✅ Logique correcte ici !
        // FORMULE : SORTIES = BSP + TRANSFERT_ENVOYÉS
        // 
        // 📌 C'EST ICI QUE sorties (156) est calculé !
        return [
            // 'quantite' =>  $totalTransfer,
            'quantite' => $totalBsp + $totalTransfer,
            'valeur' => null
        ];
    }
}