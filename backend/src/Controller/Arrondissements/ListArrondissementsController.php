<?php

namespace App\Controller\Arrondissements;

use App\Repository\ArrondissementRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/arrondissements')]
#[OA\Tag(name: 'Arrondissements')]
final class ListArrondissementsController extends AbstractController
{
    #[Route('', name: 'app_arrondissement_list', methods: ['GET'])]
    #[OA\Get(
        path: '/arrondissements',
        summary: 'Lister les arrondissements (paginé)',
        description: 'Retourne la liste paginée des arrondissements. Filtres : departement_id, search, is_delete.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'departement_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filtrer par département')]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Recherche textuelle sur le nom')]
    #[OA\Parameter(name: 'is_delete', in: 'query', schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'), description: 'false (défaut) = actifs uniquement. true = corbeille uniquement. all = tous.')]
    #[OA\Response(
        response: 200,
        description: 'Success - liste paginée des arrondissements retournée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Arrondissements list returned successfully.',
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
                            'nom' => 'Yaoundé 1er',
                            'code' => null,
                            'is_delete' => false,
                            'departement_id' => 1,
                            'departement' => [
                                'id' => 1,
                                'nom' => 'Mfoundi',
                                'region' => [
                                    'id' => 1,
                                    'nom' => 'Centre',
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
        ArrondissementRepository $arrondissementRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $isDelete = $request->query->get('is_delete', 'false');

        $departementIdParam = $request->query->get('departement_id');
        $departementId = null !== $departementIdParam ? (int) $departementIdParam : null;

        $arrondissements = $arrondissementRepository->findPaginatedArrondissements($page, $limit, $isDelete, $departementId, $search);
        $total = $arrondissementRepository->countAllArrondissements($isDelete, $departementId, $search);

        $payload = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
            'data' => $arrondissements,
        ];

        $json = $serializer->serialize($payload, 'json', ['groups' => ['arrondissement:list']]);

        return $apiResponse->success(json_decode($json, true), Response::HTTP_OK, 'Arrondissements list returned successfully.');
    }
}
