<?php

namespace App\Controller\ConsumableEntries;

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
final class CreateConsumableEntryController extends AbstractController
{
    #[Route('', name: 'app_consumable_entry_create', methods: ['POST'])]
    #[OA\Post(
        path: '/consumable-entries',
        summary: 'Créer une entrée de consomptible',
        description: 'Création multipart/form-data. Les pièces jointes représentent les factures.'
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'consumable_id', type: 'integer', example: 1, description: 'Obligatoire'),
                    new OA\Property(property: 'service_id', type: 'integer', example: 16, description: 'Obligatoire'),
                    new OA\Property(property: 'quantite', type: 'number', example: 1000, description: 'Obligatoire'),
                    new OA\Property(property: 'dateEntree', type: 'string', format: 'date', nullable: true, example: '2026-08-16'),
                    new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'Réception mensuelle'),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Factures : piecesJointes[0]=facture.pdf'
                    ),
                    new OA\Property(
                        property: 'piecesJointesNoms[]',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: 'Noms des factures',
                        example: ['Facture 001', 'Facture 002']
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Entrée créée avec succès.', 'data' => ['id' => 1]]))]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['consumable_id' => 'Le consomptible est obligatoire.']]))]
    public function __invoke(
        Request $request,
        ConsumableEntryService $consumableEntryService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = $request->request->all();
        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        try {
            $entry = $consumableEntryService->create($payload, $documents, $documentLabels);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        }

        return $apiResponse->success(
            ['id' => $entry->getId()],
            Response::HTTP_OK,
            'Entrée créée avec succès.'
        );
    }
}
