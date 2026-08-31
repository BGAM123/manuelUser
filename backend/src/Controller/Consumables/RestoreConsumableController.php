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
final class RestoreConsumableController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_consumable_restore', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/consumables/{id}/restore',
        summary: 'Restaurer un consomptible',
        description: 'Restauration d\'un consomptible précédemment supprimé.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Consomptible restauré avec succès.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le consomptible demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        Consumable $consumable,
        ConsumableRepository $consumableRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (!$consumable->isDelete()) {
            return $apiResponse->error('Ce consomptible n\'est pas supprimé.', Response::HTTP_CONFLICT);
        }

        $consumableRepository->restore($consumable);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Consomptible restauré avec succès.'
        );
    }
}
