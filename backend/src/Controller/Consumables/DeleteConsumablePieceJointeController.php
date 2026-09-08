<?php

namespace App\Controller\Consumables;

use App\Entity\Consumable;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Security\ConsumableAccessChecker;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/consumables')]
#[OA\Tag(name: 'Consomptibles')]
final class DeleteConsumablePieceJointeController extends AbstractController
{
    #[Route('/{id}/piece-jointe/{pjId}', name: 'app_consumable_delete_piece_jointe', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/consumables/{id}/piece-jointe/{pjId}',
        summary: 'Supprimer une pièce jointe d\'un consomptible',
        description: 'Suppression d\'une pièce jointe liée à un consomptible.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Parameter(name: 'pjId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 5)]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Pièce jointe supprimée avec succès.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La pièce jointe demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        Consumable $consumable,
        int $pjId,
        #[CurrentUser] User $user,
        ConsumableAccessChecker $accessChecker,
        ConsumableService $consumableService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $accessChecker->assertCanAccessConsumable($user, $consumable);

        if ($consumable->isDelete()) {
            return $apiResponse->error('Ce consomptible est supprimé.', Response::HTTP_CONFLICT);
        }

        try {
            $consumableService->removePieceJointe($consumable, $pjId);
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
