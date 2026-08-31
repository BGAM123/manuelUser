<?php

namespace App\Controller\AssetMaintenances;

use App\Entity\AssetMaintenance;
use App\Exception\ResourceInUseException;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-maintenances')]
#[OA\Tag(name: 'Asset Maintenances')]
final class ForceDeleteAssetMaintenanceController extends AbstractController
{
    #[Route('/{id}/force', name: 'app_asset_maintenance_force_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-maintenances/{id}/force',
        summary: 'Supprimer physiquement une maintenance',
        description: 'Suppression physique définitive d\'une maintenance. Attention : cette action est irréversible.'
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
                'message' => 'Maintenance supprimée physiquement avec succès.',
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
                'message' => 'Cette maintenance est utilisée dans d\'autres données et ne peut pas être supprimée physiquement.',
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
                'message' => 'La maintenance demandée est introuvable.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        AssetMaintenance $maintenance,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $forceDeleteService->delete($maintenance);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Maintenance supprimée physiquement avec succès.'
        );
    }
}
