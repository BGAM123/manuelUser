<?php

namespace App\Controller\Stock;

use App\DTO\StatisticsFilter;
use App\Service\StatisticsService;
use App\Service\StockResponseBuilder;
use App\Service\StockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

/**
 * Contrôleur unique pour l'API de gestion du stock des biens.
 * Point d'entrée unique pour toute l'analyse du stock.
 */
class GetStockBienController extends AbstractController
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly StockResponseBuilder $responseBuilder,
        private readonly StatisticsService $statisticsService
    ) {
    }

    /**
     * API unique de gestion du stock des biens.
     */
    #[Route('/gestion_stock_bien', name: 'gestion_stock_bien', methods: ['GET'])]
    #[OA\Get(
        path: '/gestion_stock_bien',
        summary: 'API de gestion du stock des biens',
        description: 'Retourne le résumé du stock et les biens paginés avec leurs filtres.',
        tags: ['Stock'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Numéro de page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                description: 'Nombre d\'éléments par page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 20)
            ),
            new OA\Parameter(
                name: 'category_id',
                description: 'Filtrer par catégorie',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'asset_type_id',
                description: 'Filtrer par type de bien',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'etat_bien_id',
                description: 'Filtrer par état de bien',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'service_id',
                description: 'Filtrer par service',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Rechercher par nom, référence ou numéro de série',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'situation',
                description: 'Filtrer par situation (AFFECTE, MAINTENANCE, NON AFFECTE)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'statut',
                description: 'Filtrer par statut du bien (ACTIF, INACTIF, SORTIS, EN MAINTENANCE)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'exercice',
                description: 'Filtrer par année d\'exercice du projet',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Gestion du stock des biens récupérée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Gestion du stock des biens récupérée avec succès'),
                        new OA\Property(property: 'data', properties: [
                            new OA\Property(property: 'summary', properties: [
                                new OA\Property(property: 'total_biens', type: 'integer', example: 150),
                                new OA\Property(property: 'total_sortis', type: 'integer', example: 25),
                                new OA\Property(property: 'total_patrimoine', type: 'integer', example: 125),
                                new OA\Property(property: 'patrimoine', properties: [
                                    new OA\Property(property: 'total', type: 'integer', example: 125),
                                    new OA\Property(property: 'actifs', type: 'integer', example: 100),
                                    new OA\Property(property: 'inactifs', type: 'integer', example: 25),
                                ], type: 'object'),
                                new OA\Property(property: 'sorties', properties: [
                                    new OA\Property(property: 'definitives', type: 'integer', example: 20),
                                ], type: 'object'),
                                new OA\Property(property: 'situations', properties: [
                                    new OA\Property(property: 'non_affectes', type: 'integer', example: 50),
                                    new OA\Property(property: 'affectes', type: 'integer', example: 60),
                                    new OA\Property(property: 'maintenance', type: 'integer', example: 15),
                                ], type: 'object'),
                                new OA\Property(property: 'affectations', properties: [
                                    new OA\Property(property: 'total', type: 'integer', example: 60),
                                    new OA\Property(property: 'utilisateurs', type: 'object', example: ['total' => 35]),
                                    new OA\Property(property: 'services', type: 'object', example: ['total' => 25]),
                                ], type: 'object'),
                            ], type: 'object'),
                            new OA\Property(property: 'assets', properties: [
                                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object'), example: [
                                    [
                                        'id' => 1,
                                        'nom' => 'Ordinateur Portable Dell',
                                        'reference' => 'DELL-001',
                                        'numero_serie' => 'SN123456',
                                        'statut' => 'ACTIF',
                                        'situation' => 'AFFECTE',
                                        'service' => ['id' => 18, 'nom' => 'Brigade de Contrôle'],
                                        'categorie' => ['id' => 1, 'nom' => 'Informatique']
                                    ]
                                ]),
                                new OA\Property(property: 'pagination', type: 'object', example: [
                                    'page' => 1,
                                    'limit' => 20,
                                    'total' => 150,
                                    'pages' => 8
                                ]),
                            ], type: 'object'),
                        ], type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, (int) $request->query->get('limit', 20));

        $filters = [
            'category_id' => $request->query->get('category_id'),
            'asset_type_id' => $request->query->get('asset_type_id'),
            'etat_bien_id' => $request->query->get('etat_bien_id'),
            'service_id' => $request->query->get('service_id'),
            'search' => $request->query->get('search'),
            'situation' => $request->query->get('situation'),
            'statut' => $request->query->get('statut'),
            'exercice' => $request->query->get('exercice'),
        ];

        $stockData = $this->stockService->getStockData(
            $page,
            $limit,
            $filters
        );

        $response = $this->responseBuilder->buildUnifiedResponse($stockData);

        return $this->json($response);
    }
}
