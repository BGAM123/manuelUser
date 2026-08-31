<?php

namespace App\Controller\AssetMaintenances;

use App\Entity\AssetMaintenance;
use App\Service\ApiResponseFactory;
use App\Service\AssetMaintenanceService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-maintenances')]
#[OA\Tag(name: 'Asset Maintenances')]
final class DeleteAssetMaintenanceController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_maintenance_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-maintenances/{id}',
        summary: 'Supprimer une maintenance de bien',
        description: 'Supprime définitivement la maintenance.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 12)]
    #[OA\Response(
        response: 200,
        description: 'Maintenance supprimée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Maintenance supprimée avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Maintenance introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Maintenance introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetMaintenance $maintenance,
        AssetMaintenanceService $maintenanceService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $maintenanceService->delete($maintenance);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Maintenance supprimée avec succès.'
        );
    }
}
