<?php

namespace App\Controller\TypeOrganigrammes;

use App\Entity\TypeOrganigramme;
use App\Repository\TypeOrganigrammeRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/type-organigrammes')]
#[OA\Tag(name: 'TypeOrganigrammes')]
final class ListTypeOrganigrammeController extends AbstractController
{
    #[Route('', name: 'app_type_organigramme_list', methods: ['GET'])]
    #[OA\Get(
        path: '/type-organigrammes',
        summary: 'Lister les types d\'organigrammes',
        description: 'Retourne la liste paginée des types d\'organigrammes.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string', description: 'Recherche par nom'))]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'),
        description: 'false (défaut) = types actifs uniquement. true = types supprimés (corbeille) uniquement. all = tous.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Liste paginée des types d\'organigrammes',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Types d\'organigrammes list returned successfully.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Types d\'organigrammes list returned successfully.',
                'data' => [
                    'meta' => [
                        'current_page' => 1,
                        'limit' => 10,
                        'total_items' => 2,
                        'total_pages' => 1
                    ],
                    'data' => [
                        [
                            'id' => 1,
                            'nom' => 'Organigramme Administratif'
                        ],
                        [
                            'id' => 2,
                            'nom' => 'Organigramme Fonctionnel'
                        ]
                    ]
                ]
            ]
        )
    )]
    public function __invoke(
        Request $request,
        TypeOrganigrammeRepository $typeOrganigrammeRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = (int) $request->query->get('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $searchQuery = $request->query->get('q');
        $isDelete = $request->query->get('is_delete', 'false');

        $types = $typeOrganigrammeRepository->findPaginatedTypes($page, $limit, $searchQuery, $isDelete);
        $total = $typeOrganigrammeRepository->countTypes($searchQuery, $isDelete);

        $data = array_map(fn(TypeOrganigramme $type) => [
            'id' => $type->getId(),
            'nom' => $type->getNom()
        ], $types);

        $payload = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
            'data' => $data,
        ];

        return $apiResponse->success($payload, Response::HTTP_OK, 'Types d\'organigrammes list returned successfully.');
    }
}
