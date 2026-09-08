<?php

namespace App\Controller\ConsumableTransfers;

use App\Entity\ConsumableTransfer;
use App\Entity\User;
use App\Repository\ConsumableTransferRepository;
use App\Security\ConsumableAccessChecker;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableStockManager;
use App\Service\ConsumableTransferResponseBuilder;
use App\Service\ConsumableTransferStockManager;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/consumable-transfers')]
#[OA\Tag(name: 'Consomptibles-Transferts')]
final class ConsumeConsumableTransferController extends AbstractController
{
    #[Route('/{id}/consume', name: 'app_consumable_transfer_consume', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/consumable-transfers/{id}/consume',
        summary: 'Enregistrer la quantité consommée sur un transfert',
        description: 'Pose la quantité consommée (valeur absolue, pas un incrément). Règle : quantityConsumed <= quantite (la quantité transférée = reçue par le service de destination). Recalcule et persiste le stock actuel du consomptible.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['quantityConsumed'],
            properties: [
                new OA\Property(property: 'quantityConsumed', type: 'number', format: 'decimal', example: 120),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Quantité consommée enregistrée avec succès.', 'data' => ['id' => 1, 'quantite' => '500.00', 'quantityConsumed' => '120.00']])
    )]
    #[OA\Response(response: 400, description: 'Bad Request - quantité invalide ou supérieure à la quantité reçue', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La quantité consommée ne peut pas dépasser la quantité reçue (500.00).', 'data' => ['quantityConsumed' => 'La quantité consommée ne peut pas dépasser la quantité reçue (500.00).']]))]
    #[OA\Response(response: 403, description: "Forbidden - seul le service destinataire du transfert peut renseigner la quantité consommée (aucune dérogation, même admin)")]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le transfert demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        int $id,
        Request $request,
        #[CurrentUser] User $user,
        ConsumableTransferRepository $consumableTransferRepository,
        ConsumableAccessChecker $accessChecker,
        ConsumableStockManager $stockManager,
        ConsumableTransferStockManager $transferStockManager,
        ConsumableTransferResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $transfer = $consumableTransferRepository->getActiveById($id);
        if (!$transfer instanceof ConsumableTransfer) {
            return $apiResponse->error('Le transfert demandé est introuvable.', Response::HTTP_NOT_FOUND);
        }

        // Restriction sans dérogation admin : chaque service remplit sa propre
        // consommation, même un compte administrateur ne peut pas le faire à sa place.
        $accessChecker->assertCanConsume($user, $transfer);

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !array_key_exists('quantityConsumed', $payload) || !is_numeric($payload['quantityConsumed'])) {
            return $apiResponse->error(
                'quantityConsumed est obligatoire et doit être un nombre.',
                Response::HTTP_BAD_REQUEST,
                ['quantityConsumed' => 'quantityConsumed est obligatoire et doit être un nombre.']
            );
        }

        $quantityConsumed = (float) $payload['quantityConsumed'];
        // Sur un transfert INITIAL, "quantite" vaut 0 (ce n'est pas un vrai transfert) : la
        // quantité réellement disponible pour ce service est la quantité initiale du
        // consomptible, portée par stockActuel à la création de ce transfert.
        $quantityReceived = $transfer->getStatut() === ConsumableTransfer::STATUT_INITIAL
            ? (float) $transfer->getConsumable()->getQuantite()
            : (float) $transfer->getQuantite();

        if ($quantityConsumed < 0) {
            return $apiResponse->error(
                'La quantité consommée ne peut pas être négative.',
                Response::HTTP_BAD_REQUEST,
                ['quantityConsumed' => 'La quantité consommée ne peut pas être négative.']
            );
        }

        if ($quantityConsumed > $quantityReceived) {
            $message = sprintf('La quantité consommée ne peut pas dépasser la quantité reçue (%s).', $transfer->getQuantite());

            return $apiResponse->error($message, Response::HTTP_BAD_REQUEST, ['quantityConsumed' => $message]);
        }

        $transfer->setQuantityConsumed((string) $quantityConsumed);
        $consumableTransferRepository->save($transfer);
        $stockManager->recalculateAndPersist($transfer->getConsumable());

        // Rafraîchit le stockActuel persisté sur les transferts du service destinataire
        // (celui affiché dans la liste des transferts), qui doit refléter la consommation.
        if ($transfer->getServiceDestination()) {
            $transferStockManager->recalculateAllStocks(
                $transfer->getConsumable()->getId(),
                $transfer->getServiceDestination()->getId()
            );
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($transfer),
            Response::HTTP_OK,
            'Quantité consommée enregistrée avec succès.'
        );
    }
}
