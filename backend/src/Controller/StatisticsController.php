<?php
// src/Controller/StatisticsController.php

namespace App\Controller;

use App\DTO\StatisticsFilter;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use App\Service\StatisticsService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour les statistiques du patrimoine.
 * 
 * Endpoint unique retournant toutes les statistiques structurées en blocs.
 * Tous les filtres sont optionnels et combinables.
 */
#[Route('/api/stats')]
#[OA\Tag(name: 'Statistics')]
final class StatisticsController extends AbstractController
{
    public function __construct(
        private readonly StatisticsService $statisticsService,
    ) {
    }

    /**
     * Regroupe tous les autres endpoints /api/stats/* dans une seule réponse JSON, organisée par
     * domaine. Les 40 routes individuelles restent inchangées et continuent de fonctionner — ceci
     * est un endpoint supplémentaire, pas un remplacement.
     *
     * Le domaine renvoyé dépend de `categorie_id` (plus de paramètre `domaines` séparé) : sans
     * catégorie, les 6 domaines ; avec une seule catégorie reconnue (Matériel Roulant / Terrain /
     * Bâtiment), seul le domaine correspondant ; avec une seule catégorie non reconnue, ou avec
     * plusieurs catégories, repli sur la section "patrimoine" générale filtrée par ces catégories.
     * Voir StatisticsService::getDashboard().
     *
     * Exclus (nécessitent un paramètre obligatoire propre à une recherche précise, pas à une vue
     * d'ensemble) : `suivi/departements-sans-declaration` (categorie_id) et `vehicules/detenteur`
     * (matricule ou user_id).
     *
     * Coûteux (~60 requêtes SQL en une seule requête HTTP quand les 6 domaines sont actifs) : à
     * réserver au chargement initial d'un tableau de bord, pas à un polling fréquent.
     */
    #[Route('/dashboard', name: 'api_stats_dashboard', methods: ['GET'])]
    #[OA\Get(
        summary: 'Tableau de bord complet : tous les KPIs statistiques en un seul appel',
        description: 'Regroupe patrimoine, véhicules, terrains, bâtiments, structures et suivi/évolution/alerte dans une seule réponse JSON. '
            . "Passer une seule `categorie_id` restreint la réponse au domaine correspondant (Matériel Roulant → véhicules, Terrain → terrains, "
            . 'Bâtiment → bâtiments) au lieu des 6. Passer plusieurs `categorie_id` filtre la section "patrimoine" par ces catégories. '
            . "N'inclut pas `suivi/departements-sans-declaration` (nécessite categorie_id) ni `vehicules/detenteur` (nécessite un utilisateur), "
            . 'qui restent des endpoints dédiés à interroger séparément. Requête coûteuse (~60 requêtes SQL) : réservé au chargement initial.'
    )]
    #[OA\Parameter(
        name: 'region_ids',
        in: 'query',
        description: 'IDs de régions (CSV, tableau `region_ids[]=` ou valeur unique).',
        schema: new OA\Schema(type: 'string'),
        example: '1,2'
    )]
    #[OA\Parameter(
        name: 'departement_ids',
        in: 'query',
        description: 'IDs de départements, cohérents avec region_ids si les deux sont fournis.',
        schema: new OA\Schema(type: 'string'),
        example: '3'
    )]
    #[OA\Parameter(
        name: 'arrondissement_ids',
        in: 'query',
        description: 'IDs d\'arrondissements.',
        schema: new OA\Schema(type: 'string'),
        example: '5'
    )]
    #[OA\Parameter(
        name: 'services_id',
        in: 'query',
        description: 'IDs de structures (services). Chaque ID donné inclut aussi tous ses descendants '
            . "d'organigramme : un ID feuille se comporte comme un filtre exact, un ID de direction "
            . 'inclut automatiquement les services rattachés (fusion des anciens filtres `service_ids` et `organigramme_service_id`).',
        schema: new OA\Schema(type: 'string'),
        example: '16'
    )]
    #[OA\Parameter(
        name: 'categorie_id',
        in: 'query',
        description: 'ID(s) de catégorie(s) de biens (Matériel Roulant, Terrain, Bâtiment...). Une seule catégorie reconnue bascule le domaine renvoyé (véhicules/terrains/bâtiments) ; plusieurs catégories filtrent la section "patrimoine" générale. CSV, tableau `categorie_id[]=` ou valeur unique.',
        schema: new OA\Schema(type: 'string'),
        example: '2'
    )]
    #[OA\Parameter(
        name: 'type_bien_ids',
        in: 'query',
        description: 'IDs de types de biens (ex. "Véhicule", "Terrain").',
        schema: new OA\Schema(type: 'string'),
        example: '1'
    )]
    #[OA\Parameter(
        name: 'sous_type_ids',
        in: 'query',
        description: 'IDs de sous-types de biens (ex. "Pick-up" dans "Véhicule").',
        schema: new OA\Schema(type: 'string'),
        example: '4'
    )]
    #[OA\Parameter(
        name: 'projet_ids',
        in: 'query',
        description: 'IDs de projets (bailleurs de financement).',
        schema: new OA\Schema(type: 'string'),
        example: '1'
    )]
    #[OA\Parameter(
        name: 'etat_bien_ids',
        in: 'query',
        description: 'IDs d\'états de biens.',
        schema: new OA\Schema(type: 'string'),
        example: '2'
    )]
    #[OA\Parameter(
        name: 'statuts',
        in: 'query',
        description: 'Statuts de gestion des biens. Par défaut, les biens SORTIS sont exclus de tous les KPIs sauf demande explicite ici. Sélection multiple possible.',
        schema: new OA\Schema(
            type: 'array',
            items: new OA\Items(type: 'string', enum: ['ACTIF', 'EN MAINTENANCE', 'SORTIS'])
        ),
        style: 'form',
        explode: false,
        example: ['ACTIF']
    )]
    #[OA\Parameter(
        name: 'exercice',
        in: 'query',
        description: "Année d'exercice sur laquelle filtrer dateAcquisition. Défaut : année en cours. "
            . "Sert aussi de référence pour l'écart GAP (comparé à exercice - 1) et le classement annuel. "
            . 'Seule l\'année de la date saisie est utilisée.',
        schema: new OA\Schema(type: 'string', format: 'date'),
        example: '2026-01-01'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success — structure complète par domaine. Les tableaux répétitifs sont réduits à '
            . 'un seul élément ici pour la lisibilité ; en réalité ils contiennent autant d\'entrées que de régions/catégories/départements concernés.',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Statistiques récupérées avec succès.',
                'data' => [
                    'patrimoine' => [
                        'vue_globale' => [
                            'total_biens' => 128,
                            'valeur_totale_patrimoine' => 45230000.0,
                            'total_biens_actifs' => 110,
                            'total_biens_sortis' => 12,
                            'total_biens_maintenance' => 6,
                            'total_services_avec_biens' => 24,
                            'biens_par_categorie' => [
                                ['categorie_id' => 2, 'categorie_nom' => 'Matériel Roulant', 'nombre_biens' => 40, 'valeur_patrimoine' => 18000000.0],
                            ],
                            'structures_centrales_vs_deconcentrees' => ['central' => 60, 'deconcentre' => 68],
                            'biens_mauvais_etat' => ['nombre_biens' => 9, 'total_biens' => 128, 'pourcentage' => 7.03],
                            'biens_sans_information' => ['sans_etat' => 5, 'sans_occupation' => 8, 'sans_securisation' => 20],
                        ],
                        'repartition_par_categorie' => [
                            ['categorie_id' => 2, 'categorie_nom' => 'Matériel Roulant', 'nombre_biens' => 40, 'valeur_patrimoine' => 18000000.0],
                        ],
                        'repartition_par_structure' => [
                            ['service_id' => 16, 'service_nom' => 'DSI', 'nombre_biens' => 14],
                        ],
                        'repartition_par_projet' => [
                            ['projet_id' => 1, 'projet_nom' => 'Modernisation SI', 'nombre_biens' => 22],
                        ],
                        'repartition_par_region' => [
                            ['region_id' => 1, 'region_nom' => 'Centre', 'nombre_biens' => 55, 'valeur_patrimoine' => 21000000.0],
                        ],
                        'top_services_valeur' => [
                            ['service_id' => 16, 'service_nom' => 'DSI', 'valeur_patrimoine' => 9000000.0, 'nombre_biens' => 14],
                        ],
                        'evolution_mensuelle' => [
                            'patrimoine' => [['mois' => '2026-07', 'valeur_patrimoine' => 44000000.0]],
                            'mouvements' => [['mois' => '2026-07', 'nombre_biens' => 3]],
                            'maintenance' => [['mois' => '2026-07', 'nombre_biens' => 2]],
                        ],
                        'evolution_gap' => [
                            ['region_id' => 1, 'region_nom' => 'Centre', 'categorie_id' => 2, 'categorie_nom' => 'Matériel Roulant', 'annee_debut' => 15, 'annee_fin' => 21, 'gap' => 6],
                        ],
                        'classement_regions' => [
                            ['categorie_id' => 2, 'categorie_nom' => 'Matériel Roulant', 'regions' => [['region_id' => 1, 'region_nom' => 'Centre', 'nombre_biens' => 18]]],
                        ],
                    ],
                    'vehicules' => [
                        'vue_globale' => [
                            'total_vehicules' => 40,
                            'repartition_par_etat' => [['etat' => 'En panne', 'nombre' => 5, 'pourcentage' => 12.5]],
                            'vehicules_a_reformer' => ['total' => 3, 'par_region' => [['region_id' => 1, 'region_nom' => 'Centre', 'nombre' => 2]]],
                            'vehicules_disparus' => ['nombre' => 1, 'total_vehicules' => 40, 'pourcentage' => 2.5, 'par_region' => [['region_id' => 1, 'region_nom' => 'Centre', 'nombre' => 1]]],
                            'carte_grise_manquante' => ['nombre' => 6, 'total_vehicules' => 40, 'pourcentage' => 15.0],
                            'valeur_parc' => ['valeur_totale' => 18000000.0, 'total_vehicules' => 40, 'vehicules_avec_valeur_renseignee' => 33],
                        ],
                        'repartition_geographique' => [
                            ['region_id' => 1, 'region_nom' => 'Centre', 'nombre_vehicules' => 15, 'departements' => [['departement_id' => 3, 'departement_nom' => 'Mfoundi', 'nombre_vehicules' => 15, 'arrondissements' => []]]],
                        ],
                        'repartition_financement' => [
                            ['source_financement' => 'Modernisation SI', 'nombre' => 22],
                        ],
                        'repartition_par_type' => [
                            ['type_id' => 1, 'type_nom' => 'Véhicule', 'nombre' => 40, 'sous_types' => [['sous_type_id' => 4, 'sous_type_nom' => 'Pick-up', 'nombre' => 12]]],
                        ],
                        'anciennete' => ['age_moyen_annees' => 4.2, 'total_vehicules' => 40, 'vehicules_avec_date_connue' => 33, 'repartition_par_tranche' => [['tranche' => '3-5 ans', 'nombre' => 14]]],
                        'croisement_etat_departement' => [
                            ['departement_id' => 3, 'departement_nom' => 'Mfoundi', 'etats' => [['etat' => 'Bon', 'nombre' => 10]]],
                        ],
                        'classement_regions' => [
                            ['region_id' => 1, 'region_nom' => 'Centre', 'nombre_vehicules' => 15],
                        ],
                    ],
                    'terrains' => [
                        'vue_globale' => [
                            'total_terrains' => 10,
                            'valeur' => ['valeur_totale' => 5000000.0, 'total_terrains' => 10, 'terrains_avec_valeur_renseignee' => 7, 'terrains_sans_valeur' => 3],
                            'repartition_bati' => [['statut' => 'Bâti', 'nombre' => 6, 'pourcentage' => 60.0]],
                            'securisation' => ['juridique_seulement' => 2, 'physique_seulement' => 1, 'les_deux' => 3, 'aucun' => 4, 'total_terrains' => 10],
                            'occupation' => [['occupation' => 'Régulière', 'nombre' => 7, 'pourcentage' => 70.0]],
                            'titre_foncier' => ['nombre_sans_titre' => 4, 'nombre_avec_titre' => 6, 'total_terrains' => 10, 'pourcentage_sans_titre' => 40.0],
                            'terrains_loues' => ['nombre' => 2],
                        ],
                        'repartition_geographique' => [
                            ['region_id' => 1, 'region_nom' => 'Centre', 'nombre_terrains' => 6, 'departements' => []],
                        ],
                        'en_litige' => ['nombre' => 1, 'terrains' => [['id' => 12, 'reference' => 'PAT-2026-00012', 'nom' => 'Terrain Nsimeyong']]],
                        'acquisitions_par_annee' => [['annee' => '2025', 'nombre' => 4]],
                        'croisement_bati_departement' => [
                            ['departement_id' => 3, 'departement_nom' => 'Mfoundi', 'bati' => 4, 'non_bati' => 2, 'aucune_information' => 0],
                        ],
                        'croisement_occupation_departement' => [
                            ['departement_id' => 3, 'departement_nom' => 'Mfoundi', 'reguliere' => 5, 'irreguliere' => 1, 'aucune_information' => 0],
                        ],
                        'classement_regions' => [
                            ['region_id' => 1, 'region_nom' => 'Centre', 'nombre_terrains' => 6],
                        ],
                    ],
                    'batiments' => [
                        'vue_globale' => [
                            'total_batiments' => 18,
                            'repartition_par_etat' => [['etat' => 'Bon', 'nombre' => 8, 'pourcentage' => 44.4]],
                            'batiments_a_refectionner' => ['total' => 2, 'par_region' => [['region_id' => 1, 'region_nom' => 'Centre', 'nombre' => 1]]],
                            'occupation' => [['occupation' => 'Régulière', 'nombre' => 12, 'pourcentage' => 66.7]],
                            'titre_foncier' => ['nombre_sans_titre' => 5, 'nombre_avec_titre' => 13, 'total_batiments' => 18, 'pourcentage_sans_titre' => 27.8],
                            'batiments_loues' => ['nombre' => 1],
                        ],
                        'repartition_geographique' => [
                            ['region_id' => 1, 'region_nom' => 'Centre', 'nombre_batiments' => 9, 'departements' => []],
                        ],
                        'financement' => [
                            'par_source_financement' => [['source_financement' => 'Modernisation SI', 'nombre' => 5]],
                            'par_projet' => [['projet_id' => 1, 'projet_nom' => 'Modernisation SI', 'nombre' => 5]],
                        ],
                        'en_litige' => ['nombre' => 0, 'batiments' => []],
                        'annee_construction' => [['annee' => '2020', 'nombre' => 3]],
                        'annee_refection' => [['annee' => '2024', 'nombre' => 2]],
                        'croisement_etat_departement' => [
                            ['departement_id' => 3, 'departement_nom' => 'Mfoundi', 'etats' => [['etat' => 'Bon', 'nombre' => 4]]],
                        ],
                        'classement_regions' => [
                            ['region_id' => 1, 'region_nom' => 'Centre', 'nombre_batiments' => 9],
                        ],
                    ],
                    'structures' => [
                        'vue_globale' => [
                            'total_structures' => ['total' => 24, 'par_classification' => [['classification' => 'Centrale', 'nombre' => 10]]],
                            'repartition_par_type_service' => [['type_service' => 'SERVICE', 'nombre' => 18]],
                            'avec_batiment' => ['total_structures' => 24, 'avec_batiment' => 9, 'sans_batiment' => 15],
                            'a_refectionner' => ['nombre' => 2, 'total_structures' => 24],
                            'valeur_infrastructures' => ['valeur_totale' => 12000000.0, 'batiments_avec_valeur' => 9],
                            'plan_disponible' => ['total_structures' => 24, 'avec_plan' => 4, 'construites_selon_plan_type_officiel' => 1],
                            'devis_disponible' => ['avec_devis' => 3, 'total_structures' => 24],
                        ],
                        'repartition_geographique' => [
                            ['region_id' => 1, 'region_nom' => 'Centre', 'nombre_structures' => 12, 'departements' => []],
                        ],
                        'cout_refection' => ['cout_total' => 450000.0, 'nombre_batiments_concernes' => 2, 'par_region' => [['region_id' => 1, 'region_nom' => 'Centre', 'cout_total' => 450000.0]]],
                        'annee_construction' => [['annee' => '2020', 'nombre' => 3]],
                    ],
                    'suivi' => [
                        'evolution_pluriannuelle' => [
                            ['region_id' => 1, 'region_nom' => 'Centre', 'categorie_id' => 2, 'categorie_nom' => 'Matériel Roulant', 'series' => [['annee' => '2025', 'nombre_biens' => 8]]],
                        ],
                        'classement_annuel_regions' => [
                            ['categorie_id' => 2, 'categorie_nom' => 'Matériel Roulant', 'annee' => 2026, 'regions' => [['region_id' => 1, 'region_nom' => 'Centre', 'nombre_biens' => 8, 'rang' => 1]]],
                        ],
                        'collecte_donnees' => [
                            'nouveaux_biens_par_semaine' => [['semaine' => '2026-W30', 'nombre' => 4]],
                            'biens_mis_a_jour_par_semaine' => [['semaine' => '2026-W30', 'nombre' => 2]],
                        ],
                        'points_attention_prioritaires' => [
                            ['id' => 12, 'site' => 'Terrain Nsimeyong', 'region_nom' => 'Centre', 'departement_nom' => 'Mfoundi', 'arrondissement_nom' => 'Yaoundé 1er', 'traitement' => 'Non traité'],
                        ],
                    ],
                ],
            ]
        )
    )]
    public function dashboard(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());

        return $this->statisticsService->formatResponse($this->statisticsService->getDashboard($filter), $request);
    }

    #[Route('/vue-globale', name: 'api_stats_vue_globale', methods: ['GET'])]
    #[OA\Get(summary: 'Statistiques globales du patrimoine')]
    public function vueGlobale(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getVueGlobale($filter), $request);
    }

    #[Route('/repartition-par-structure', name: 'api_stats_repartition_structure', methods: ['GET'])]
    #[OA\Get(summary: 'Répartition par structure (Top services)')]
    public function repartitionParStructure(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        $critere = $request->query->get('critere', 'nombre');
        
        if ($critere === 'valeur') {
            return $this->statisticsService->formatResponse($this->statisticsService->getTopServicesValeur($filter), $request);
        }

        return $this->statisticsService->formatResponse($this->statisticsService->getRepartitionParStructure($filter), $request);
    }

    #[Route('/repartition-par-projet', name: 'api_stats_repartition_projet', methods: ['GET'])]
    #[OA\Get(summary: 'Répartition par projet donateur')]
    public function repartitionParProjet(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getRepartitionParProjet($filter), $request);
    }

    #[Route('/repartition-par-region', name: 'api_stats_repartition_region', methods: ['GET'])]
    #[OA\Get(summary: 'Agglomération des données par région (pour carte de la carte)')]
    public function repartitionParRegion(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getRepartitionParRegion($filter), $request);
    }

    #[Route('/evolution-mensuelle', name: 'api_stats_evolution_mensuelle', methods: ['GET'])]
    #[OA\Get(summary: 'Séries temporelles mensuelles (mouvements, maintenance ou patrimoine)')]
    public function evolutionMensuelle(Request $request): Response
    {
        $type = $request->query->get('type', 'patrimoine'); // mouvements | maintenance | patrimoine
        $filter = StatisticsFilter::fromRequest($request->query->all());

        return $this->statisticsService->formatResponse($this->statisticsService->getEvolutionMensuelle($filter, $type), $request);
    }

    #[Route('/repartition-par-categorie', name: 'api_stats_repartition_categorie', methods: ['GET'])]
    #[OA\Get(summary: 'Nombre de biens et valeur du patrimoine par catégorie')]
    public function repartitionParCategorie(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getRepartitionParCategorie($filter), $request);
    }

    #[Route('/evolution-gap', name: 'api_stats_evolution_gap', methods: ['GET'])]
    #[OA\Get(
        summary: 'Écart (GAP) du nombre de biens par région et catégorie, entre l\'exercice demandé et l\'exercice précédent',
        description: 'Compare `exercice` (défaut : année en cours) à `exercice - 1`.'
    )]
    public function evolutionGap(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());

        return $this->statisticsService->formatResponse($this->statisticsService->getEvolutionGap($filter), $request);
    }

    #[Route('/classement-regions', name: 'api_stats_classement_regions', methods: ['GET'])]
    #[OA\Get(summary: 'Classement des régions par nombre de biens, pour chaque catégorie')]
    public function classementRegions(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getClassementRegions($filter), $request);
    }

    // ==========================================================================
    // Matériel roulant (véhicules)
    // ==========================================================================

    #[Route('/vehicules/vue-globale', name: 'api_stats_vehicules_vue_globale', methods: ['GET'])]
    #[OA\Get(summary: 'Statistiques globales du matériel roulant (nombre, état, à réformer, disparus, carte grise, valeur du parc)')]
    public function vehiculesVueGlobale(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getVehiculesVueGlobale($filter), $request);
    }

    #[Route('/vehicules/repartition-geographique', name: 'api_stats_vehicules_repartition_geographique', methods: ['GET'])]
    #[OA\Get(summary: 'Répartition des véhicules : Région -> Département -> Arrondissement')]
    public function vehiculesRepartitionGeographique(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getVehiculesRepartitionGeographique($filter), $request);
    }

    #[Route('/vehicules/repartition-financement', name: 'api_stats_vehicules_repartition_financement', methods: ['GET'])]
    #[OA\Get(summary: 'Répartition des véhicules par source de financement')]
    public function vehiculesRepartitionFinancement(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getVehiculesRepartitionFinancement($filter), $request);
    }

    #[Route('/vehicules/repartition-type', name: 'api_stats_vehicules_repartition_type', methods: ['GET'])]
    #[OA\Get(summary: 'Répartition des véhicules par type / sous-type (ex. Pick-up, Berline, Moto)')]
    public function vehiculesRepartitionParType(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getVehiculesRepartitionParType($filter), $request);
    }

    #[Route('/vehicules/anciennete', name: 'api_stats_vehicules_anciennete', methods: ['GET'])]
    #[OA\Get(
        summary: "Ancienneté du parc automobile (âge moyen + répartition par tranche d'âge)",
        description: "Calculée depuis `dateAcquisition`, en l'absence d'une date de mise en circulation dédiée dans le schéma."
    )]
    public function vehiculesAnciennete(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getVehiculesAnciennete($filter), $request);
    }

    #[Route('/vehicules/croisement-etat-departement', name: 'api_stats_vehicules_croisement_etat_departement', methods: ['GET'])]
    #[OA\Get(summary: 'Tableau croisé : nombre de véhicules par état, pour chaque département')]
    public function vehiculesCroisementEtatDepartement(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getVehiculesCroisementEtatDepartement($filter), $request);
    }

    #[Route('/vehicules/classement-regions', name: 'api_stats_vehicules_classement_regions', methods: ['GET'])]
    #[OA\Get(summary: 'Classement des régions par nombre de véhicules')]
    public function vehiculesClassementRegions(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getVehiculesClassementRegions($filter), $request);
    }

    #[Route('/vehicules/detenteur', name: 'api_stats_vehicules_detenteur', methods: ['GET'])]
    #[OA\Get(
        summary: 'Véhicules affectés à un détenteur donné',
        description: 'Recherche par `matricule` ou `user_id` (au moins un des deux requis).'
    )]
    #[OA\Parameter(name: 'matricule', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'user_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
    public function vehiculesParDetenteur(
        Request $request,
        UserRepository $userRepository,
        ApiResponseFactory $apiResponse
    ): Response {
        $matricule = $request->query->get('matricule');
        $userId = $request->query->get('user_id');

        $user = null;
        if (null !== $userId) {
            $user = $userRepository->find((int) $userId);
        } elseif (null !== $matricule) {
            $user = $userRepository->findOneBy(['matricule' => $matricule, 'isDelete' => false]);
        }

        if (null === $user) {
            return $apiResponse->error(
                'Aucun utilisateur trouvé pour ce matricule ou cet identifiant.',
                Response::HTTP_NOT_FOUND
            );
        }

        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getVehiculesParDetenteur($filter, $user), $request);
    }

    // ==========================================================================
    // Terrains
    // ==========================================================================

    #[Route('/terrains/vue-globale', name: 'api_stats_terrains_vue_globale', methods: ['GET'])]
    #[OA\Get(summary: 'Statistiques globales des terrains (nombre, valeur, bâti, sécurisation, occupation, titre foncier, location)')]
    public function terrainsVueGlobale(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getTerrainsVueGlobale($filter), $request);
    }

    #[Route('/terrains/repartition-geographique', name: 'api_stats_terrains_repartition_geographique', methods: ['GET'])]
    #[OA\Get(summary: 'Répartition des terrains : Région -> Département -> Arrondissement')]
    public function terrainsRepartitionGeographique(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getTerrainsRepartitionGeographique($filter), $request);
    }

    #[Route('/terrains/en-litige', name: 'api_stats_terrains_en_litige', methods: ['GET'])]
    #[OA\Get(summary: 'Nombre et liste des terrains en litige')]
    public function terrainsEnLitige(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getTerrainsEnLitige($filter), $request);
    }

    #[Route('/terrains/acquisitions-par-annee', name: 'api_stats_terrains_acquisitions_par_annee', methods: ['GET'])]
    #[OA\Get(summary: 'Nombre de terrains acquis par année')]
    public function terrainsAcquisitionsParAnnee(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getTerrainsAcquisitionsParAnnee($filter), $request);
    }

    #[Route('/terrains/croisement-bati-departement', name: 'api_stats_terrains_croisement_bati_departement', methods: ['GET'])]
    #[OA\Get(summary: 'Tableau croisé : Bâti / Non bâti / Aucune information, pour chaque département')]
    public function terrainsCroisementBatiDepartement(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getTerrainsCroisementBatiDepartement($filter), $request);
    }

    #[Route('/terrains/croisement-occupation-departement', name: 'api_stats_terrains_croisement_occupation_departement', methods: ['GET'])]
    #[OA\Get(summary: 'Tableau croisé : occupation Régulière / Irrégulière / Aucune information, pour chaque département')]
    public function terrainsCroisementOccupationDepartement(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getTerrainsCroisementOccupationDepartement($filter), $request);
    }

    #[Route('/terrains/classement-regions', name: 'api_stats_terrains_classement_regions', methods: ['GET'])]
    #[OA\Get(summary: 'Classement des régions par nombre de terrains')]
    public function terrainsClassementRegions(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getTerrainsClassementRegions($filter), $request);
    }

    // ==========================================================================
    // Bâtiments
    // ==========================================================================

    #[Route('/batiments/vue-globale', name: 'api_stats_batiments_vue_globale', methods: ['GET'])]
    #[OA\Get(summary: 'Statistiques globales des bâtiments (nombre, état, à réfectionner, occupation, titre foncier, location)')]
    public function batimentsVueGlobale(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getBatimentsVueGlobale($filter), $request);
    }

    #[Route('/batiments/repartition-geographique', name: 'api_stats_batiments_repartition_geographique', methods: ['GET'])]
    #[OA\Get(summary: 'Répartition des bâtiments : Région -> Département -> Arrondissement')]
    public function batimentsRepartitionGeographique(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getBatimentsRepartitionGeographique($filter), $request);
    }

    #[Route('/batiments/repartition-financement', name: 'api_stats_batiments_repartition_financement', methods: ['GET'])]
    #[OA\Get(summary: 'Répartition des bâtiments par source de financement et par projet donateur')]
    public function batimentsRepartitionFinancement(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getBatimentsFinancement($filter), $request);
    }

    #[Route('/batiments/en-litige', name: 'api_stats_batiments_en_litige', methods: ['GET'])]
    #[OA\Get(summary: 'Nombre et liste des bâtiments en litige')]
    public function batimentsEnLitige(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getBatimentsEnLitige($filter), $request);
    }

    #[Route('/batiments/annee-construction', name: 'api_stats_batiments_annee_construction', methods: ['GET'])]
    #[OA\Get(
        summary: 'Répartition des bâtiments par année de construction',
        description: "Calculée depuis `dateAcquisition`, en l'absence d'une date de construction dédiée dans le schéma."
    )]
    public function batimentsAnneeConstruction(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getBatimentsRepartitionAnneeConstruction($filter), $request);
    }

    #[Route('/batiments/annee-refection', name: 'api_stats_batiments_annee_refection', methods: ['GET'])]
    #[OA\Get(
        summary: 'Répartition des bâtiments par année de dernière réfection',
        description: "Déduite de la dernière intervention de maintenance dont le motif contient \"réfection\" — pas un champ dédié."
    )]
    public function batimentsAnneeRefection(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getBatimentsRepartitionAnneeRefection($filter), $request);
    }

    #[Route('/batiments/croisement-etat-departement', name: 'api_stats_batiments_croisement_etat_departement', methods: ['GET'])]
    #[OA\Get(summary: 'Tableau croisé : nombre de bâtiments par état, pour chaque département')]
    public function batimentsCroisementEtatDepartement(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getBatimentsCroisementEtatDepartement($filter), $request);
    }

    #[Route('/batiments/classement-regions', name: 'api_stats_batiments_classement_regions', methods: ['GET'])]
    #[OA\Get(summary: 'Classement des régions par nombre de bâtiments')]
    public function batimentsClassementRegions(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getBatimentsClassementRegions($filter), $request);
    }

    // ==========================================================================
    // Structures (Service)
    // ==========================================================================

    #[Route('/structures/vue-globale', name: 'api_stats_structures_vue_globale', methods: ['GET'])]
    #[OA\Get(summary: 'Statistiques globales des structures (nombre, classification, type, bâtiment, réfection, valeur, plan, devis)')]
    public function structuresVueGlobale(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getStructuresVueGlobale($filter), $request);
    }

    #[Route('/structures/repartition-geographique', name: 'api_stats_structures_repartition_geographique', methods: ['GET'])]
    #[OA\Get(summary: 'Répartition des structures : Région -> Département -> Arrondissement')]
    public function structuresRepartitionGeographique(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getStructuresRepartitionGeographique($filter), $request);
    }

    #[Route('/structures/cout-refection', name: 'api_stats_structures_cout_refection', methods: ['GET'])]
    #[OA\Get(summary: 'Coût de réfection estimé des infrastructures : global + par région')]
    public function structuresCoutRefection(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getStructuresCoutRefection($filter), $request);
    }

    #[Route('/structures/annee-construction', name: 'api_stats_structures_annee_construction', methods: ['GET'])]
    #[OA\Get(summary: "Répartition des bâtiments de structures par année de construction (proxy dateAcquisition)")]
    public function structuresAnneeConstruction(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getStructuresRepartitionAnneeConstruction($filter), $request);
    }

    // ==========================================================================
    // Suivi, évolution et alerte
    // ==========================================================================

    #[Route('/suivi/evolution-pluriannuelle', name: 'api_stats_suivi_evolution_pluriannuelle', methods: ['GET'])]
    #[OA\Get(summary: 'Évolution du nombre de biens acquis, par région et catégorie, toutes années confondues')]
    public function suiviEvolutionPluriannuelle(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getEvolutionPluriannuelle($filter), $request);
    }

    #[Route('/suivi/classement-annuel-regions', name: 'api_stats_suivi_classement_annuel_regions', methods: ['GET'])]
    #[OA\Get(
        summary: 'Pour un exercice donné, rang de chaque région dans chaque catégorie de biens',
        description: 'Utilise `exercice` (défaut : année en cours).'
    )]
    public function suiviClassementAnnuelRegions(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getClassementAnnuelRegions($filter), $request);
    }

    #[Route('/suivi/departements-sans-declaration', name: 'api_stats_suivi_departements_sans_declaration', methods: ['GET'])]
    #[OA\Get(summary: "Départements n'ayant déclaré aucun bien d'une catégorie donnée")]
    #[OA\Parameter(name: 'categorie_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))]
    public function suiviDepartementsSansDeclaration(Request $request, ApiResponseFactory $apiResponse): Response
    {
        $categorieId = $request->query->get('categorie_id');
        if (null === $categorieId || '' === $categorieId) {
            return $apiResponse->error('Le paramètre categorie_id est requis.', Response::HTTP_BAD_REQUEST);
        }

        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse(
            $this->statisticsService->getDepartementsSansDeclaration($filter, (int) $categorieId),
            $request
        );
    }

    #[Route('/suivi/collecte-donnees', name: 'api_stats_suivi_collecte_donnees', methods: ['GET'])]
    #[OA\Get(
        summary: 'Avancement de la collecte de données, semaine par semaine',
        description: 'Nouveaux biens enregistrés et biens mis à jour, par semaine ISO. `depuis` (YYYY-MM-DD) borne la période ; défaut : 26 dernières semaines.'
    )]
    #[OA\Parameter(name: 'depuis', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    public function suiviCollecteDonnees(Request $request): Response
    {
        $depuisParam = $request->query->get('depuis');
        $depuis = null !== $depuisParam
            ? new \DateTimeImmutable($depuisParam)
            : (new \DateTimeImmutable())->modify('-26 weeks');

        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getSuiviCollecteHebdomadaire($filter, $depuis), $request);
    }

    #[Route('/suivi/points-attention-prioritaires', name: 'api_stats_suivi_points_attention_prioritaires', methods: ['GET'])]
    #[OA\Get(summary: 'Sites (terrains/bâtiments) prioritaires à sécuriser et leur statut de traitement')]
    public function suiviPointsAttentionPrioritaires(Request $request): Response
    {
        $filter = StatisticsFilter::fromRequest($request->query->all());
        return $this->statisticsService->formatResponse($this->statisticsService->getPointsAttentionPrioritaires($filter), $request);
    }
}