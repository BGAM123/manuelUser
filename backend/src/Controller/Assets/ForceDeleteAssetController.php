<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Exception\ResourceInUseException;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Assets')]
final class ForceDeleteAssetController extends AbstractController
{
    #[Route('/{id}/force', name: 'app_asset_force_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/assets/{id}/force',
        summary: 'Supprimer physiquement un bien',
        description: 'Suppression physique définitive d\'un bien. Attention : cette action est irréversible.'
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
                'message' => 'Bien supprimé physiquement avec succès.',
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
                'message' => 'Ce bien est utilisé dans d\'autres données et ne peut pas être supprimé physiquement.',
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
                'message' => 'Le bien demandé est introuvable.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        Asset $asset,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $forceDeleteService->delete($asset);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Bien supprimé physiquement avec succès.'
        );
    }
}
