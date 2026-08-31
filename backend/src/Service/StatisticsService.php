<?php
// src/Service/StatisticsService.php

namespace App\Service;

use App\DTO\StatisticsFilter;
use App\Entity\User;
use App\Repository\StatisticsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class StatisticsService
{
    public function __construct(
        private readonly StatisticsRepository $statisticsRepository,
        private readonly ApiResponseFactory $apiResponseFactory,
        private readonly Security $security,
        private readonly StatisticsExportService $exportService,
    ) {
    }

    public const DASHBOARD_DOMAINES = ['patrimoine', 'vehicules', 'terrains', 'batiments', 'structures', 'suivi'];

    /**
     * Regroupe tous les KPIs (sauf ceux nécessitant un paramètre de recherche précis — voir
     * StatisticsController::dashboard()) en une seule structure, organisée par domaine.
     *
     * Le domaine calculé dépend de `$filter->categorieId` (plus de paramètre `domaines` séparé) :
     * - Pas de catégorie fournie → les 6 domaines.
     * - Une seule catégorie fournie et reconnue (Matériel Roulant / Terrain / Bâtiment, cf.
     *   StatisticsRepository::resolveDomaineParCategorieId) → seul ce domaine est calculé.
     * - Une seule catégorie fournie mais non reconnue (ex. Matériel informatique, Mobilier de
     *   bureau — pas de module dédié), ou plusieurs catégories fournies → repli sur la section
     *   "patrimoine" générale, filtrée par ces catégories via le mécanisme standard.
     */
    public function getDashboard(StatisticsFilter $filter): array
    {
        $actifs = self::DASHBOARD_DOMAINES;
        if (null !== $filter->categorieId) {
            if (1 === count($filter->categorieId)) {
                $domaine = $this->statisticsRepository->resolveDomaineParCategorieId($filter->categorieId[0]);
                $actifs = [$domaine ?? 'patrimoine'];
            } else {
                $actifs = ['patrimoine'];
            }
        }

        $data = [];

        if (in_array('patrimoine', $actifs, true)) {
            $data['patrimoine'] = [
                'vue_globale' => $this->getVueGlobale($filter),
                'repartition_par_categorie' => $this->getRepartitionParCategorie($filter),
                'repartition_par_structure' => $this->getRepartitionParStructure($filter),
                'repartition_par_projet' => $this->getRepartitionParProjet($filter),
                'repartition_par_region' => $this->getRepartitionParRegion($filter),
                'top_services_valeur' => $this->getTopServicesValeur($filter),
                'evolution_mensuelle' => [
                    'patrimoine' => $this->getEvolutionMensuelle($filter, 'patrimoine'),
                    'mouvements' => $this->getEvolutionMensuelle($filter, 'mouvements'),
                    'maintenance' => $this->getEvolutionMensuelle($filter, 'maintenance'),
                ],
                'evolution_gap' => $this->getEvolutionGap($filter),
                'classement_regions' => $this->getClassementRegions($filter),
            ];
        }

        if (in_array('vehicules', $actifs, true)) {
            $data['vehicules'] = [
                'vue_globale' => $this->getVehiculesVueGlobale($filter),
                'repartition_geographique' => $this->getVehiculesRepartitionGeographique($filter),
                'repartition_financement' => $this->getVehiculesRepartitionFinancement($filter),
                'repartition_par_type' => $this->getVehiculesRepartitionParType($filter),
                'anciennete' => $this->getVehiculesAnciennete($filter),
                'croisement_etat_departement' => $this->getVehiculesCroisementEtatDepartement($filter),
                'classement_regions' => $this->getVehiculesClassementRegions($filter),
            ];
        }

        if (in_array('terrains', $actifs, true)) {
            $data['terrains'] = [
                'vue_globale' => $this->getTerrainsVueGlobale($filter),
                'repartition_geographique' => $this->getTerrainsRepartitionGeographique($filter),
                'en_litige' => $this->getTerrainsEnLitige($filter),
                'acquisitions_par_annee' => $this->getTerrainsAcquisitionsParAnnee($filter),
                'croisement_bati_departement' => $this->getTerrainsCroisementBatiDepartement($filter),
                'croisement_occupation_departement' => $this->getTerrainsCroisementOccupationDepartement($filter),
                'classement_regions' => $this->getTerrainsClassementRegions($filter),
            ];
        }

        if (in_array('batiments', $actifs, true)) {
            $data['batiments'] = [
                'vue_globale' => $this->getBatimentsVueGlobale($filter),
                'repartition_geographique' => $this->getBatimentsRepartitionGeographique($filter),
                'financement' => $this->getBatimentsFinancement($filter),
                'en_litige' => $this->getBatimentsEnLitige($filter),
                'annee_construction' => $this->getBatimentsRepartitionAnneeConstruction($filter),
                'annee_refection' => $this->getBatimentsRepartitionAnneeRefection($filter),
                'croisement_etat_departement' => $this->getBatimentsCroisementEtatDepartement($filter),
                'classement_regions' => $this->getBatimentsClassementRegions($filter),
            ];
        }

        if (in_array('structures', $actifs, true)) {
            $data['structures'] = [
                'vue_globale' => $this->getStructuresVueGlobale($filter),
                'repartition_geographique' => $this->getStructuresRepartitionGeographique($filter),
                'cout_refection' => $this->getStructuresCoutRefection($filter),
                'annee_construction' => $this->getStructuresRepartitionAnneeConstruction($filter),
            ];
        }

        if (in_array('suivi', $actifs, true)) {
            $depuisCollecte = (new \DateTimeImmutable())->modify('-26 weeks');

            $data['suivi'] = [
                'evolution_pluriannuelle' => $this->getEvolutionPluriannuelle($filter),
                'classement_annuel_regions' => $this->getClassementAnnuelRegions($filter),
                'collecte_donnees' => $this->getSuiviCollecteHebdomadaire($filter, $depuisCollecte),
                'points_attention_prioritaires' => $this->getPointsAttentionPrioritaires($filter),
            ];
        }

        return $data;
    }

    // Remplace la méthode getStatistics globale
    public function getVueGlobale(StatisticsFilter $filter): array
    {
        $filters = $this->buildSecuredFilters($filter);

        $general = $this->statisticsRepository->getGeneralStatistics($filters);
        $parCategorie = $this->statisticsRepository->getRepartitionParCategorie($filters);
        $structures = $this->statisticsRepository->getRepartitionServicesCentralDeconcentre($filters);
        $mauvaisEtat = $this->statisticsRepository->getBiensMauvaisEtat($filters);
        $sansInformation = $this->statisticsRepository->getBiensSansInformation($filters);

        return array_merge($general, [
            'biens_par_categorie' => $parCategorie,
            'structures_centrales_vs_deconcentrees' => $structures,
            'biens_mauvais_etat' => $mauvaisEtat,
            'biens_sans_information' => $sansInformation,
        ]);
    }

    public function getRepartitionParCategorie(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getRepartitionParCategorie($this->buildSecuredFilters($filter));
    }

    /**
     * Écart (GAP) entre l'exercice demandé (`$filter->exercice`, ou année en cours par défaut) et
     * l'exercice précédent. Remplace l'ancienne comparaison entre deux années arbitraires
     * (`annee_debut`/`annee_fin`, supprimés) par la logique plus simple "exercice vs exercice - 1".
     */
    public function getEvolutionGap(StatisticsFilter $filter): array
    {
        $exercice = $filter->exercice ?? (int) (new \DateTimeImmutable())->format('Y');

        return $this->statisticsRepository->getEvolutionGap($this->buildSecuredFilters($filter), $exercice - 1, $exercice);
    }

    public function getClassementRegions(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getClassementRegionsParCategorie($this->buildSecuredFilters($filter));
    }

    // ======================================================================
    // Matériel roulant (véhicules)
    // ======================================================================

    public function getVehiculesVueGlobale(StatisticsFilter $filter): array
    {
        $filters = $this->buildSecuredFilters($filter);

        return [
            'total_vehicules' => $this->statisticsRepository->getVehiculesTotal($filters),
            'repartition_par_etat' => $this->statisticsRepository->getVehiculesRepartitionParEtat($filters),
            'vehicules_a_reformer' => $this->statisticsRepository->getVehiculesAReformer($filters),
            'vehicules_disparus' => $this->statisticsRepository->getVehiculesDisparus($filters),
            'carte_grise_manquante' => $this->statisticsRepository->getVehiculesCarteGriseManquante($filters),
            'valeur_parc' => $this->statisticsRepository->getVehiculesValeurTotale($filters),
        ];
    }

    public function getVehiculesRepartitionGeographique(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getVehiculesRepartitionGeographique($this->buildSecuredFilters($filter));
    }

    public function getVehiculesRepartitionFinancement(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getVehiculesRepartitionFinancement($this->buildSecuredFilters($filter));
    }

    public function getVehiculesRepartitionParType(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getVehiculesRepartitionParType($this->buildSecuredFilters($filter));
    }

    public function getVehiculesAnciennete(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getVehiculesAnciennete($this->buildSecuredFilters($filter));
    }

    public function getVehiculesCroisementEtatDepartement(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getVehiculesCroisementEtatDepartement($this->buildSecuredFilters($filter));
    }

    public function getVehiculesClassementRegions(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getVehiculesClassementRegions($this->buildSecuredFilters($filter));
    }

    public function getVehiculesParDetenteur(StatisticsFilter $filter, User $user): array
    {
        $filters = $this->buildSecuredFilters($filter);
        $vehicules = $this->statisticsRepository->getVehiculesParDetenteur((int) $user->getId(), $filters);

        return [
            'detenteur' => [
                'id' => $user->getId(),
                'nom' => trim($user->getLastName() . ' ' . $user->getFirstName()),
                'matricule' => $user->getMatricule(),
                'fonction' => $user->getService()?->getNom(),
            ],
            'nombre_vehicules' => count($vehicules),
            'vehicules' => $vehicules,
        ];
    }

    // ======================================================================
    // Terrains
    // ======================================================================

    public function getTerrainsVueGlobale(StatisticsFilter $filter): array
    {
        $filters = $this->buildSecuredFilters($filter);

        return [
            'total_terrains' => $this->statisticsRepository->getTerrainsTotal($filters),
            'valeur' => $this->statisticsRepository->getTerrainsValeurTotale($filters),
            'repartition_bati' => $this->statisticsRepository->getTerrainsRepartitionBati($filters),
            'securisation' => $this->statisticsRepository->getTerrainsSecurisation($filters),
            'occupation' => $this->statisticsRepository->getTerrainsRepartitionOccupation($filters),
            'titre_foncier' => $this->statisticsRepository->getTerrainsTitreFoncierManquant($filters),
            'terrains_loues' => $this->statisticsRepository->getTerrainsLoues($filters),
        ];
    }

    public function getTerrainsRepartitionGeographique(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getTerrainsRepartitionGeographique($this->buildSecuredFilters($filter));
    }

    public function getTerrainsEnLitige(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getTerrainsEnLitige($this->buildSecuredFilters($filter));
    }

    public function getTerrainsAcquisitionsParAnnee(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getTerrainsAcquisitionsParAnnee($this->buildSecuredFilters($filter));
    }

    public function getTerrainsCroisementBatiDepartement(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getTerrainsCroisementBatiDepartement($this->buildSecuredFilters($filter));
    }

    public function getTerrainsCroisementOccupationDepartement(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getTerrainsCroisementOccupationDepartement($this->buildSecuredFilters($filter));
    }

    public function getTerrainsClassementRegions(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getTerrainsClassementRegions($this->buildSecuredFilters($filter));
    }

    // ======================================================================
    // Bâtiments
    // ======================================================================

    public function getBatimentsVueGlobale(StatisticsFilter $filter): array
    {
        $filters = $this->buildSecuredFilters($filter);

        return [
            'total_batiments' => $this->statisticsRepository->getBatimentsTotal($filters),
            'repartition_par_etat' => $this->statisticsRepository->getBatimentsRepartitionParEtat($filters),
            'batiments_a_refectionner' => $this->statisticsRepository->getBatimentsARefectionner($filters),
            'occupation' => $this->statisticsRepository->getBatimentsRepartitionOccupation($filters),
            'titre_foncier' => $this->statisticsRepository->getBatimentsTitreFoncierManquant($filters),
            'batiments_loues' => $this->statisticsRepository->getBatimentsLoues($filters),
        ];
    }

    public function getBatimentsRepartitionGeographique(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getBatimentsRepartitionGeographique($this->buildSecuredFilters($filter));
    }

    public function getBatimentsFinancement(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getBatimentsFinancement($this->buildSecuredFilters($filter));
    }

    public function getBatimentsEnLitige(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getBatimentsEnLitige($this->buildSecuredFilters($filter));
    }

    public function getBatimentsRepartitionAnneeConstruction(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getBatimentsRepartitionAnneeConstruction($this->buildSecuredFilters($filter));
    }

    public function getBatimentsRepartitionAnneeRefection(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getBatimentsRepartitionAnneeRefection($this->buildSecuredFilters($filter));
    }

    public function getBatimentsCroisementEtatDepartement(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getBatimentsCroisementEtatDepartement($this->buildSecuredFilters($filter));
    }

    public function getBatimentsClassementRegions(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getBatimentsClassementRegions($this->buildSecuredFilters($filter));
    }

    // ======================================================================
    // Structures (Service)
    // ======================================================================

    public function getStructuresVueGlobale(StatisticsFilter $filter): array
    {
        $filters = $this->buildSecuredFilters($filter);

        return [
            'total_structures' => $this->statisticsRepository->getStructuresRepartitionClassification($filters),
            'repartition_par_type_service' => $this->statisticsRepository->getStructuresRepartitionParTypeService($filters),
            'avec_batiment' => $this->statisticsRepository->getStructuresAvecBatiment($filters),
            'a_refectionner' => $this->statisticsRepository->getStructuresARefectionner($filters),
            'valeur_infrastructures' => $this->statisticsRepository->getStructuresValeurInfrastructures($filters),
            'plan_disponible' => $this->statisticsRepository->getStructuresPlanDisponible($filters),
            'devis_disponible' => $this->statisticsRepository->getStructuresDevisDisponible($filters),
        ];
    }

    public function getStructuresRepartitionGeographique(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getStructuresRepartitionGeographique($this->buildSecuredFilters($filter));
    }

    public function getStructuresCoutRefection(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getStructuresCoutRefection($this->buildSecuredFilters($filter));
    }

    /**
     * KPI "Année de construction" (structures) : même donnée que le KPI 11a des bâtiments,
     * exposée aussi sous ce domaine pour éviter de dupliquer la requête.
     */
    public function getStructuresRepartitionAnneeConstruction(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getBatimentsRepartitionAnneeConstruction($this->buildSecuredFilters($filter));
    }

    // ======================================================================
    // Suivi, évolution et alerte
    // ======================================================================

    public function getEvolutionPluriannuelle(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getEvolutionPluriannuelle($this->buildSecuredFilters($filter));
    }

    public function getClassementAnnuelRegions(StatisticsFilter $filter): array
    {
        $exercice = $filter->exercice ?? (int) (new \DateTimeImmutable())->format('Y');

        return $this->statisticsRepository->getClassementAnnuelRegions($this->buildSecuredFilters($filter), $exercice);
    }

    public function getDepartementsSansDeclaration(StatisticsFilter $filter, int $categorieId): array
    {
        return $this->statisticsRepository->getDepartementsSansDeclaration($this->buildSecuredFilters($filter), $categorieId);
    }

    public function getSuiviCollecteHebdomadaire(StatisticsFilter $filter, ?\DateTimeImmutable $depuis): array
    {
        return $this->statisticsRepository->getSuiviCollecteHebdomadaire($this->buildSecuredFilters($filter), $depuis);
    }

    public function getPointsAttentionPrioritaires(StatisticsFilter $filter): array
    {
        return $this->statisticsRepository->getPointsAttentionPrioritaires($this->buildSecuredFilters($filter));
    }

    public function getRepartitionParStructure(StatisticsFilter $filter): array
    {
        $filters = $this->buildSecuredFilters($filter);
        return $this->statisticsRepository->getTopServicesByBiens($filters, $filter->limit ?? 10);
    }

    public function getRepartitionParProjet(StatisticsFilter $filter): array
    {
        $filters = $this->buildSecuredFilters($filter);
        return $this->statisticsRepository->getTopProjectsByBiens($filters, $filter->limit ?? 5);
    }
    
    public function getRepartitionParRegion(StatisticsFilter $filter): array
    {
        $filters = $this->buildSecuredFilters($filter);
        return $this->statisticsRepository->getRepartitionParRegion($filters);
    }
    
    public function getTopServicesValeur(StatisticsFilter $filter): array
    {
        $filters = $this->buildSecuredFilters($filter);
        return $this->statisticsRepository->getTopServicesByValeur($filters, $filter->limit ?? 6);
    }

    public function getEvolutionMensuelle(StatisticsFilter $filter, string $type): array
    {
        $filters = $this->buildSecuredFilters($filter);
        if ($type === 'mouvements') {
            return $this->statisticsRepository->getBiensAffectesParMois($filters);
        } elseif ($type === 'maintenance') {
            return $this->statisticsRepository->getMaintenanceParMois($filters);
        }
        
        return $this->statisticsRepository->getPatrimoineParMois($filters); // default evolution patrimoine
    }

    private function filterToArray(StatisticsFilter $filter): array
    {
        return array_filter([
            'servicesId' => $filter->servicesId,
            'categorieId' => $filter->categorieId,
            'assetTypeIds' => $filter->assetTypeIds,
            'assetSubTypeIds' => $filter->assetSubTypeIds,
            'projectIds' => $filter->projectIds,
            'statuts' => $filter->statuts,
            'etatBienIds' => $filter->etatBienIds,
            'regionIds' => $filter->regionIds,
            'departementIds' => $filter->departementIds,
            'arrondissementIds' => $filter->arrondissementIds,
            'exercice' => $filter->exercice,
        ], fn($v) => $v !== null);
    }

    /**
     * Isolation des données par service pour les utilisateurs non-administrateurs : ils ne
     * voient que les statistiques de leur propre service + ses descendants d'organigramme
     * (cf. StatisticsRepository::resolveServicesIdWithDescendants), quel que soit le filtre
     * `services_id` envoyé par le client — barrière de sécurité, pas une simple valeur par
     * défaut contournable (demande explicite).
     *
     * "Administrateur" ici suit exactement la même convention que le frontend
     * (useIsAdmin()) : un rôle métier (assignedRoles, PAS le tableau Symfony
     * User::$roles qui reste toujours ['ROLE_USER'] dans cette application — aucun
     * ROLE_ADMIN/ROLE_GESTIONNAIRE_* n'est jamais assigné, cf. CreateUserCommand /
     * UserRepository::createUser) dont le nom contient "admin" (insensible à la casse).
     */
    private function buildSecuredFilters(StatisticsFilter $filter): array
    {
        $filters = $this->filterToArray($filter);
        $user = $this->security->getUser();

        if ($user instanceof User) {
            $isAdmin = false;
            foreach ($user->getAssignedRoles() as $role) {
                if (null !== $role->getNom() && preg_match('/admin/i', $role->getNom())) {
                    $isAdmin = true;
                    break;
                }
            }

            if (!$isAdmin) {
                $uService = $user->getService();
                if ($uService) {
                    $filters['secured_service_id'] = $uService->getId();
                }
            }
        }

        return $filters;
    }

    /**
     * Enveloppe standard `{success, status, message, data}` par défaut. Si `$request` porte
     * `?format=xlsx` ou `?format=pdf` (filtre "Export des données", écran de disponibilité des
     * filtres), le résultat est streamé en fichier téléchargeable à la place — même donnée,
     * même filtres, juste une présentation différente.
     */
    public function formatResponse(array $statistics, ?Request $request = null): Response
    {
        $format = $request?->query->get('format');

        if ('xlsx' === $format || 'pdf' === $format) {
            $title = $this->exportTitleFromRoute((string) $request?->attributes->get('_route', 'export'));

            return 'xlsx' === $format
                ? $this->exportService->respondXlsx($statistics, $title)
                : $this->exportService->respondPdf($statistics, $title);
        }

        return $this->apiResponseFactory->success(
            $statistics,
            Response::HTTP_OK,
            'Statistiques récupérées avec succès.'
        );
    }

    private function exportTitleFromRoute(string $routeName): string
    {
        $name = str_replace('api_stats_', '', $routeName);
        $name = str_replace('_', ' ', $name);
        $name = trim($name);

        return '' !== $name ? ucwords($name) : 'Export statistiques';
    }
}