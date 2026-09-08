<?php

namespace App\Controller;

use App\Service\ApiResponseFactory;
use App\Service\GlobalPatrimoineService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

/**
 * Contrôleur dédié à l'API globale du patrimoine.
 *
 * Ce contrôleur fournit une vue consolidée et structurée de l'ensemble du patrimoine
 * et des consommables, avec des regroupements par différents critères.
 *
 * L'API est en lecture seule (GET uniquement) et ne modifie aucune donnée.
 */
#[Route('/patrimoine_global')]
#[OA\Tag(name: 'PatrimoineGlobal')]
class PatrimoineGlobalController extends AbstractController
{
    public function __construct(
        private readonly GlobalPatrimoineService $globalPatrimoineService,
        private readonly ApiResponseFactory $apiResponseFactory
    ) {
    }

    /**
     * Récupère la vue globale complète du patrimoine et des consommables.
     *
     * @param Request $request Requête HTTP
     * @return JsonResponse Vue globale du patrimoine
     */
    #[Route('', name: 'api_patrimoine_global', methods: ['GET'])]
    #[OA\Get(
        path: '/patrimoine_global',
        summary: 'Récupérer la vue globale du patrimoine et des consommables',
        description: 'Retourne une vue consolidée et structurée de l\'ensemble du patrimoine et des consommables, avec des regroupements par différents critères.'
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Numéro de page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Nombre d\'éléments par page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Parameter(
        name: 'service',
        description: 'Filtrer par service (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '42,45')
    )]
    #[OA\Parameter(
        name: 'categorie',
        description: 'Filtrer par catégorie (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '12,35')
    )]
    #[OA\Parameter(
        name: 'type',
        description: 'Filtrer par type de bien (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '77,134')
    )]
    #[OA\Parameter(
        name: 'statut',
        description: 'Filtrer par statut (valeurs séparées par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'ACTIF,EN MAINTENANCE')
    )]
    #[OA\Parameter(
        name: 'etat',
        description: 'Filtrer par état de bien (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '2,4')
    )]
    #[OA\Parameter(
        name: 'sousType',
        description: 'Filtrer par sous-type de bien (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '10,15')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Vue globale du patrimoine récupérée avec succès.'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'PATRIMOINE_GLOBAL', properties: [
                        new OA\Property(property: 'BIENS', properties: [
                            new OA\Property(property: 'totalBiens', type: 'integer', example: 10),
                            new OA\Property(property: 'affectations', properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 43),
                                new OA\Property(property: 'parService', type: 'array', items: new OA\Items(type: 'object')),
                            ], type: 'object'),
                            new OA\Property(property: 'restitutions', properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 2),
                                new OA\Property(property: 'parService', type: 'array', items: new OA\Items(type: 'object')),
                            ], type: 'object'),
                            new OA\Property(property: 'statuts', type: 'object', example: ['ACTIF' => 8, 'EN MAINTENANCE' => 2, 'SORTIS' => 4]),
                            new OA\Property(property: 'Bien_parEtat', type: 'object'),
                            new OA\Property(property: 'Bien_parRegion', type: 'object'),
                            new OA\Property(property: 'Bien_parDepartement', type: 'object'),
                            new OA\Property(property: 'Bien_parArrondissement', type: 'object'),
                            new OA\Property(property: 'Bien_parService', type: 'object'),
                            new OA\Property(property: 'Bien_parProjet', type: 'object'),
                            new OA\Property(property: 'Bien_parCategorie', type: 'object'),
                            new OA\Property(property: 'Bien_parType', type: 'object'),
                            new OA\Property(property: 'Bien_parRegionDepartementCategorieEtat', type: 'object'),
                            new OA\Property(property: 'Bien_parCategorieRegion', type: 'object'),
                        ], type: 'object'),
                        new OA\Property(property: 'CONSOMMABLES', properties: [
                            new OA\Property(property: 'totalConsommables', type: 'integer', example: 15),
                            new OA\Property(property: 'Consommables_parService', type: 'object'),
                            new OA\Property(property: 'Consommables_parCategorie', type: 'object'),
                        ], type: 'object'),
                    ], type: 'object'),
                ], type: 'object'),
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Vue globale du patrimoine récupérée avec succès.',
                'data' => [
                    'PATRIMOINE_GLOBAL' => [
                        'BIENS' => [
                            'totalBiens' => 10,
                            'affectations' => [
                                'total' => 43,
                                'parService' => [
                                    [
                                        'service' => ['id' => 42, 'nom' => 'Comptable Matière Cab'],
                                        'total' => 1
                                    ],
                                    [
                                        'service' => ['id' => 49, 'nom' => 'Comptable Matière SG'],
                                        'total' => 2
                                    ]
                                ]
                            ],
                            'restitutions' => [
                                'total' => 2,
                                'parService' => [
                                    [
                                        'service' => ['id' => 15, 'nom' => 'Direction des Pâturages'],
                                        'total' => 1
                                    ]
                                ]
                            ],
                            'statuts' => [
                                'ACTIF' => 8,
                                'EN MAINTENANCE' => 2,
                                'SORTIE' => 4
                            ],
                            'Bien_parEtat' => [
                                'Bon' => [
                                    'total' => 4,
                                    'biens' => [
                                        'data' => [
                                            [
                                                'id' => 69,
                                                'reference' => 'PAT-2026-00029',
                                                'nom' => 'dafsd;lf/',
                                                'statut' => 'ACTIF',
                                                'etatBien' => ['id' => 2, 'nom' => 'Bon'],
                                                'categorie' => ['id' => 12, 'nom' => 'COMBUSTIBLE ET LUMINAIRE'],
                                                'typeBien' => ['id' => 77, 'nom' => 'Combustibles'],
                                                'structure' => null,
                                                'responsable' => ['id' => 38, 'nom' => 'Moreau', 'prenom' => 'Michelle'],
                                                'valeur' => 18000,
                                                'valeurInitiale' => '18000.00',
                                                'securise' => true,
                                                'received' => null
                                            ]
                                        ],
                                        'pagination' => [
                                            'page' => 1,
                                            'limit' => 100,
                                            'total' => 4,
                                            'pages' => 1
                                        ]
                                    ]
                                ]
                            ],
                            'Bien_parRegion' => [
                                'ADAMAOUA' => [
                                    'total' => 1,
                                    'biens' => [
                                        'data' => [
                                            [
                                                'id' => 72,
                                                'reference' => 'PAT-2026-00032',
                                                'nom' => 'testenzo',
                                                'statut' => 'ACTIF',
                                                'categorie' => ['id' => 29, 'nom' => 'TERRAINS'],
                                                'typeBien' => ['id' => 45, 'nom' => 'Terrain bâti'],
                                                'structure' => ['id' => 42, 'nom' => 'Comptable Matière Cab'],
                                                'responsable' => ['id' => 50, 'nom' => 'Vasseur', 'prenom' => 'Théophile'],
                                                'valeur' => 894,
                                                'etatBien' => ['id' => 7, 'nom' => 'Hors d\'usage']
                                            ]
                                        ],
                                        'pagination' => ['page' => 1, 'limit' => 100, 'total' => 1, 'pages' => 1]
                                    ]
                                ]
                            ],
                            'Bien_parDepartement' => [
                                'ABO' => [
                                    'total' => 1,
                                    'biens' => [
                                        'data' => [
                                            [
                                                'id' => 72,
                                                'reference' => 'PAT-2026-00032',
                                                'nom' => 'testenzo',
                                                'statut' => 'ACTIF',
                                                'categorie' => ['id' => 29, 'nom' => 'TERRAINS'],
                                                'typeBien' => ['id' => 45, 'nom' => 'Terrain bâti'],
                                                'structure' => ['id' => 42, 'nom' => 'Comptable Matière Cab'],
                                                'valeur' => 894
                                            ]
                                        ],
                                        'pagination' => ['page' => 1, 'limit' => 100, 'total' => 1, 'pages' => 1]
                                    ]
                                ]
                            ],
                            'Bien_parArrondissement' => [
                                'Ngaoundéré' => [
                                    'total' => 1,
                                    'biens' => [
                                        'data' => [
                                            [
                                                'id' => 72,
                                                'reference' => 'PAT-2026-00032',
                                                'nom' => 'testenzo',
                                                'statut' => 'ACTIF',
                                                'categorie' => ['id' => 29, 'nom' => 'TERRAINS'],
                                                'valeur' => 894
                                            ]
                                        ],
                                        'pagination' => ['page' => 1, 'limit' => 100, 'total' => 1, 'pages' => 1]
                                    ]
                                ]
                            ],
                            'Bien_parService' => [
                                'Comptable Matière Cab' => [
                                    'total' => 1,
                                    'biens' => [
                                        'data' => [
                                            [
                                                'id' => 72,
                                                'reference' => 'PAT-2026-00032',
                                                'nom' => 'testenzo',
                                                'statut' => 'ACTIF',
                                                'categorie' => ['id' => 29, 'nom' => 'TERRAINS'],
                                                'structure' => ['id' => 42, 'nom' => 'Comptable Matière Cab'],
                                                'valeur' => 894
                                            ]
                                        ],
                                        'pagination' => ['page' => 1, 'limit' => 100, 'total' => 1, 'pages' => 1]
                                    ]
                                ]
                            ],
                            'Bien_parProjet' => [
                                'Budget de l\'État (BIP)' => [
                                    'total' => 2,
                                    'biens' => [
                                        'data' => [
                                            [
                                                'id' => 69,
                                                'reference' => 'PAT-2026-00029',
                                                'nom' => 'dafsd;lf/',
                                                'statut' => 'ACTIF',
                                                'categorie' => ['id' => 12, 'nom' => 'COMBUSTIBLE ET LUMINAIRE'],
                                                'projet' => ['id' => 5, 'nom' => 'Budget de l\'État (BIP)'],
                                                'valeur' => 18000
                                            ]
                                        ],
                                        'pagination' => ['page' => 1, 'limit' => 100, 'total' => 2, 'pages' => 1]
                                    ]
                                ]
                            ],
                            'Bien_parCategorie' => [
                                'COMBUSTIBLE ET LUMINAIRE' => [
                                    'total' => 2,
                                    'biens' => [
                                        'data' => [
                                            [
                                                'id' => 69,
                                                'reference' => 'PAT-2026-00029',
                                                'nom' => 'dafsd;lf/',
                                                'statut' => 'ACTIF',
                                                'categorie' => ['id' => 12, 'nom' => 'COMBUSTIBLE ET LUMINAIRE'],
                                                'typeBien' => ['id' => 77, 'nom' => 'Combustibles'],
                                                'valeur' => 18000
                                            ]
                                        ],
                                        'pagination' => ['page' => 1, 'limit' => 100, 'total' => 2, 'pages' => 1]
                                    ]
                                ]
                            ],
                            'Bien_parType' => [
                                'Combustibles' => [
                                    'total' => 2,
                                    'biens' => [
                                        'data' => [
                                            [
                                                'id' => 69,
                                                'reference' => 'PAT-2026-00029',
                                                'nom' => 'dafsd;lf/',
                                                'statut' => 'ACTIF',
                                                'typeBien' => ['id' => 77, 'nom' => 'Combustibles'],
                                                'valeur' => 18000
                                            ]
                                        ],
                                        'pagination' => ['page' => 1, 'limit' => 100, 'total' => 2, 'pages' => 1]
                                    ]
                                ]
                            ],
                            'Bien_parRegionDepartementCategorieEtat' => [
                                'ADAMAOUA-ABO-TERRAINS-Hors d\'usage' => [
                                    'total' => 1,
                                    'biens' => [
                                        'data' => [
                                            [
                                                'id' => 72,
                                                'reference' => 'PAT-2026-00032',
                                                'nom' => 'testenzo',
                                                'statut' => 'ACTIF',
                                                'categorie' => ['id' => 29, 'nom' => 'TERRAINS'],
                                                'etatBien' => ['id' => 7, 'nom' => 'Hors d\'usage'],
                                                'valeur' => 894
                                            ]
                                        ],
                                        'pagination' => ['page' => 1, 'limit' => 100, 'total' => 1, 'pages' => 1]
                                    ]
                                ]
                            ],
                            'Bien_parCategorieRegion' => [
                                'TERRAINS-ADAMAOUA' => [
                                    'total' => 1,
                                    'biens' => [
                                        'data' => [
                                            [
                                                'id' => 72,
                                                'reference' => 'PAT-2026-00032',
                                                'nom' => 'testenzo',
                                                'statut' => 'ACTIF',
                                                'categorie' => ['id' => 29, 'nom' => 'TERRAINS'],
                                                'valeur' => 894
                                            ]
                                        ],
                                        'pagination' => ['page' => 1, 'limit' => 100, 'total' => 1, 'pages' => 1]
                                    ]
                                ]
                            ]
                        ],
                        'CONSOMMABLES' => [
                            'totalConsommables' => 15,
                            'Consommables_parService' => [
                                'Ministre' => [
                                    'total' => 8,
                                    'consommables' => [
                                        'data' => [
                                            [
                                                'id' => 54,
                                                'nom' => 'Huile végétale',
                                                'description' => 'Huile végétale',
                                                'categorie' => ['id' => 10, 'nom' => 'VIVRE'],
                                                'service' => ['id' => 38, 'nom' => 'Ministre'],
                                                'stockActuel' => '100.00',
                                                'prixInitial' => '1000.00'
                                            ]
                                        ],
                                        'pagination' => [
                                            'page' => 1,
                                            'limit' => 100,
                                            'total' => 8,
                                            'pages' => 1
                                        ]
                                    ]
                                ]
                            ],
                            'Consommables_parCategorie' => [
                                'VIVRE' => [
                                    'total' => 4,
                                    'consommables' => [
                                        'data' => [
                                            [
                                                'id' => 53,
                                                'nom' => 'Sucre',
                                                'description' => 'Sucre',
                                                'categorie' => ['id' => 10, 'nom' => 'VIVRE'],
                                                'service' => ['id' => 45, 'nom' => 'Secrétaire Général'],
                                                'stockActuel' => '7.00',
                                                'prixInitial' => '750.00'
                                            ]
                                        ],
                                        'pagination' => [
                                            'page' => 1,
                                            'limit' => 100,
                                            'total' => 4,
                                            'pages' => 1
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        )
    )]
    public function getGlobalPatrimoine(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(1000, (int) $request->query->get('limit', 10)));

            $filters = [
                'service' => $request->query->get('service'),
                'categorie' => $request->query->get('categorie'),
                'type' => $request->query->get('type'),
                'statut' => $request->query->get('statut'),
                'etat' => $request->query->get('etat'),
                'sousType' => $request->query->get('sousType'),
            ];

            // Nettoyer les filtres null
            $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

            $data = $this->globalPatrimoineService->getGlobalPatrimoine($page, $limit, $filters);

            return $this->apiResponseFactory->success($data, 200, 'Vue globale du patrimoine récupérée avec succès.');
        } catch (\Exception $e) {
            return $this->apiResponseFactory->error(
                'Erreur lors de la récupération des données globales : ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Récupère uniquement les données agrégées pour les consommables.
     *
     * @param Request $request Requête HTTP
     * @return JsonResponse Données agrégées des consommables
     */
    // #[Route('/consommables', name: 'api_patrimoine_global_consommables', methods: ['GET'])]
    #[OA\Get(
        path: '/patrimoine_global/consommables',
        summary: 'Récupérer uniquement les données globales des consommables',
        description: 'Retourne uniquement la section CONSOMMABLES de la vue globale, avec regroupements par service et catégorie.'
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Numéro de page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Nombre d\'éléments par page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Parameter(
        name: 'service',
        description: 'Filtrer par service (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '42,45')
    )]
    #[OA\Parameter(
        name: 'categorie',
        description: 'Filtrer par catégorie (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '12,35')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Données globales des consommables récupérées avec succès.'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'totalConsommables', type: 'integer'),
                    new OA\Property(property: 'Consommables_parService', type: 'object'),
                    new OA\Property(property: 'Consommables_parCategorie', type: 'object'),
                ], type: 'object'),
            ]
        )
    )]
    public function getGlobalConsommables(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(1000, (int) $request->query->get('limit', 10)));

            $filters = [
                'service' => $request->query->get('service'),
                'categorie' => $request->query->get('categorie'),
            ];

            // Nettoyer les filtres null
            $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

            $data = $this->globalPatrimoineService->getGlobalPatrimoine($page, $limit, $filters);

            return $this->apiResponseFactory->success($data['PATRIMOINE_GLOBAL']['CONSOMMABLES'], 200, 'Données globales des consommables récupérées avec succès.');
        } catch (\Exception $e) {
            return $this->apiResponseFactory->error(
                'Erreur lors de la récupération des données des consommables : ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Récupère uniquement les données agrégées pour les biens.
     *
     * @param Request $request Requête HTTP
     * @return JsonResponse Données agrégées des biens
     */
    // #[Route('/biens', name: 'api_patrimoine_global_biens', methods: ['GET'])]
    #[OA\Get(
        path: '/patrimoine_global/biens',
        summary: 'Récupérer uniquement les données globales des biens',
        description: 'Retourne uniquement la section BIENS de la vue globale, avec tous les regroupements.'
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Numéro de page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Nombre d\'éléments par page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Parameter(
        name: 'service',
        description: 'Filtrer par service (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '42,45')
    )]
    #[OA\Parameter(
        name: 'categorie',
        description: 'Filtrer par catégorie (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '12,35')
    )]
    #[OA\Parameter(
        name: 'type',
        description: 'Filtrer par type de bien (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '77,134')
    )]
    #[OA\Parameter(
        name: 'statut',
        description: 'Filtrer par statut (valeurs séparées par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'ACTIF,EN MAINTENANCE')
    )]
    #[OA\Parameter(
        name: 'etat',
        description: 'Filtrer par état de bien (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '2,4')
    )]
    #[OA\Parameter(
        name: 'sousType',
        description: 'Filtrer par sous-type de bien (ids séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: '10,15')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Données globales des biens récupérées avec succès.'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'totalBiens', type: 'integer'),
                    new OA\Property(property: 'affectations', type: 'object'),
                    new OA\Property(property: 'restitutions', type: 'object'),
                    new OA\Property(property: 'statuts', type: 'object'),
                    new OA\Property(property: 'Bien_parEtat', type: 'object'),
                    new OA\Property(property: 'Bien_parRegion', type: 'object'),
                    new OA\Property(property: 'Bien_parDepartement', type: 'object'),
                    new OA\Property(property: 'Bien_parArrondissement', type: 'object'),
                    new OA\Property(property: 'Bien_parService', type: 'object'),
                    new OA\Property(property: 'Bien_parProjet', type: 'object'),
                    new OA\Property(property: 'Bien_parCategorie', type: 'object'),
                    new OA\Property(property: 'Bien_parType', type: 'object'),
                    new OA\Property(property: 'Bien_parRegionDepartementCategorieEtat', type: 'object'),
                    new OA\Property(property: 'Bien_parCategorieRegion', type: 'object'),
                ], type: 'object'),
            ]
        )
    )]
    public function getGlobalBiens(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(1000, (int) $request->query->get('limit', 10)));

            $filters = [
                'service' => $request->query->get('service'),
                'categorie' => $request->query->get('categorie'),
                'type' => $request->query->get('type'),
                'statut' => $request->query->get('statut'),
                'etat' => $request->query->get('etat'),
                'sousType' => $request->query->get('sousType'),
            ];

            // Nettoyer les filtres null
            $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

            $data = $this->globalPatrimoineService->getGlobalPatrimoine($page, $limit, $filters);

            return $this->apiResponseFactory->success($data['PATRIMOINE_GLOBAL']['BIENS'], 200, 'Données globales des biens récupérées avec succès.');
        } catch (\Exception $e) {
            return $this->apiResponseFactory->error(
                'Erreur lors de la récupération des données des biens : ' . $e->getMessage(),
                500
            );
        }
    }
}
