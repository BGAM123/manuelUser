<?php

namespace App\Controller\Champs;

use App\Repository\ChampRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/champs')]
#[OA\Tag(name: 'Champs')]
final class ListChampsController extends AbstractController
{
    #[Route('', name: 'app_champ_list', methods: ['GET'])]
    #[OA\Get(path: '/champs', summary: 'Lister les champs (paginé)', description: 'Liste paginée des champs personnalisés avec leurs catégories associées.')]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_delete', in: 'query', schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'))]
    #[OA\Parameter(name: 'category_ids', in: 'query', description: 'Filtrer par IDs de catégories (séparés par virgules, ex: 5,6)', schema: new OA\Schema(type: 'string', example: '5,6'))]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Champs retournés avec succès.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 1, 'total_pages' => 1],
                    'data' => [
                        [
                            'id' => 1,
                            'nom' => 'Couleur',
                            'type' => 'text',
                            'subtype' => 'email',
                            'option' => 'Rouge,Vert,Bleu',
                            // 'isObligatoire' => false,
                            // 'isMultiple' => false,
                            'is_delete' => false,
                            'categories' => [
                                [
                                    'id' => 2,
                                    'nom' => 'Matériel Roulant'
                                ]
                            ]
                        ],
                    ],
                ],
            ]
        )
    )]
    public function __invoke(
        Request $request,
        ChampRepository $champRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $isDelete = $request->query->get('is_delete', 'false');
        $categoryIdsParam = $request->query->get('category_ids');
        $categoryIds = null;
        if ($categoryIdsParam) {
            $categoryIds = array_map('intval', explode(',', $categoryIdsParam));
        }

        $items = $champRepository->findPaginated($page, $limit, $isDelete, $search, $categoryIds);
        $total = $champRepository->countAll($isDelete, $search, $categoryIds);

        $payload = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
            'data' => $items,
        ];

        $json = $serializer->serialize($payload, 'json', ['groups' => ['champ:list']]);

        return $apiResponse->success(json_decode($json, true), Response::HTTP_OK, 'Champs retournés avec succès.');
    }
}
