<?php

namespace App\Controller\Locations;

use App\Service\ApiResponseFactory;
use App\Service\TerritoryHierarchyBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/locations')]
#[OA\Tag(name: 'Locations')]
final class LocationsTreeController extends AbstractController
{
    #[Route('', name: 'app_locations', methods: ['GET'])]
    #[Route('/tree', name: 'app_locations_tree', methods: ['GET'])]
    #[OA\Get(
        path: '/locations',
        summary: 'Arbre territorial hiérarchique',
        description: "Retourne le découpage administratif complet : Région → Département → Arrondissement. Alias disponible : GET /locations/tree. Filtre search sur région, département et arrondissement simultanément."
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
        description: 'Recherche sur région, département et arrondissement',
        example: 'yaounde'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Arbre territorial récupéré avec succès.',
                'data' => [
                    [
                        'id' => 1,
                        'nom' => 'Centre',
                        'departements' => [
                            [
                                'id' => 1,
                                'nom' => 'Mfoundi',
                                'arrondissements' => [
                                    ['id' => 1, 'nom' => 'Yaoundé 1er'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    public function __invoke(
        Request $request,
        TerritoryHierarchyBuilder $territoryHierarchyBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $search = $request->query->get('search');
        $tree = $territoryHierarchyBuilder->build(is_string($search) ? $search : null);

        return $apiResponse->success($tree, Response::HTTP_OK, 'Arbre territorial récupéré avec succès.');
    }
}
