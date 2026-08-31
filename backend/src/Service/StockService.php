<?php

namespace App\Service;

use App\Entity\Asset;
use App\Repository\AssetAssignmentRepository;
use App\Repository\AssetMaintenanceRepository;
use App\Repository\AssetExitRepository;
use App\Repository\BspRepository;
use App\Repository\StockRepository;

/**
 * Service pour l'analyse du stock patrimonial (read-only).
 * Encapsule la logique métier de calcul de la situation des biens.
 */
final class StockService
{
    public const SITUATION_DISPONIBLE = 'DISPONIBLE';
    public const SITUATION_AFFECTE = 'AFFECTE';
    public const SITUATION_MAINTENANCE = 'MAINTENANCE';
    public const SITUATION_SORTIE_TEMPORAIRE = 'SORTIE_TEMPORAIRE';
    public const SITUATION_RETOURNE = 'RETOURNE';
    public const SITUATION_SORTI_DEFINITIVEMENT = 'SORTI_DEFINITIVEMENT';

    public function __construct(
        private readonly StockRepository $stockRepository,
        private readonly AssetAssignmentRepository $assignmentRepository,
        private readonly AssetMaintenanceRepository $maintenanceRepository,
        private readonly AssetExitRepository $exitRepository,
        private readonly BspRepository $bspRepository
    ) {
    }

    /**
     * Calcule la situation d'un bien
     */
    public function calculateAssetSituation(Asset $asset): string
    {
        return $this->stockRepository->calculateAssetSituation($asset->getId());
    }

    /**
     * Récupère les biens avec leur situation calculée
     */
    // public function getAssetsWithSituation(int $page, int $limit, ?array $filters = null): array
    // {
    //     return $this->stockRepository->findAssetsWithSituation($page, $limit, $filters);
    // }

    public function getAssetsWithSituation(int $page, int $limit, ?array $filters = null): array
{
    return $this->stockRepository->findByAssetsWithSituation($page, $limit, $filters);
}

    /**
     * Compte les biens avec filtres
     */
    // public function countAssetsWithSituation(?array $filters = null): int
    // {
    //     return $this->stockRepository->countAssetsWithSituation($filters);
    // }
    public function countAssetsWithSituation(?array $filters = null): int
{
    return $this->stockRepository->countByAssetsWithSituation($filters);
}
    /**
     * Récupère le résumé du stock par situation
     */
    public function getStockSummary(?array $filters = null): array
    {
        return $this->stockRepository->countBySituation($filters);
    }

    /**
     * Récupère les affectations par utilisateur et service
     */
    public function getAssignments(?array $filters = null): array
{
    return $this->stockRepository->countByAssignments($filters);
}

    /**
     * Récupère les biens par statut (actif/inactif)
     */
    public function getStatuts(?array $filters = null): array
    {
        return $this->stockRepository->countByStatut($filters);
    }

    /**
     * Récupère les biens par projet
     */
    public function getProjects(?array $filters = null): array
    {
        return $this->stockRepository->countByProject($filters);
    }

    /**
     * Récupère l'historique des mouvements d'un bien
     */
    public function getAssetHistory(int $assetId): array
    {
        return $this->stockRepository->findAssetHistory($assetId);
    }

    /**
     * Récupère les mouvements globaux
     */
    public function getGlobalMovements(
        int $page,
        int $limit,
        ?string $type = null,
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null
    ): array {
        return $this->stockRepository->findGlobalMovements($page, $limit, $type, $dateDebut, $dateFin);
    }

    /**
     * Compte les mouvements globaux
     */
    public function countGlobalMovements(
        ?string $type = null,
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null
    ): int {
        return $this->stockRepository->countByGlobalMovements($type, $dateDebut, $dateFin);
    }

    /**
     * Récupère l'affectation active d'un bien
     */
    public function getActiveAssignment(int $assetId): ?array
    {
        $assignment = $this->assignmentRepository->findActiveByAsset($assetId);
        if (!$assignment) {
            return null;
        }

        return [
            'id' => $assignment->getId(),
            // 'type_affectation' => $assignment->getTypeAffectation(),
            'date_debut' => $assignment->getDateDebut()?->format('Y-m-d'),
            'date_fin' => $assignment->getDateFin()?->format('Y-m-d'),
            'commentaire' => $assignment->getCommentaire(),
            'user' => $assignment->getUser() ? [
                'id' => $assignment->getUser()->getId(),
                'nom' => $assignment->getUser()->getLastName(),
                'prenom' => $assignment->getUser()->getFirstName(),
                'matricule' => $assignment->getUser()->getMatricule(),
                'service' => $assignment->getUser()->getService() ? [
                    'id' => $assignment->getUser()->getService()->getId(),
                    'nom' => $assignment->getUser()->getService()->getNom(),
                    'sigle' => $assignment->getUser()->getService()->getSigle(),
                ] : null,
            ] : null,
            'service' => $assignment->getService() ? [
                'id' => $assignment->getService()->getId(),
                'nom' => $assignment->getService()->getNom(),
                'sigle' => $assignment->getService()->getSigle(),
            ] : null,
        ];
    }

