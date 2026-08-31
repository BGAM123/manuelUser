<?php

namespace App\Controller\ConsumableTransfers;

use App\Entity\ConsumableTransfer;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableTransferService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumable-transfers')]
#[OA\Tag(name: 'Consomptibles-Transferts')]
final class UpdateConsumableTransferController extends AbstractController
{
    #[Route('/{id}', name: 'app_consumable_transfer_update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/consumable-transfers/{id}',
        summary: 'Mettre à jour un transfert de consomptible',
        description: 'Modification partielle (multipart/form-data). Le type et le statut ne peuvent pas être modifiés.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'consumable_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'service_destination_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'quantite', type: 'number', nullable: true),
                        new OA\Property(property: 'dateTransfert', type: 'string', format: 'date', nullable: true),
                        new OA\Property(property: 'observations', type: 'string', nullable: true),
                        new OA\Property(property: 'piecesJointes[]', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), description: 'Documents à ajouter'),
                        new OA\Property(property: 'piecesJointesNoms[]', type: 'array', items: new OA\Items(type: 'string'), description: 'Noms des documents'),
                    ]
                )
            ),
            new OA\JsonContent(example: ['quantite' => 600, 'observations' => 'Transfert ajusté']),
        ]
    )]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Transfert mis à jour avec succès.', 'data' => ['id' => 1]]))]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le transfert demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        ConsumableTransfer $transfer,
        Request $request,
        ConsumableTransferService $consumableTransferService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($transfer->isDelete()) {
            return $apiResponse->error('Ce transfert est supprimé.', Response::HTTP_CONFLICT);
        }

        $contentType = (string) $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $payload = json_decode($request->getContent(), true);
            if (!is_array($payload)) {
                return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
            }
            $documents = [];
            $documentLabels = [];
        } else {
            $payload = $request->request->all();
            $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
            $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');
        }

        try {
            $transfer = $consumableTransferService->update($transfer, $payload, $documents, $documentLabels);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        }

        return $apiResponse->success(
            ['id' => $transfer->getId()],
            Response::HTTP_OK,
            'Transfert mis à jour avec succès.'
        );
    }
}
