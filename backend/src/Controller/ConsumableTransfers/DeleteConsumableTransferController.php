<?php

namespace App\Controller\ConsumableTransfers;

use App\Entity\ConsumableTransfer;
use App\Repository\ConsumableTransferRepository;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableStockManager;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumable-transfers')]
#[OA\Tag(name: 'Consomptibles-Transferts')]
final class DeleteConsumableTransferController extends AbstractController
{
    #[Route('/{id}', name: 'app_consumable_transfer_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/consumable-transfers/{id}',
        summary: 'Supprimer (soft-delete) un transfert de consomptible',
        description: 'Suppression logique d\'un transfert de consomptible.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Transfert supprimé avec succès.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le transfert demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        ConsumableTransfer $transfer,
        ConsumableTransferRepository $consumableTransferRepository,
        ConsumableStockManager $stockManager,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($transfer->isDelete()) {
            return $apiResponse->error('Ce transfert est déjà supprimé.', Response::HTTP_CONFLICT);
        }

        $consumableTransferRepository->softDelete($transfer);
        $stockManager->recalculateAndPersist($transfer->getConsumable());

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Transfert supprimé avec succès.'
        );
    }
}
