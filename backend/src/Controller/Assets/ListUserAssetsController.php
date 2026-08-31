<?php

namespace App\Controller\Assets;

use App\Repository\AssetRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users')]
#[OA\Tag(name: 'Users')]
final class ListUserAssetsController extends AbstractController
{
    #[Route('/{id}/assets', name: 'app_user_assets_list', methods: ['GET'])]
    #[OA\Get(
        path: '/users/{id}/assets',
        summary: 'Lister les biens patrimoniaux non supprimés d\'un utilisateur',
        description: 'Liste paginée des biens actifs affectés à un utilisateur spécifique via les affectations actuelles.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID de l\'utilisateur')]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Biens de l\'utilisateur retournés avec succès.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 5, 'total_pages' => 1],
                    'data' => [
                        [
                            "id" => 39,
                            "reference" => "PAT-2026-00039",
                            "nom" => "Ordinateur Portable HP ProBook 450 G10",
                            "categorie" => [
                                "id" => 1,
                                "nom" => "MATERIEL DE GUERRE"
                            ],
                            "typeBien" => [
                                "id" => 47,
                                "nom" => "Explosifs"
                            ],
                            "subtypeBien" => [
                                "id" => 47,
                                "nom" => "Explosifs"
                            ],
                            "structure" => [
                            "id" => 14,
                            "nom" => "Compkkkktabilité"
                            ],
                            "responsable" => [
                            "id" => 65,
                            "nom" => "NCA",
                            "prenom" => "Alex"
                            ],
                            "statut" => "ACTIF",
                            "sourceFinancement" => "patrimoine 2026",
                            "exercice" => 2027,
                            "valeur" => 850000,
                            "valeurInitiale" => 850000,
                            "dateAcquisition" => "2026-07-30",
                            "etatBien" => null,
                            "securise" => false
                        ],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Utilisateur introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Utilisateur introuvable.', 'data' => null]))]
    public function __invoke(
        int $id,
        Request $request,
        AssetRepository $assetRepository,
        AssetResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);

        // Récupérer les biens actifs de l'utilisateur
        $assets = $assetRepository->findPaginatedByUser($id, $page, $limit);
        $total = $assetRepository->countByUser($id);

        if (empty($assets) && $page > 1) {
            return $apiResponse->error('Page introuvable.', Response::HTTP_NOT_FOUND);
        }

        $data = array_map(static fn ($asset) => $responseBuilder->buildListUserItem($asset), $assets);

        $meta = [
            'current_page' => $page,
            'limit' => $limit,
            'total_items' => $total,
            'total_pages' => (int) ceil($total / max(1, $limit)),
        ];

        return $apiResponse->success([
            'meta' => $meta,
            'data' => $data,
        ], Response::HTTP_OK, 'Biens de l\'utilisateur retournés avec succès.');
    }
}
