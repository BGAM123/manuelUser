<?php

namespace App\Controller\ConsumableTransfers;

use App\Entity\ConsumableTransfer;
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
final class ConsumableTransferDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_consumable_transfer_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/consumable-transfers/{id}',
        summary: 'Détail d\'un transfert de consomptible',
        description: 'Retourne le transfert complet avec ses pièces jointes.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Détail du transfert récupéré avec succès.',
                'data' => [
                    'id' => 1,
                    'consumable' => ['id' => 1, 'nom' => 'Papier A4'],
                    'serviceDestination' => ['id' => 16, 'nom' => 'Direction des Systèmes d\'Information'],
                    'type' => 'TRANSFERT_DIRECT',
                    'statut' => 'TRANSFERE',
                    'quantite' => '500.00',
                    'dateTransfert' => '2026-08-16',
                    'observations' => 'Transfert pour usage interne',
                    'pieceJointes' => [
                        ['id' => 1, 'nom' => 'Document 1', 'chemin' => '/uploads/consumable-transfers/doc.pdf'],
                    ],
                    'consumableBsp' => null,
                    'createdAt' => '2026-08-16 10:00:00',
                    'updatedAt' => '2026-08-16 10:00:00',
                    'accuseReception' => [
                        'effectue' => true,
                        'date' => '2026-08-16 11:00:00',
                        'par' => ['id' => 12, 'nom' => 'Doe', 'prenom' => 'Jane'],
                        'commentaire' => 'Materiel bien recu.',
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le transfert demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        int $id,
        #[CurrentUser] User $user,
        ConsumableTransferRepository $consumableTransferRepository,
        ConsumableAccessChecker $accessChecker,
        ConsumableTransferResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $transfer = $consumableTransferRepository->getActiveById($id);
        if (!$transfer instanceof ConsumableTransfer) {
            return $apiResponse->error('Le transfert demandé est introuvable.', Response::HTTP_NOT_FOUND);
        }

        $accessChecker->assertCanAccessTransfer($user, $transfer);

        return $apiResponse->success(
            $responseBuilder->buildDetail($transfer),
            Response::HTTP_OK,
            'Détail du transfert récupéré avec succès.'
        );
    }
}
