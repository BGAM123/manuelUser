<?php

namespace App\Controller\Categories;

use App\Repository\CategoryRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// #[Route('/categories')]
// #[OA\Tag(name: 'Categories')]
final class ListCategoryThresholdsController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository
    ) {
    }

    // #[Route('/thresholds/list', name: 'app_category_thresholds_list', methods: ['GET'])]
    #[OA\Get(
        path: '/categories/thresholds/list',
        summary: 'Lister les seuils de maintenance des catégories (paginé)',
        description: 'Retourne la liste paginée des catégories avec leurs seuils de maintenance.'
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'Numéro de la page',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Nombre d\'éléments par page',
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Parameter(
        name: 'category_ids',
        in: 'query',
        description: 'Filtrer par IDs de catégories (séparés par des virgules, ex: 1,2,3)',
        schema: new OA\Schema(type: 'string', example: '1,2,3')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Seuils des catégories retournés avec succès.',
                'data' => [
                    'meta' => [
                        'current_page' => 1,
                        'limit' => 10,
                        'total_items' => 25,
                        'total_pages' => 3
                    ],
                    'data' => [
                        [
                            'id' => 1,
                            'nom' => 'Matériel informatique',
                            'seuil' => 5
                        ],
                        [
                            'id' => 2,
                            'nom' => 'Véhicules',
                            'seuil' => 3
                        ]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'Format de category_ids invalide.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        Request $request,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // Récupérer les paramètres de pagination
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = min(100, max(1, $limit)); // Limiter entre 1 et 100
        
        // Récupérer les IDs de catégories
        $categoryIdsParam = $request->query->get('category_ids');
        $categoryIds = null;
        
        if ($categoryIdsParam !== null && $categoryIdsParam !== '') {
            $ids = array_map('intval', explode(',', $categoryIdsParam));
            $ids = array_filter($ids, fn($id) => $id > 0);
            
            if (!empty($ids)) {
                $categoryIds = $ids;
            }
        }

        // Récupérer les catégories avec leurs seuils (paginées)
        $categories = $this->categoryRepository->findThresholdsPaginated(
            $page,
            $limit,
            $categoryIds
        );
        
        // Compter le total
        $total = $this->categoryRepository->countThresholds($categoryIds);

        // Construire la réponse paginée
        $payload = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
            'data' => $categories,
        ];

        return $apiResponse->success(
            $payload,
            Response::HTTP_OK,
            'Seuils des catégories retournés avec succès.'
        );
    }
}