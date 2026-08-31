<?php

namespace App\Controller\AssetDepreciations;

use App\Entity\AssetDepreciation;
use App\Service\ApiResponseFactory;
use App\Service\AssetDepreciationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-depreciations')]
#[OA\Tag(name: 'Asset Depreciations')]
final class DeleteAssetDepreciationController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_depreciation_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-depreciations/{id}',
        summary: 'Supprimer une dépréciation de bien',
        description: 'Supprime définitivement la dépréciation.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 3)]
    #[OA\Response(
        response: 200,
        description: 'Dépréciation supprimée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Dépréciation supprimée avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Dépréciation introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Dépréciation introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetDepreciation $depreciation,
        AssetDepreciationService $depreciationService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $depreciationService->delete($depreciation);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Dépréciation supprimée avec succès.'
        );
    }
}
