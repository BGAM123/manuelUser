<?php

namespace App\Service\Comptables\LivreJournal;

use App\Entity\Consumable;
use App\Entity\ConsumableTransfer;
use App\Repository\ConsumableTransferRepository;

/**
 * Analyseur pour les consommables dans le Livre Journal.
 * 
 * Responsabilités :
 * - Analyser les mouvements d'entrée (transferts vers service)
 * - Analyser les mouvements de sortie (BSP, sorties)
 * - Déterminer le service concerné pour origine/destination
 */
final class LivreJournalConsumableAnalyzer
{
    public function __construct(
        private readonly ConsumableTransferRepository $consumableTransferRepository,
    ) {}

    /**
     * Analyse un consommable et construit les données du Livre Journal.
     * 
     * @param Consumable $consumable Le consommable à analyser
     * @return array Données du Livre Journal pour ce consommable
     */
    public function analyze(Consumable $consumable): array
    {
        // Récupérer la catégorie pour le numéro d'ordre
        $category = $consumable->getCategory();
        $numeroOrdreClasse = $category && !$category->isDelete() ? $category->getOrdre() : null;

        // Déterminer le service pour origine/destination
        $service = $consumable->getService();
        $serviceNom = $service ? $service->getNom() : null;

        // Analyser les entrées (transferts vers service)
        $entree = $this->analyzeEntree($consumable);

        // Analyser les sorties (BSP)
        $sortie = $this->analyzeSortie($consumable);

        // Prix unitaire = prix initial
        $prixUnitaire = $consumable->getPrixInitial() ? (float) $consumable->getPrixInitial() : null;

        // Date = createdAt
        $date = $consumable->getCreatedAt() ? $consumable->getCreatedAt()->format('Y-m-d') : null;

        return [
            'type' => 'CONSOMMABLE',
            'date' => $date,
            'origineDestination' => $serviceNom,
            'numeroOrdreClasse' => $numeroOrdreClasse,
            'designation' => $consumable->getNom(),
            'uniteMesure' => $consumable->getUnite_mesure(),
            'prixUnitaire' => $prixUnitaire,
            'entree' => $entree,
            'sortie' => $sortie,
            'observations' => '',
            'consumableId' => $consumable->getId(),
        ];
    }

    /**
     * Analyse les entrées pour un consommable.
     * 
     * Une entrée correspond à :
     * - Un transfert direct (TRANSFERT_DIRECT) avec statut TRANSFERE
     * - Le service destinataire reçoit la quantité transférée
     * 
     * @param Consumable $consumable Le consommable concerné
     * @return array Données d'entrée (quantite, valeur)
     */
    private function analyzeEntree(Consumable $consumable): array
    {
        // Récupérer les transferts directs vers ce consommable
        $transferts = $this->consumableTransferRepository->findBy([
            'consumable' => $consumable,
            'type' => ConsumableTransfer::TYPE_TRANSFERT_DIRECT,
            'statut' => ConsumableTransfer::STATUT_TRANSFERE,
            'isDelete' => false,
        ]);

        if (empty($transferts)) {
            return [
                'quantite' => 0,
                'valeur' => 0,
            ];
        }

        // Calculer le total des quantités transférées
        $totalQuantite = 0;
        foreach ($transferts as $transfert) {
            $totalQuantite += (float) $transfert->getQuantite();
        }

        // Calculer la valeur = quantité × prix unitaire
        $prixUnitaire = $consumable->getPrixInitial() ? (float) $consumable->getPrixInitial() : 0;
        $valeur = $totalQuantite * $prixUnitaire;

        return [
            'quantite' => $totalQuantite,
            'valeur' => $valeur,
        ];
    }

    /**
     * Analyse les sorties pour un consommable.
     * 
     * Une sortie correspond à :
     * - Un BSP (type BSP) avec statut SORTI
     * - La quantité servie correspond à la sortie
     * 
     * @param Consumable $consumable Le consommable concerné
     * @return array Données de sortie (quantite, valeur)
     */
    private function analyzeSortie(Consumable $consumable): array
    {
        // Récupérer les BSP pour ce consommable
        $transferts = $this->consumableTransferRepository->findBy([
            'consumable' => $consumable,
            'type' => ConsumableTransfer::TYPE_BSP,
            'statut' => ConsumableTransfer::STATUT_SORTI,
            'isDelete' => false,
        ]);

        if (empty($transferts)) {
            return [
                'quantite' => 0,
                'valeur' => 0,
            ];
        }

        // Calculer le total des quantités servies
        // Note: Pour les BSP, la quantité servie est dans l'entité Bsp liée
        // Ici on utilise la quantité du transfert comme approximation
        $totalQuantite = 0;
        foreach ($transferts as $transfert) {
            $quantite = $transfert->getQuantite();
            if ($quantite) {
                $totalQuantite += (float) $quantite;
            }
        }

        // Calculer la valeur = quantité × prix unitaire
        $prixUnitaire = $consumable->getPrixInitial() ? (float) $consumable->getPrixInitial() : 0;
        $valeur = $totalQuantite * $prixUnitaire;

        return [
            'quantite' => $totalQuantite,
            'valeur' => $valeur,
        ];
    }
}
