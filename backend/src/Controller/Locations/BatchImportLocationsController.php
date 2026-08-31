<?php

namespace App\Controller\Locations;

use App\Service\ApiResponseFactory;
use App\Service\LocationRegistrationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// #[Route('/locations')]
// #[OA\Tag(name: 'LocationsImport')]
final class BatchImportLocationsController extends AbstractController
{
    // #[Route('/batch-import', name: 'app_locations_batch_import', methods: ['POST'])]
    // #[OA\Post(path: '/locations/batch-import', summary: 'Importer en lot region/departement/arrondissement')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['items'],
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        required: ['region', 'departement', 'arrondissement'],
                        properties: [
                            new OA\Property(property: 'region', type: 'string', example: 'Centre'),
                            new OA\Property(property: 'departement', type: 'string', example: 'Mfoundi'),
                            new OA\Property(property: 'arrondissement', type: 'string', example: 'Yaounde 1er'),
                        ]
                    )
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Import terminé',
        content: new OA\JsonContent(example: [
            'success' => true,
            'status' => 200,
            'message' => 'Import des localités terminé avec succès.',
            'data' => [
                'created' => ['regions' => 0, 'departements' => 1, 'arrondissements' => 2],
                'reused' => ['regions' => 2, 'departements' => 3, 'arrondissements' => 1],
                'ignored_rows_in_payload' => 1,
            ],
        ])
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide')]
    #[OA\Response(response: 409, description: 'Conflict - Limite des 10 régions actives dépassée')]
    public function __invoke(
        Request $request,
        LocationRegistrationService $locationRegistrationService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['items']) || !is_array($payload['items'])) {
            return $apiResponse->error('Payload invalide. Le champ items (tableau) est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $summary = $locationRegistrationService->importFlatHierarchy($payload['items']);
        } catch (\InvalidArgumentException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\DomainException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $apiResponse->success($summary, Response::HTTP_OK, 'Import des localités terminé avec succès.');
    }
}
