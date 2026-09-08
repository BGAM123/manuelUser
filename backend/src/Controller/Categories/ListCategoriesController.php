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
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/categories')]
#[OA\Tag(name: 'Categories')]
final class ListCategoriesController extends AbstractController
{
    #[Route('', name: 'app_category_list', methods: ['GET'])]
    #[OA\Get(path: '/categories', summary: 'Lister les catégories de biens')]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Recherche partielle sur le nom')]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true'], default: 'false'),
        description: 'false (défaut) = catégories actives uniquement. true = catégories supprimées (corbeille) uniquement. all = toutes.'
    )]
    #[OA\Parameter(
        name: 'consommable',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true'], default: 'false'),
        description: 'false (défaut) = catégories non consommables uniquement. true = catégories consommables uniquement. all = toutes.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - liste paginée des catégories retournée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Categories list returned successfully.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 6, 'total_pages' => 1],
                    'data' => [
                        ['id' => 1, 'nom' => 'Véhicules', 'description' => null, 'is_delete' => false, 'seuil' => 1000000, 'ordre' => 1, 'consommable' => false],
                    ],
                ],
            ]
        )
    )]
    public function __invoke(
        Request $request,
        CategoryRepository $categoryRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $isDelete = $request->query->get('is_delete', 'false');

        // ✅ Récupérer le paramètre consommable
        $consommable = $request->query->get('consommable', 'all');

        // ✅ Appeler la méthode avec le nouveau paramètre
        $categories = $categoryRepository->findPaginatedCategories($page, $limit, $isDelete, $search, $consommable);

        $total = $categoryRepository->countAllCategories($isDelete, $search, $consommable);

        $payload = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
            'data' => $categories,
        ];

        $json = $serializer->serialize($payload, 'json', ['groups' => ['category:list']]);
        return $apiResponse->success(json_decode($json, true), Response::HTTP_OK, 'Categories list returned successfully.');
    }
}