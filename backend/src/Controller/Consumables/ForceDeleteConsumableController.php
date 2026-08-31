<?php

namespace App\Controller\Consumables;

use App\Entity\Consumable;
use App\Exception\ResourceInUseException;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumables')]
#[OA\Tag(name: 'Consomptibles')]
final class ForceDeleteConsumableController extends AbstractController
{
    #[Route('/{id}/force', name: 'app_consumable_force_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/consumables/{id}/force',
        summary: 'Supprimer physiquement un consomptible',
        description: 'Suppression physique définitive d\'un consomptible. Attention : cette action est irréversible.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Consomptible supprimé physiquement avec succès.', 'data' => null]))]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le consomptible demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        Consumable $consumable,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $forceDeleteService->delete($consumable);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Consomptible supprimé physiquement avec succès.'
        );
    }
}