    /**
     * Récupère la maintenance en cours d'un bien
     */
    public function getOpenMaintenance(int $assetId): ?array
    {
        $asset = $this->stockRepository->find($assetId);  // ← Changé
        if (!$asset) {
            return null;
        }

        $maintenance = $this->maintenanceRepository->findOpenForAsset($asset);
        if (!$maintenance) {
            return null;
        }

        return [
            'id' => $maintenance->getId(),
            'motif' => $maintenance->getMotif(),
            'cout' => $maintenance->getCout(),
            'date_intervention' => $maintenance->getDateIntervention()?->format('Y-m-d'),
            'date_recuperation' => $maintenance->getDateRecuperation()?->format('Y-m-d'),
            'statut' => $maintenance->getStatut(),
            'observations' => $maintenance->getObservations(),
            'etat_bien' => $maintenance->getEtatBien() ? [
                'id' => $maintenance->getEtatBien()->getId(),
                'nom' => $maintenance->getEtatBien()->getNom(),
            ] : null,
        ];
    }

    /**
     * Récupère la sortie définitive d'un bien
     */
    public function getAssetExit(int $assetId): ?array
    {
        $exit = $this->exitRepository->findByAssetId($assetId);
        if (!$exit) {
            return null;
        }

        return [
            'id' => $exit->getId(),
            'date_sortie' => $exit->getDateSortie()?->format('Y-m-d'),
            'motif_sortie' => $exit->getMotifSortie(),
            // 'protocole_reference' => $exit->getProtocoleReference(),
            'observations' => $exit->getObservations(),
            'exit_type' => $exit->getExitType() ? [
                'id' => $exit->getExitType()->getId(),
                'nom' => $exit->getExitType()->getNom(),
            ] : null,
            'service' => $exit->getService() ? [
                'id' => $exit->getService()->getId(),
                'nom' => $exit->getService()->getNom(),
                'sigle' => $exit->getService()->getSigle(),
            ] : null,
            'user' => $exit->getUser() ? [
                'id' => $exit->getUser()->getId(),
                'nom' => $exit->getUser()->getLastName(),
                'prenom' => $exit->getUser()->getFirstName(),
                'matricule' => $exit->getUser()->getMatricule(),
                'service' => $exit->getUser()->getService() ? [
                    'id' => $exit->getUser()->getService()->getId(),
                    'nom' => $exit->getUser()->getService()->getNom(),
                    'sigle' => $exit->getUser()->getService()->getSigle(),
                ] : null,
            ] : null,
        ];
    }

    /**
     * Récupère les BSP d'un bien
     */
    public function getAssetBsp(int $assetId): array
    {
        $exit = $this->exitRepository->findByAssetId($assetId);
        if (!$exit) {
            return [];
        }

        $bsps = $this->bspRepository->findActiveByAssetExitId($exit->getId());
        $result = [];

        foreach ($bsps as $bsp) {
            $result[] = [
                'id' => $bsp->getId(),
                'numero' => $bsp->getNumero(),
                'date_etablissement' => $bsp->getDateEtablissement()?->format('Y-m-d'),
                'date_retour_effective' => $bsp->getDateRetourEffective()?->format('Y-m-d'),
                'retour' => $bsp->isRetour(),
                'quantite_demandee' => $bsp->getQuantiteDemandee(),
                'quantite_accordee' => $bsp->getQuantiteAccordee(),
                'quantite_servie' => $bsp->getQuantiteServie(),
                'observations' => $bsp->getObservations(),
                'service' => $bsp->getService() ? [
                    'id' => $bsp->getService()->getId(),
                    'nom' => $bsp->getService()->getNom(),
                ] : null,
                'beneficiaire' => $bsp->getBeneficiaire() ? [
                    'id' => $bsp->getBeneficiaire()->getId(),
                    'nom' => $bsp->getBeneficiaire()->getLastName(),
                    'prenom' => $bsp->getBeneficiaire()->getFirstName(),
                ] : null,
            ];
        }

        return $result;
    }

    /**
     * Formate les filtres pour la requête
     */
    public function normalizeFilters(array $filters): array
    {
        $normalized = [];

        if (isset($filters['category_id'])) {
            $normalized['category_id'] = (int) $filters['category_id'];
        }

        if (isset($filters['asset_type_id'])) {
            $normalized['asset_type_id'] = (int) $filters['asset_type_id'];
        }

        if (isset($filters['etat_bien_id'])) {
            $normalized['etat_bien_id'] = (int) $filters['etat_bien_id'];
        }

        if (isset($filters['service_id'])) {
            $normalized['service_id'] = (int) $filters['service_id'];
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $normalized['search'] = trim($filters['search']);
        }

        if (isset($filters['situation']) && !empty($filters['situation'])) {
            $normalized['situation'] = strtoupper($filters['situation']);
        }

        if (isset($filters['statut']) && !empty($filters['statut'])) {
            $normalized['statut'] = strtoupper($filters['statut']);
        }

        if (isset($filters['exercice']) && !empty($filters['exercice'])) {
            $normalized['exercice'] = (int) $filters['exercice'];
        }

        return $normalized;
    }

    /**
     * Récupère toutes les données du stock patrimonial de manière unifiée
     */
    public function getStockData(
        int $page = 1,
        int $limit = 20,
        ?array $filters = null
    ): array {
        $normalizedFilters = $this->normalizeFilters($filters ?? []);
        $data = [];

        // Résumé du stock (avec filtres)
        $data['summary'] = $this->getStockSummary($normalizedFilters);
        $data['assignments'] = $this->getAssignments($normalizedFilters);
        $data['statuts'] = $this->getStatuts($normalizedFilters);
        $data['projects'] = $this->getProjects($normalizedFilters);

        // Biens paginés
        $assets = $this->getAssetsWithSituation($page, $limit, $normalizedFilters);
        $total = $this->countAssetsWithSituation($normalizedFilters);
        $data['assets'] = [
            'data' => $assets,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ];

        return $data;
    }
}
