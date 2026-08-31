<?php

namespace App\Controller\Cartographie;

use App\Service\ApiResponseFactory;
use App\Service\TerritoryHierarchyBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cartographie')]
#[OA\Tag(name: 'Cartographie')]
final class CartographieController extends AbstractController
{
    #[Route('', name: 'app_cartographie', methods: ['GET'])]
    #[OA\Get(
        path: '/cartographie',
        summary: 'Cartographie territoriale hiérarchique',
        description: "Retourne le découpage territorial sous forme d'arbre Région → Département → Arrondissement. Le filtre search s'applique simultanément sur région, département et arrondissement."
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
                'message' => 'Cartographie récupérée avec succès.',
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
                                    ['id' => 2, 'nom' => 'Yaoundé 2ème'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    public function __invoke(
        Request $request,
        TerritoryHierarchyBuilder $territoryHierarchyBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $search = $request->query->get('search');
        $tree = $territoryHierarchyBuilder->build(is_string($search) ? $search : null);

        return $apiResponse->success($tree, Response::HTTP_OK, 'Cartographie récupérée avec succès.');
    }
}
