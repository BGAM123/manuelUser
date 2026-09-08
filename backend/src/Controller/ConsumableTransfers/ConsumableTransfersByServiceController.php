<?php

namespace App\Controller\ConsumableTransfers;

use App\Entity\User;
use App\Repository\ConsumableTransferRepository;
use App\Security\ConsumableAccessChecker;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableTransferResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/consumable-transfers')]
#[OA\Tag(name: 'Consomptibles-Transferts')]
final class ConsumableTransfersByServiceController extends AbstractController
{
    #[Route('/by-service/{serviceId}', name: 'app_consumable_transfers_by_service', methods: ['GET'])]
    #[OA\Get(
        path: '/consumable-transfers/by-service/{serviceId}',
        summary: 'Lister les transferts vers un service',
        description: 'Retourne tous les transferts non supprimés vers un service donné.'
    )]
    #[OA\Parameter(name: 'serviceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 16)]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Transferts retournés avec succès.',
                'data' => [
                    [
                        'id' => 1,
                        'consumable' => ['id' => 1, 'nom' => 'Papier A4'],
                        'serviceDestination' => ['id' => 16, 'nom' => 'Direction des Systèmes d\'Information'],
                        'type' => 'TRANSFERT_DIRECT',
                        'statut' => 'TRANSFERE',
                        'quantite' => '500.00',
                        'dateTransfert' => '2026-08-16',
                        'observations' => 'Transfert pour usage interne',
                        'createdAt' => '2026-08-16 10:00:00',
                        'updatedAt' => '2026-08-16 10:00:00',
                    ],
                ],
            ]
        )
    )]
    public function __invoke(
        int $serviceId,
        #[CurrentUser] User $user,
        ConsumableTransferRepository $consumableTransferRepository,
        ConsumableAccessChecker $accessChecker,
        ConsumableTransferResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $accessChecker->assertCanAccessServiceId($user, $serviceId);

        $transfers = $consumableTransferRepository->findByService($serviceId);

        return $apiResponse->success(
            $responseBuilder->buildList($transfers),
            Response::HTTP_OK,
            'Transferts retournés avec succès.'
        );
    }
}
