<?php

namespace App\Controller\AssetReevaluations;

use App\Entity\AssetReevaluation;
use App\Service\ApiResponseFactory;
use App\Service\AssetReevaluationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-reevaluations')]
#[OA\Tag(name: 'Asset Reevaluations')]
final class DeleteAssetReevaluationController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_reevaluation_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-reevaluations/{id}',
        summary: 'Supprimer une réévaluation de bien',
        description: 'Supprime définitivement la réévaluation.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Réévaluation supprimée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Réévaluation supprimée avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Réévaluation introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Réévaluation introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetReevaluation $reevaluation,
        AssetReevaluationService $reevaluationService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $reevaluationService->delete($reevaluation);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Réévaluation supprimée avec succès.'
        );
    }
}
