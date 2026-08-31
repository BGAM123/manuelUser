<?php

namespace App\Controller\Departements;

use App\Repository\DepartementRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/departements')]
#[OA\Tag(name: 'Departements')]
final class ListDepartementsController extends AbstractController
{
    #[Route('', name: 'app_departement_list', methods: ['GET'])]
    #[OA\Get(path: '/departements', summary: 'Lister les départements (paginé)')]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'region_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_delete', in: 'query', schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'))]
    #[OA\Response(
        response: 200,
        description: 'Success - liste paginée des départements retournée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Departements list returned successfully.',
                'data' => [
                    'meta' => [
                        'current_page' => 1,
                        'limit' => 10,
                        'total_items' => 1,
                        'total_pages' => 1,
                    ],
                    'data' => [
                        [
                            'id' => 1,
                            'nom' => 'Mfoundi',
                            'code' => null,
                            'is_delete' => false,
                            'region_id' => 1,
                            'region' => [
                                'id' => 1,
                                'nom' => 'Centre',
                            ],
                        ],
                    ],
                ],
            ]
        )
    )]
    public function __invoke(
        Request $request,
        DepartementRepository $departementRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $isDelete = $request->query->get('is_delete', 'false');

        $regionIdParam = $request->query->get('region_id');
        $regionId = null !== $regionIdParam ? (int) $regionIdParam : null;

        $departements = $departementRepository->findPaginatedDepartements($page, $limit, $isDelete, $regionId, $search);
        $total = $departementRepository->countAllDepartements($isDelete, $regionId, $search);

        $payload = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
            'data' => $departements,
        ];

        $json = $serializer->serialize($payload, 'json', ['groups' => ['departement:list']]);

        return $apiResponse->success(json_decode($json, true), Response::HTTP_OK, 'Departements list returned successfully.');
    }
}
