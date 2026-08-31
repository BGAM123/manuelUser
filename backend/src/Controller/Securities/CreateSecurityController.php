<?php

namespace App\Controller\Securities;

use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\SecurityResponseBuilder;
use App\Service\SecurityService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/securities')]
#[OA\Tag(name: 'Securities')]
final class CreateSecurityController extends AbstractController
{
    #[Route('', name: 'app_security_create', methods: ['POST'])]
    #[OA\Post(
        path: '/securities',
        summary: 'Créer une sécurisation',
        description: "Création multipart/form-data. Permet d'associer un mode de sécurisation à plusieurs biens avec des coordonnées optionnelles et des pièces jointes."
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'asset_ids[]', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                    new OA\Property(property: 'security_mode', type: 'string', example: 'Physique'),
                    new OA\Property(property: 'date_securisation', type: 'string', format: 'date', nullable: true, example: '2026-08-11'),
                    new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true, example: 48.8566),
                    new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true, example: 2.3522),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Upload multiple de documents'
                    ),
                    new OA\Property(
                        property: 'piecesJointesNoms[]',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: 'Noms personnalisés des documents (même index que piecesJointes)'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Sécurisation créée avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Sécurisation créée avec succès.',
                'data' => [
                    'id' => 1,
                    'securityMode' => 'Physique',
                    'dateSecurisation' => '2026-08-11',
                    'location' => null,
                    'assets' => [
                        ['id' => 1, 'reference' => 'REF001', 'nom' => 'Ordinateur portable', 'code' => 'PC001']
                    ],
                    'documents' => [],
                    'createdAt' => '2026-08-11 10:00:00',
                    'updatedAt' => '2026-08-11 10:00:00',
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Validation échouée', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Mode de sécurisation ou bien introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le mode de sécurisation sélectionné n\'existe pas.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Request $request,
        SecurityService $securityService,
        SecurityResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        UploadedFilesNormalizer $filesNormalizer
    ): JsonResponse {
        $payload = $request->request->all();
        
        $this->normalizeArrayFields($payload);
        
        $assetIds = $payload['asset_ids'] ?? [];
        $securityMode = $payload['security_mode'] ?? null;
        $dateSecurisation = $payload['date_securisation'] ?? null;
        
        // ✅ CORRECTION : Gestion des valeurs vides pour latitude et longitude
        $latitude = null;
        if (isset($payload['latitude']) && $payload['latitude'] !== '' && $payload['latitude'] !== 'null') {
            $latitude = (float) $payload['latitude'];
        }
        
        $longitude = null;
        if (isset($payload['longitude']) && $payload['longitude'] !== '' && $payload['longitude'] !== 'null') {
            $longitude = (float) $payload['longitude'];
        }

        // Conversion des types
        if ($dateSecurisation && !empty($dateSecurisation) && $dateSecurisation !== 'null') {
            $dateSecurisation = \DateTimeImmutable::createFromFormat('Y-m-d', $dateSecurisation);
            if ($dateSecurisation === false) {
                return $apiResponse->error('Format de date invalide (attendu: Y-m-d).', Response::HTTP_BAD_REQUEST);
            }
        } else {
            $dateSecurisation = null;
        }

        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        // Validation
        if (empty($assetIds)) {
            return $apiResponse->error('Au moins un ID de bien est requis.', Response::HTTP_BAD_REQUEST);
        }

        if (empty(trim($securityMode))) {
            return $apiResponse->error('Le mode de sécurisation est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $security = $securityService->create(
                $assetIds,
                $securityMode,
                $dateSecurisation,
                $latitude,
                $longitude,
                $documents,
                $documentLabels
            );
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        } catch (\Exception $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($security),
            Response::HTTP_CREATED,
            'Sécurisation créée avec succès.'
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function normalizeArrayFields(array &$payload): void
    {
        foreach (['asset_ids', 'project_ids', 'category_ids', 'asset_type_ids', 'etat_bien_ids', 'service_ids'] as $key) {
            if (isset($payload[$key]) && !is_array($payload[$key])) {
                if (is_string($payload[$key]) && str_contains($payload[$key], ',')) {
                    $payload[$key] = array_map('trim', explode(',', $payload[$key]));
                } else {
                    $payload[$key] = [$payload[$key]];
                }
            }
            $bracket = $key . '[]';
            if (isset($payload[$bracket])) {
                $payload[$key] = is_array($payload[$bracket]) ? $payload[$bracket] : [$payload[$bracket]];
                unset($payload[$bracket]);
            }
        }
    }
}