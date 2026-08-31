<?php

namespace App\Controller\Regions;

use App\Repository\RegionRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/regions')]
#[OA\Tag(name: 'Regions')]
final class ListRegionsController extends AbstractController
{
    #[Route('', name: 'app_region_list', methods: ['GET'])]
    #[OA\Get(path: '/regions', summary: 'Lister les régions (paginé)')]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_delete', in: 'query', schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'))]
    #[OA\Response(response: 200, description: 'Success - liste paginée des régions retournée', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Regions list returned successfully.', 'data' => ['meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 1, 'total_pages' => 1], 'data' => []]]))]
    public function __invoke(
        Request $request,
        RegionRepository $regionRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $isDelete = $request->query->get('is_delete', 'false');

        $regions = $regionRepository->findPaginatedRegions($page, $limit, $isDelete, $search);
        $total = $regionRepository->countAllRegions($isDelete, $search);

        $payload = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
            'data' => $regions,
        ];

        $json = $serializer->serialize($payload, 'json', ['groups' => ['region:list']]);

        return $apiResponse->success(json_decode($json, true), Response::HTTP_OK, 'Regions list returned successfully.');
    }
}
