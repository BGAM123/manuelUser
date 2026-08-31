<?php

namespace App\Controller\AssetReevaluations;

use App\Entity\AssetReevaluation;
use App\Service\ApiResponseFactory;
use App\Service\AssetReevaluationResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-reevaluations')]
#[OA\Tag(name: 'Asset Reevaluations')]
final class GetAssetReevaluationController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_reevaluation_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/asset-reevaluations/{id}',
        summary: 'Détail d\'une réévaluation de bien',
        description: 'Retourne les détails d\'une réévaluation.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Détail de la réévaluation',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Réévaluation trouvée.',
                'data' => [
                    'id' => 1,
                    'valeurActuelle' => 2000000,
                    'nouvelleValeur' => 2250000,
                    'methodeEvaluation' => 'Expertise',
                    'service' => ['id' => 6, 'nom' => 'Direction du Patrimoine'],
                    'dateReevaluation' => '2024-01-10',
                    'motif' => 'Réévaluation annuelle',
                    'observations' => null,
                    'piecesJointes' => [],
                    'createdAt' => '2026-08-01 11:00:00'
                ]
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Réévaluation introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Réévaluation introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetReevaluation $reevaluation,
        AssetReevaluationResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        return $apiResponse->success(
            $responseBuilder->buildDetail($reevaluation),
            Response::HTTP_OK,
            'Réévaluation trouvée.'
        );
    }
}
