<?php

namespace App\Controller\AssetExits;

use App\Entity\AssetExit;
use App\Exception\ResourceInUseException;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-exits')]
#[OA\Tag(name: 'Asset Exits')]
final class ForceDeleteAssetExitController extends AbstractController
{
    #[Route('/{id}/force', name: 'app_asset_exit_force_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-exits/{id}/force',
        summary: 'Supprimer physiquement une sortie',
        description: 'Suppression physique définitive d\'une sortie. Attention : cette action est irréversible.'
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
                'message' => 'Sortie supprimée physiquement avec succès.',
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
                'message' => 'Cette sortie est utilisée dans d\'autres données et ne peut pas être supprimée physiquement.',
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
                'message' => 'La sortie demandée est introuvable.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        AssetExit $exit,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $forceDeleteService->delete($exit);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Sortie supprimée physiquement avec succès.'
        );
    }
}
