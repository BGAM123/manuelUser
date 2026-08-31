<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Repository\AssetRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Assets')]
final class SoftDeleteAssetController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_asset_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/assets/{id}/soft-delete',
        summary: 'Suppression logique d\'un bien',
        description: 'Passe is_delete à true.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Bien supprimé avec succès.', 'data' => null]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le bien demandé est introuvable.', 'data' => null])
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 409, description: 'Conflict - Déjà supprimé', content: new OA\JsonContent(example: ['success' => false, 'status' => 409, 'message' => 'Ce bien est déjà supprimé.', 'data' => null]))]
    public function __invoke(
        Asset $asset,
        AssetRepository $assetRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est déjà supprimé.', Response::HTTP_CONFLICT);
        }

        $assetRepository->softDelete($asset);

        return $apiResponse->success(null, Response::HTTP_OK, 'Bien supprimé avec succès.');
    }
}
