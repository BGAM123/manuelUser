<?php

namespace App\Controller\ConsumableEntries;

use App\Entity\ConsumableEntry;
use App\Exception\ResourceInUseException;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableStockManager;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumable-entries')]
#[OA\Tag(name: 'Consomptibles')]
final class ForceDeleteConsumableEntryController extends AbstractController
{
    #[Route('/{id}/force', name: 'app_consumable_entry_force_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/consumable-entries/{id}/force',
        summary: 'Supprimer physiquement une entrée de consomptible',
        description: 'Suppression physique définitive d\'une entrée. Attention : cette action est irréversible.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Entrée supprimée physiquement avec succès.', 'data' => null]))]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'L\'entrée demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        ConsumableEntry $entry,
        ForceDeleteService $forceDeleteService,
        ConsumableStockManager $stockManager,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $consumable = $entry->getConsumable();

        try {
            $forceDeleteService->delete($entry);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $stockManager->recalculateAndPersist($consumable);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Entrée supprimée physiquement avec succès.'
        );
    }
}
