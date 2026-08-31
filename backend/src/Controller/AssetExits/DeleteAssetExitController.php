<?php

namespace App\Controller\AssetExits;

use App\Entity\AssetExit;
use App\Service\ApiResponseFactory;
use App\Service\AssetExitService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-exits')]
#[OA\Tag(name: 'Asset Exits')]
final class DeleteAssetExitController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_exit_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-exits/{id}',
        summary: 'Supprimer une sortie de bien',
        description: 'Supprime une sortie de bien (soft delete). Le statut du bien est automatiquement restauré à ACTIF.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Sortie supprimée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Sortie supprimée avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Sortie introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Sortie introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetExit $exit,
        AssetExitService $exitService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($exit->isDelete()) {
            return $apiResponse->error('Cette sortie est déjà supprimée.', Response::HTTP_NOT_FOUND);
        }

        $exitService->delete($exit);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Sortie supprimée avec succès.'
        );
    }
}
