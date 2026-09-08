<?php

namespace App\Controller\ConsumableTransfers;

use App\Entity\ConsumableTransfer;
use App\Entity\User;
use App\Exception\ResourceInUseException;
use App\Security\ConsumableAccessChecker;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableStockManager;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/consumable-transfers')]
#[OA\Tag(name: 'Consomptibles-Transferts')]
final class ForceDeleteConsumableTransferController extends AbstractController
{
    #[Route('/{id}/force', name: 'app_consumable_transfer_force_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/consumable-transfers/{id}/force',
        summary: 'Supprimer physiquement un transfert de consomptible',
        description: 'Suppression physique définitive d\'un transfert. Attention : cette action est irréversible.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Transfert supprimé physiquement avec succès.', 'data' => null]))]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le transfert demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        ConsumableTransfer $transfer,
        #[CurrentUser] User $user,
        ConsumableAccessChecker $accessChecker,
        ForceDeleteService $forceDeleteService,
        ConsumableStockManager $stockManager,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $accessChecker->assertCanAccessTransfer($user, $transfer);

        $consumable = $transfer->getConsumable();

        try {
            $forceDeleteService->delete($transfer);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $stockManager->recalculateAndPersist($consumable);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Transfert supprimé physiquement avec succès.'
        );
    }
}
