<?php

namespace App\Controller\ConsumableTransfers;

use App\Entity\ConsumableTransfer;
use App\Entity\User;
use App\Repository\ConsumableTransferRepository;
use App\Security\ConsumableAccessChecker;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableStockManager;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/consumable-transfers')]
#[OA\Tag(name: 'Consomptibles-Transferts')]
final class RestoreConsumableTransferController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_consumable_transfer_restore', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/consumable-transfers/{id}/restore',
        summary: 'Restaurer un transfert de consomptible',
        description: 'Restauration d\'un transfert précédemment supprimé.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Transfert restauré avec succès.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le transfert demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        ConsumableTransfer $transfer,
        #[CurrentUser] User $user,
        ConsumableTransferRepository $consumableTransferRepository,
        ConsumableAccessChecker $accessChecker,
        ConsumableStockManager $stockManager,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $accessChecker->assertCanAccessTransfer($user, $transfer);

        if (!$transfer->isDelete()) {
            return $apiResponse->error('Ce transfert n\'est pas supprimé.', Response::HTTP_CONFLICT);
        }

        $consumableTransferRepository->restore($transfer);
        $stockManager->recalculateAndPersist($transfer->getConsumable());

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Transfert restauré avec succès.'
        );
    }
}
