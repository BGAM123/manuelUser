<?php

namespace App\Controller\ConsumableEntries;

use App\Entity\ConsumableEntry;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableEntryService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumable-entries')]
#[OA\Tag(name: 'Consomptibles')]
final class UpdateConsumableEntryController extends AbstractController
{
    #[Route('/{id}', name: 'app_consumable_entry_update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/consumable-entries/{id}',
        summary: 'Mettre à jour une entrée de consomptible',
        description: 'Modification partielle (multipart/form-data).'
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
                        new OA\Property(property: 'service_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'quantite', type: 'number', nullable: true),
                        new OA\Property(property: 'dateEntree', type: 'string', format: 'date', nullable: true),
                        new OA\Property(property: 'observations', type: 'string', nullable: true),
                        new OA\Property(property: 'piecesJointes[]', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), description: 'Factures à ajouter'),
                        new OA\Property(property: 'piecesJointesNoms[]', type: 'array', items: new OA\Items(type: 'string'), description: 'Noms des factures'),
                    ]
                )
            ),
            new OA\JsonContent(example: ['quantite' => 1500, 'observations' => 'Réception mensuelle ajustée']),
        ]
    )]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Entrée mise à jour avec succès.', 'data' => ['id' => 1]]))]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'L\'entrée demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        ConsumableEntry $entry,
        Request $request,
        ConsumableEntryService $consumableEntryService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($entry->isDelete()) {
            return $apiResponse->error('Cette entrée est supprimée.', Response::HTTP_CONFLICT);
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
            $entry = $consumableEntryService->update($entry, $payload, $documents, $documentLabels);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        }

        return $apiResponse->success(
            ['id' => $entry->getId()],
            Response::HTTP_OK,
            'Entrée mise à jour avec succès.'
        );
    }
}
