<?php

namespace App\Controller\ConsumableTransfers;

use App\Entity\ConsumableTransfer;
use App\Entity\Notification;
use App\Entity\User;
use App\Exception\ValidationFailedException;
use App\Repository\ConsumableTransferRepository;
use App\Service\AcknowledgementService;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableTransferResponseBuilder;
use App\Service\ConsumableTransferService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/consumable-transfers')]
#[OA\Tag(name: 'Consomptibles-Transferts')]
final class BatchAcknowledgeConsumableTransferController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/acknowledge-batch', name: 'app_consumable_transfer_acknowledge_batch', methods: ['POST'])]
    #[OA\Post(
        path: '/consumable-transfers/acknowledge-batch',
        summary: 'Accuser réception de plusieurs transferts en une seule fois',
        description: "Réutilise AcknowledgementService (même contrôle de propriété que l'accusé unitaire : réservé au destinataire de chaque transfert). Une seule transaction : si un seul transfer_id échoue (introuvable, non autorisé, déjà accusé), aucun n'est validé."
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['transfer_ids'],
            properties: [
                new OA\Property(property: 'transfer_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                new OA\Property(property: 'commentaire', type: 'string', nullable: true, example: 'Lot reçu le 25/08.'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - tous les transferts ont été accusés réception',
        content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => '3 transfert(s) accusé(s) réception avec succès.', 'data' => ['acknowledged' => [1, 2, 3]]])
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request - au moins un transfert a échoué, aucun accusé n\'a été enregistré',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => "Le transfert #2 n'est pas autorisé pour cet utilisateur.", 'data' => ['failed_transfer_id' => 2]])
    )]
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        ConsumableTransferRepository $consumableTransferRepository,
        ConsumableTransferService $consumableTransferService,
        AcknowledgementService $acknowledgementService,
        ConsumableTransferResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $transferIds = is_array($payload) ? ($payload['transfer_ids'] ?? null) : null;

        if (!is_array($transferIds) || [] === $transferIds) {
            return $apiResponse->error(
                'transfer_ids est obligatoire et doit être un tableau non vide.',
                Response::HTTP_BAD_REQUEST,
                ['transfer_ids' => 'transfer_ids est obligatoire et doit être un tableau non vide.']
            );
        }

        $comment = is_array($payload) ? ($payload['commentaire'] ?? null) : null;

        $this->entityManager->beginTransaction();

        try {
            $acknowledgedIds = [];
            foreach ($transferIds as $transferId) {
                $transferId = (int) $transferId;
                $transfer = $consumableTransferRepository->getActiveById($transferId);
                if (!$transfer instanceof ConsumableTransfer) {
                    throw new ValidationFailedException(
                        ['failed_transfer_id' => $transferId],
                        sprintf('Le transfert #%d est introuvable.', $transferId)
                    );
                }

                $recipient = $consumableTransferService->resolveRecipient($transfer);
                if (!$recipient || $recipient->getId() !== $user->getId()) {
                    throw new ValidationFailedException(
                        ['failed_transfer_id' => $transferId],
                        sprintf('Le transfert #%d n\'est pas autorisé pour cet utilisateur.', $transferId)
                    );
                }

                // isAcknowledged/acknowledgedAt sont tenus à jour automatiquement par
                // AcknowledgementNotificationSubscriber sur AcknowledgementRecordedEvent,
                // déclenché par acknowledge() ci-dessous — pas besoin de les poser ici.
                $acknowledgementService->acknowledge($user, Notification::SUBJECT_CONSUMABLE_TRANSFER, $transferId, $comment);
                $acknowledgedIds[] = $transferId;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (ValidationFailedException $e) {
            $this->entityManager->rollback();

            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST, $e->getErrors());
        }

        return $apiResponse->success(
            ['acknowledged' => $acknowledgedIds],
            Response::HTTP_OK,
            sprintf('%d transfert(s) accusé(s) réception avec succès.', count($acknowledgedIds))
        );
    }
}
