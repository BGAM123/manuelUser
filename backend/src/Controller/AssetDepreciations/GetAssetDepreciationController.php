<?php

namespace App\Controller\AssetDepreciations;

use App\Entity\AssetDepreciation;
use App\Service\ApiResponseFactory;
use App\Service\AssetDepreciationResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-depreciations')]
#[OA\Tag(name: 'Asset Depreciations')]
final class GetAssetDepreciationController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_depreciation_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/asset-depreciations/{id}',
        summary: 'Détail d\'une dépréciation de bien',
        description: 'Retourne les détails d\'une dépréciation.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 3)]
    #[OA\Response(
        response: 200,
        description: 'Détail de la dépréciation',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Dépréciation trouvée.',
                'data' => [
                    'id' => 3,
                    'typeDepreciation' => 'Usure',
                    'methodeAmortissement' => 'Linéaire',
                    'dureeVie' => 10,
                    // 'valeurActuelle' => 2000000,
                    'tauxDepreciation' => 20,
                    'montantDepreciation' => 400000,
                    'dateDepreciation' => '2025-01-10',
                    'motif' => 'Dépréciation liée à l\'usage',
                    'observations' => null,
                    'piecesJointes' => [],
                    'createdAt' => '2026-08-01 11:20:00'
                ]
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Dépréciation introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Dépréciation introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetDepreciation $depreciation,
        AssetDepreciationResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        return $apiResponse->success(
            $responseBuilder->buildDetail($depreciation),
            Response::HTTP_OK,
            'Dépréciation trouvée.'
        );
    }
}
