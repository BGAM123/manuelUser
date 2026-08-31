<?php

namespace App\Controller\AssetDepreciations;

use App\Entity\AssetDepreciation;
use App\Exception\ResourceInUseException;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-depreciations')]
#[OA\Tag(name: 'Asset Depreciations')]
final class ForceDeleteAssetDepreciationController extends AbstractController
{
    #[Route('/{id}/force', name: 'app_asset_depreciation_force_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-depreciations/{id}/force',
        summary: 'Supprimer physiquement une dépréciation',
        description: 'Suppression physique définitive d\'une dépréciation. Attention : cette action est irréversible.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        example: 1
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Dépréciation supprimée physiquement avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request - Suppression impossible car la ressource est liée à d\'autres données',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'Cette dépréciation est utilisée dans d\'autres données et ne peut pas être supprimée physiquement.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 404,
                'message' => 'La dépréciation demandée est introuvable.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        AssetDepreciation $depreciation,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $forceDeleteService->delete($depreciation);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Dépréciation supprimée physiquement avec succès.'
        );
    }
}
