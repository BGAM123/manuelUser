<?php

namespace App\Controller\AssetTypes;

use App\Repository\AssetTypeRepository;
use App\Service\ApiResponseFactory;
use App\Service\DefaultAssetReferencesService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/asset-types')]
#[OA\Tag(name: 'AssetTypes')]
final class ListAssetTypesController extends AbstractController
{
    #[Route('', name: 'app_asset_type_list', methods: ['GET'])]
    #[OA\Get(
        path: '/asset-types',
        summary: 'Lister les types de biens (paginé)',
        description: 'Retourne la liste paginée des types de biens. Filtres : category_id, search, is_delete.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'),
        description: 'false (défaut) = types de biens actifs uniquement. true = types de biens supprimés (corbeille) uniquement. all = tous les types de biens, actifs et supprimés.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - liste paginée des types de biens retournée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Liste des types de biens retournée avec succès.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 3, 'total_pages' => 1],
                    'data' => [
                        ['id' => 4, 'nom' => 'Véhicule léger', 'category_id' => 1, 'dureeVie' => 10, 'taux' => '20.00', 'is_delete' => false],
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
        AssetTypeRepository $assetTypeRepository,
        DefaultAssetReferencesService $defaultAssetReferences,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $isDelete = $request->query->get('is_delete', 'false');

        $categoryIdParam = $request->query->get('category_id');
        $requestedCategoryId = null !== $categoryIdParam ? (int) $categoryIdParam : null;

        // Même règle que pour /assets : un category_id soft-deleté ne doit jamais être
        // traité comme actif, on le résout vers la catégorie active ou par défaut.
        $categoryResolution = null;
        $categoryId = null;
        if (null !== $requestedCategoryId) {
            $resolvedCategory = $defaultAssetReferences->resolveActiveCategoryOrDefault($requestedCategoryId);
            $categoryId = $resolvedCategory->getId();
            $categoryResolution = $defaultAssetReferences->describeCategoryResolution($requestedCategoryId, $resolvedCategory);
        }

        $assetTypes = $assetTypeRepository->findPaginatedAssetTypes($page, $limit, $isDelete, $categoryId, $search);
        $total = $assetTypeRepository->countAllAssetTypes($isDelete, $categoryId, $search);

        $meta = [
            'current_page' => $page,
            'limit' => $limit,
            'total_items' => $total,
            'total_pages' => (int) ceil($total / $limit),
        ];
        if (null !== $categoryResolution) {
            $meta['category_filter'] = $categoryResolution;
        }

        $payload = [
            'meta' => $meta,
            'data' => $assetTypes,
        ];

        $json = $serializer->serialize($payload, 'json', ['groups' => ['asset_type:list']]);
        return $apiResponse->success(json_decode($json, true), Response::HTTP_OK, 'Asset types list returned successfully.');
    }
}
