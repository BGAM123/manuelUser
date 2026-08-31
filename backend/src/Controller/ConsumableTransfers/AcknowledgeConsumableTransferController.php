<?php

namespace App\Controller\ConsumableTransfers;

use App\Entity\ConsumableTransfer;
use App\Entity\Notification;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\ConsumableTransferRepository;
use App\Service\AcknowledgementService;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableTransferResponseBuilder;
use App\Service\ConsumableTransferService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/consumable-transfers')]
#[OA\Tag(name: 'Consomptibles-Transferts')]
final class AcknowledgeConsumableTransferController extends AbstractController
{
    #[Route('/{id}/acknowledge', name: 'app_consumable_transfer_acknowledge', methods: ['POST'])]
    #[OA\Post(
        path: '/consumable-transfers/{id}/acknowledge',
        summary: 'Accuser réception d\'un transfert de consomptible',
        description: "Réservé au destinataire du transfert (bénéficiaire BSP ou utilisateur du service de destination). Ne peut être fait qu'une seule fois par transfert."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'commentaire', type: 'string', nullable: true, example: 'Materiel bien recu.'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Accusé de réception enregistré')]
    #[OA\Response(response: 400, description: 'Déjà accusé réception', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'Un accusé de réception a déjà été enregistré pour cette transaction.', 'data' => ['acknowledgement' => 'Un accusé de réception a déjà été enregistré pour cette transaction.']]))]
    #[OA\Response(response: 403, description: 'Utilisateur non autorisé (pas le destinataire)')]
    #[OA\Response(response: 404, description: 'Transfert introuvable')]
    public function __invoke(
        int $id,
        Request $request,
        #[CurrentUser] User $user,
        ConsumableTransferRepository $consumableTransferRepository,
        ConsumableTransferService $consumableTransferService,
        AcknowledgementService $acknowledgementService,
        ConsumableTransferResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $transfer = $consumableTransferRepository->getActiveById($id);
        if (!$transfer instanceof ConsumableTransfer) {
            throw new ResourceNotFoundException('Le transfert demandé est introuvable.');
        }

        $recipient = $consumableTransferService->resolveRecipient($transfer);
        if (!$recipient || $recipient->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException("Vous n'êtes pas autorisé à accuser réception de ce transfert.");
        }

        $comment = $request->request->get('commentaire');

        try {
            $acknowledgementService->acknowledge($user, Notification::SUBJECT_CONSUMABLE_TRANSFER, $transfer->getId(), $comment);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST, $e->getErrors());
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($transfer),
            Response::HTTP_OK,
            'Accusé de réception enregistré avec succès.'
        );
    }
}
