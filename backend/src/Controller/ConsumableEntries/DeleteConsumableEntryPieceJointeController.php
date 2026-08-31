<?php

namespace App\Controller\ConsumableEntries;

use App\Entity\ConsumableEntry;
use App\Exception\ResourceNotFoundException;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableEntryService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumable-entries')]
#[OA\Tag(name: 'Consomptibles')]
final class DeleteConsumableEntryPieceJointeController extends AbstractController
{
    #[Route('/{id}/piece-jointe/{pjId}', name: 'app_consumable_entry_delete_piece_jointe', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/consumable-entries/{id}/piece-jointe/{pjId}',
        summary: 'Supprimer une pièce jointe d\'une entrée de consomptible',
        description: 'Suppression d\'une pièce jointe (facture) liée à une entrée de consomptible.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Parameter(name: 'pjId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 5)]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Pièce jointe supprimée avec succès.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La pièce jointe demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        ConsumableEntry $entry,
        int $pjId,
        ConsumableEntryService $consumableEntryService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($entry->isDelete()) {
            return $apiResponse->error('Cette entrée est supprimée.', Response::HTTP_CONFLICT);
        }

        try {
            $consumableEntryService->removePieceJointe($entry, $pjId);
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Pièce jointe supprimée avec succès.'
        );
    }
}
