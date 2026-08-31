<?php

namespace App\Controller\EtatBiens;

use App\Repository\EtatBienRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/etat-biens')]
#[OA\Tag(name: 'EtatBiens')]
final class ListEtatBiensController extends AbstractController
{
    #[Route('', name: 'app_etat_bien_list', methods: ['GET'])]
    #[OA\Get(
        path: '/etat-biens',
        summary: 'Lister les états de biens',
        description: 'Liste paginée des états de biens avec leurs types associés. Filtrable par asset_type_id.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_delete', in: 'query', schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'))]
    #[OA\Parameter(
        name: 'asset_type_id',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer les états liés à un type de bien'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'États de biens retournés avec succès.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 1, 'total_pages' => 1],
                    'data' => [
                        [
                            'id' => 1,
                            'nom' => 'En panne',
                            'numeroOrdre' => 1,
                            'description' => 'Bien en panne de fonctionnement',
                            'is_delete' => false,
                            'assetTypes' => [
                                ['id' => 1, 'nom' => 'Véhicule'],
                                ['id' => 3, 'nom' => 'Groupe électrogène'],
                            ],
                        ],
                    ],
                ],
            ]
        )
    )]
    public function __invoke(
        Request $request,
        EtatBienRepository $etatBienRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $isDelete = $request->query->get('is_delete', 'false');
        $assetTypeId = $request->query->has('asset_type_id') ? $request->query->getInt('asset_type_id') : null;

        $items = $etatBienRepository->findPaginated($page, $limit, $isDelete, $search, $assetTypeId);
        $total = $etatBienRepository->countAll($isDelete, $search, $assetTypeId);

        $payload = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / max(1, $limit)),
            ],
            'data' => $items,
        ];

        $json = $serializer->serialize($payload, 'json', ['groups' => ['etat_bien:list']]);

        return $apiResponse->success(json_decode($json, true), Response::HTTP_OK, 'États de biens retournés avec succès.');
    }
}
