<?php

namespace App\Controller\ConsumableEntries;

use App\Entity\ConsumableEntry;
use App\Repository\ConsumableEntryRepository;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableStockManager;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumable-entries')]
#[OA\Tag(name: 'Consomptibles')]
final class DeleteConsumableEntryController extends AbstractController
{
    #[Route('/{id}', name: 'app_consumable_entry_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/consumable-entries/{id}',
        summary: 'Supprimer (soft-delete) une entrée de consomptible',
        description: 'Suppression logique d\'une entrée de consomptible.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Entrée supprimée avec succès.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'L\'entrée demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        ConsumableEntry $entry,
        ConsumableEntryRepository $consumableEntryRepository,
        ConsumableStockManager $stockManager,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($entry->isDelete()) {
            return $apiResponse->error('Cette entrée est déjà supprimée.', Response::HTTP_CONFLICT);
        }

        $consumableEntryRepository->softDelete($entry);
        $stockManager->recalculateAndPersist($entry->getConsumable());

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Entrée supprimée avec succès.'
        );
    }
}
