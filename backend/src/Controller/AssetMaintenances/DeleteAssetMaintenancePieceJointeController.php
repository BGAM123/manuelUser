<?php

namespace App\Controller\AssetMaintenances;

use App\Entity\AssetMaintenance;
use App\Entity\PieceJointe;
use App\Service\ApiResponseFactory;
use App\Service\AssetMaintenanceService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-maintenances')]
#[OA\Tag(name: 'Asset Maintenances')]
final class DeleteAssetMaintenancePieceJointeController extends AbstractController
{
    public function __construct(
        private readonly AssetMaintenanceService $maintenanceService
    ) {
    }

    #[Route('/{maintenanceId}/pieces-jointes/{pieceJointeId}', name: 'app_asset_maintenance_piece_jointe_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-maintenances/{maintenanceId}/pieces-jointes/{pieceJointeId}',
        summary: 'Supprimer une pièce jointe d\'une maintenance',
        description: 'Supprime le fichier physique, l\'enregistrement PieceJointe et la liaison avec la maintenance.'
    )]
    #[OA\Parameter(name: 'maintenanceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 12)]
    #[OA\Parameter(name: 'pieceJointeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 5)]
    #[OA\Response(
        response: 200,
        description: 'Pièce jointe supprimée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Pièce jointe supprimée avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Maintenance ou pièce jointe introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Maintenance introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        int $maintenanceId,
        int $pieceJointeId,
        Request $request,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $entityManager = $this->maintenanceService->getEntityManager();
            $maintenance = $entityManager->find(AssetMaintenance::class, $maintenanceId);
            $pieceJointe = $entityManager->find(PieceJointe::class, $pieceJointeId);

            if (!$maintenance) {
                return $apiResponse->error('Maintenance introuvable.', Response::HTTP_NOT_FOUND);
            }

            if (!$pieceJointe) {
                return $apiResponse->error('Pièce jointe introuvable.', Response::HTTP_NOT_FOUND);
            }

            if (!$maintenance->getPieceJointes()->contains($pieceJointe)) {
                return $apiResponse->error('Cette pièce jointe n\'est pas associée à cette maintenance.', Response::HTTP_NOT_FOUND);
            }

            $this->maintenanceService->deletePieceJointe($maintenance, $pieceJointe);

            return $apiResponse->success(
                null,
                Response::HTTP_OK,
                'Pièce jointe supprimée avec succès.'
            );
        } catch (\Exception $e) {
            return $apiResponse->error(
                'Une erreur est survenue lors de la suppression de la pièce jointe.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
