<?php

namespace App\Controller\AssetSubTypes;

use App\Repository\AssetSubTypeRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/asset-sub-types')]
#[OA\Tag(name: 'AssetSubTypes')]
final class ListAssetSubTypesController extends AbstractController
{
    #[Route('', name: 'app_asset_sub_type_list', methods: ['GET'])]
    #[OA\Get(path: '/asset-sub-types', summary: 'Lister les sous-types de biens (paginé)')]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'asset_type_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'),
        description: 'false (défaut) = sous-types actifs uniquement. true = sous-types supprimés (corbeille) uniquement. all = tous.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - liste paginée des sous-types de biens retournée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Asset sub-types list returned successfully.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 1, 'total_pages' => 1],
                    'data' => [
                        ['id' => 1, 'nom' => 'Berline', 'asset_type_id' => 1, 'is_delete' => false],
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
        AssetSubTypeRepository $assetSubTypeRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $isDelete = $request->query->get('is_delete', 'false');

        $assetTypeIdParam = $request->query->get('asset_type_id');
        $assetTypeId = null !== $assetTypeIdParam ? (int) $assetTypeIdParam : null;

        $assetSubTypes = $assetSubTypeRepository->findPaginatedAssetSubTypes($page, $limit, $isDelete, $assetTypeId, $search);
        $total = $assetSubTypeRepository->countAllAssetSubTypes($isDelete, $assetTypeId, $search);

        $payload = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
            'data' => $assetSubTypes,
        ];

        $json = $serializer->serialize($payload, 'json', ['groups' => ['asset_sub_type:list']]);
        return $apiResponse->success(json_decode($json, true), Response::HTTP_OK, 'Asset sub-types list returned successfully.');
    }
}