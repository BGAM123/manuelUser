<?php

namespace App\Controller\Consumables;

use App\Entity\Consumable;
use App\Repository\ConsumableRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumables')]
#[OA\Tag(name: 'Consomptibles')]
final class DeleteConsumableController extends AbstractController
{
    #[Route('/{id}', name: 'app_consumable_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/consumables/{id}',
        summary: 'Supprimer (soft-delete) un consomptible',
        description: 'Suppression logique d\'un consomptible.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Consomptible supprimé avec succès.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le consomptible demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        Consumable $consumable,
        ConsumableRepository $consumableRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($consumable->isDelete()) {
            return $apiResponse->error('Ce consomptible est déjà supprimé.', Response::HTTP_CONFLICT);
        }

        $consumableRepository->softDelete($consumable);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Consomptible supprimé avec succès.'
        );
    }
}
