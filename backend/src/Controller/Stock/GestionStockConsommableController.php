<?php

namespace App\Controller\Stock;

use App\Service\ApiResponseFactory;
use App\Service\GestionStockConsommableService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gestion_stock_comsomtible')]
#[OA\Tag(name: 'Stock')]
class GestionStockConsommableController extends AbstractController
{
    public function __construct(
        private readonly GestionStockConsommableService $gestionStockService,
        private readonly ApiResponseFactory $apiResponse,
    ) {
    }

    #[Route('', name: 'app_gestion_stock_consommable', methods: ['GET'])]
    #[OA\Get(
        path: '/gestion_stock_comsomtible',
        summary: 'Récupérer la situation des stocks de consommables',
        description: 'Retourne la situation des stocks avec filtres optionnels par service, catégorie, consommable et recherche.'
    )]
    #[OA\Parameter(
        name: 'serviceId',
        description: 'Filtrer par service',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    // #[OA\Parameter(
    //     name: 'categorieId',
    //     description: 'Filtrer par catégorie',
    //     in: 'query',
    //     required: false,
    //     schema: new OA\Schema(type: 'integer')
    // )]
    // #[OA\Parameter(
    //     name: 'consumableId',
    //     description: 'Filtrer par consommable',
    //     in: 'query',
    //     required: false,
    //     schema: new OA\Schema(type: 'integer')
    // )]
    // #[OA\Parameter(
    //     name: 'search',
    //     description: 'Recherche par nom de consommable',
    //     in: 'query',
    //     required: false,
    //     schema: new OA\Schema(type: 'string')
    // )]
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
        schema: new OA\Schema(type: 'integer', default: 20)
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Situation des stocks récupérée avec succès.'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'summary', properties: [
                        new OA\Property(property: 'totalConsumables', type: 'integer', example: 2),
                        new OA\Property(property: 'totalQuantityInitial', type: 'string', example: '5000.00'),
                        new OA\Property(property: 'totalQuantityCurrent', type: 'string', example: '4900.00'),
                    ], type: 'object'),
                    new OA\Property(property: 'services', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'service', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 18),
                            new OA\Property(property: 'nom', type: 'string', example: 'Brigade de Contrôle et de Surveillance des Activités de Pêche'),
                        ], type: 'object'),
                        new OA\Property(property: 'totalConsumables', type: 'integer', example: 1),
                        new OA\Property(property: 'totalQuantityInitial', type: 'string', example: '5000.00'),
                        new OA\Property(property: 'totalQuantityReceived', type: 'string', example: '0.00'),
                        new OA\Property(property: 'totalQuantityTransferred', type: 'string', example: '2.00'),
                        new OA\Property(property: 'totalQuantityCurrent', type: 'string', example: '4898.00'),
                        new OA\Property(property: 'consumables', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'consumableId', type: 'integer', example: 17),
                            new OA\Property(property: 'nom', type: 'string', example: 'Papier A4'),
                            new OA\Property(property: 'quantityInitial', type: 'string', example: '5000'),
                            new OA\Property(property: 'quantityReceived', type: 'string', example: '0'),
                            new OA\Property(property: 'quantityTransferred', type: 'string', example: '2'),
                            new OA\Property(property: 'stockActuel', type: 'string', example: '4898'),
                        ], type: 'object')),
                    ], type: 'object')),
                    new OA\Property(property: 'pagination', properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 2),
                        new OA\Property(property: 'pages', type: 'integer', example: 1),
                    ], type: 'object'),
                ], type: 'object'),
            ]
        )
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $filters = [
            'serviceId' => $request->query->get('serviceId') ? (int) $request->query->get('serviceId') : null,
            'categorieId' => $request->query->get('categorieId') ? (int) $request->query->get('categorieId') : null,
            'consumableId' => $request->query->get('consumableId') ? (int) $request->query->get('consumableId') : null,
            'search' => $request->query->get('search'),
        ];

        // Nettoyer les filtres null
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, (int) $request->query->get('limit', 20));

        $data = $this->gestionStockService->getStockSituation($filters, $page, $limit);

        return $this->apiResponse->success($data, 200, 'Situation des stocks récupérée avec succès.');
    }
}
