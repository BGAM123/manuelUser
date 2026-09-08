<?php

namespace App\Service\Comptables\LivreJournal;

use App\Entity\Asset;
use App\Entity\AssetAssignment;
use App\Entity\AssetExit;
use App\Repository\AssetRepository;
use App\Repository\AssetAssignmentRepository;
use App\Repository\AssetExitRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Analyseur pour les biens (Assets) dans le Livre Journal.
 * 
 * Responsabilités :
 * - Analyser les mouvements d'entrée (affectations, restitutions, transferts)
 * - Analyser les mouvements de sortie (AssetExit, statut SORTIS)
 * - Déterminer le service concerné pour origine/destination
 */
final class LivreJournalAssetAnalyzer
{
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly AssetAssignmentRepository $assetAssignmentRepository,
        private readonly AssetExitRepository $assetExitRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Analyse un bien et construit les données du Livre Journal.
     * 
     * @param Asset $asset Le bien à analyser
     * @return array Données du Livre Journal pour ce bien
     */
    public function analyze(Asset $asset): array
    {
        // Récupérer la catégorie pour le numéro d'ordre
        $category = $asset->getCategories()->first();
        $numeroOrdreClasse = $category && !$category->isDelete() ? $category->getOrdre() : null;

        // Déterminer le service pour origine/destination
        $service = $this->determineService($asset);
        $serviceNom = $service ? $service->getNom() : null;

        // Analyser les entrées
        $entree = $this->analyzeEntree($asset);

        // Analyser les sorties
        $sortie = $this->analyzeSortie($asset);

        // Prix unitaire = valeur du bien
        $prixUnitaire = $asset->getValeur() ? (float) $asset->getValeur() : null;

        // Date = createdAt
        $date = $asset->getCreatedAt() ? $asset->getCreatedAt()->format('Y-m-d') : null;

        return [
            'type' => 'BIEN',
            'date' => $date,
            'origineDestination' => $serviceNom,
            'numeroOrdreClasse' => $numeroOrdreClasse,
            'designation' => $asset->getNom(),
            'uniteMesure' => $asset->getUnite_mesure(),
            'prixUnitaire' => $prixUnitaire,
            'entree' => $entree,
            'sortie' => $sortie,
            'observations' => '',
            'assetId' => $asset->getId(),
        ];
    }

    /**
     * Détermine le service concerné pour un bien.
     * 
     * Priorité :
     * 1. Service de l'affectation actuelle (sans date de fin)
     * 2. Service de la sortie si le bien est sorti
     * 3. Service du projet si aucun service d'affectation
     * 4. Service de restitution si applicable
     * 
     * @param Asset $asset Le bien concerné
     * @return Service|null Le service déterminé
     */
    private function determineService(Asset $asset)
    {
        // Chercher l'affectation actuelle (sans date de fin)
        $assignments = $asset->getAssignments();
        foreach ($assignments as $assignment) {
            if (!$assignment->isDelete() && null === $assignment->getDateFin()) {
                // Si c'est une affectation, le service est celui de l'affectation
                if ($assignment->getTypeAffectation() === 'AFFECTATION') {
                    return $assignment->getService();
                }
                // Si c'est une restitution, le service est celui de la restitution
                if ($assignment->getTypeAffectation() === 'RESTITUTION') {
                    return $assignment->getService();
                }
            }
        }

        // Si le bien est sorti, utiliser le service de la sortie
        $sortie = $asset->getSortie();
        if ($sortie && !$sortie->isDelete()) {
            return $sortie->getService();
        }

        // Sinon, utiliser le service du premier projet
        if (!$asset->getProjects()->isEmpty()) {
            $project = $asset->getProjects()->first();
            // Le projet n'a pas directement de service, mais on peut utiliser le service du bien
            // ou retourner null si pas de service direct
        }

        // Chercher un service directement lié au bien
        if (!$asset->getServices()->isEmpty()) {
            return $asset->getServices()->first();
        }

        return null;
    }

    /**
     * Analyse les entrées pour un bien.
     * 
     * Une entrée correspond à :
     * - Une affectation à un utilisateur/service
     * - Une restitution à un service
     * - Un transfert vers un service
     * 
     * @param Asset $asset Le bien concerné
     * @return array Données d'entrée (quantite, valeur)
     */
    private function analyzeEntree(Asset $asset): array
    {
        // Pour un bien, une entrée est généralement = 1
        // On considère qu'il y a une entrée si le bien n'est pas sorti
        $isSorti = $asset->getStatut() === 'SORTIS' || ($asset->getSortie() && !$asset->getSortie()->isDelete());

        if ($isSorti) {
            return [
                'quantite' => 0,
                'valeur' => 0,
            ];
        }

        // Vérifier s'il y a une affectation active
        $hasActiveAssignment = false;
        foreach ($asset->getAssignments() as $assignment) {
            if (!$assignment->isDelete() && null === $assignment->getDateFin()) {
                $hasActiveAssignment = true;
                break;
            }
        }

        if ($hasActiveAssignment) {
            $quantiteStock = $asset->getQuantiteStock() ? (float) $asset->getQuantiteStock() : 0;
            $valeur = $asset->getValeur() ? (float) $asset->getValeur() : 0;
            return [
                'quantite' => $quantiteStock,
                'valeur' => $valeur,
            ];
        }

        // Si aucune affectation active, pas d'entrée comptabilisée
        return [
            'quantite' => 0,
            'valeur' => 0,
        ];
    }

    /**
     * Analyse les sorties pour un bien.
     * 
     * Une sortie correspond à :
     * - Asset.statut = SORTIS
     * - Présence d'une AssetExit
     * 
     * @param Asset $asset Le bien concerné
     * @return array Données de sortie (quantite, valeur)
     */
    private function analyzeSortie(Asset $asset): array
    {
        $isSorti = $asset->getStatut() === 'SORTIS' || ($asset->getSortie() && !$asset->getSortie()->isDelete());

        if (!$isSorti) {
            $quantiteStock = $asset->getQuantiteStock() ? (float) $asset->getQuantiteStock() : 0;
            $valeur = $asset->getValeurInitiale() ? (float) $asset->getValeurInitiale() : 0;
            return [
                'quantite' => $quantiteStock,
                'valeur' => $valeur,
            ];
        }

        $quantiteStock = $asset->getQuantiteStock() ? (float) $asset->getQuantiteStock() : 0;
        $valeur = $asset->getValeurInitiale() ? (float) $asset->getValeurInitiale() : 0;
        return [
            'quantite' => $quantiteStock,
            'valeur' => $valeur,
        ];
    }
}
