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
final class ReplaceSecurityController extends AbstractController
{
    #[Route('/{id}', name: 'app_security_replace', methods: ['POST'])]
    #[OA\Post(
        path: '/securities/{id}',
        summary: 'Modifier une sécurisation',
        description: "Modifie une sécurisation existante."
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        description: 'ID de la sécurisation à modifier'
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'asset_ids[]', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                    new OA\Property(property: 'security_mode', type: 'string', nullable: true, example: 'Physique', description: 'Nom du mode de sécurisation'), // ✅ Changé
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
                        description: 'Noms personnalisés des documents'
                    ),
                ]
            )
        )
    )]
    public function __invoke(
        int $id,
        Request $request,
        SecurityService $securityService,
        SecurityResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        UploadedFilesNormalizer $filesNormalizer
    ): JsonResponse {
        $payload = $request->request->all();
        
        error_log('=== REPLACE SECURITY (POST) ===');
        error_log('Payload reçu: ' . print_r($payload, true));
        
        $this->normalizeArrayFields($payload);
        
        // Récupérer les paramètres
        $assetIds = null;
        if (isset($payload['asset_ids']) && !empty($payload['asset_ids'])) {
            $assetIds = $payload['asset_ids'];
            if (is_string($assetIds)) {
                $assetIds = array_map('intval', explode(',', $assetIds));
            }
            $assetIds = array_filter($assetIds, function($id) {
                return $id > 0;
            });
            if (empty($assetIds)) {
                $assetIds = null;
            }
        }
        
        $securityMode = $payload['security_mode'] ?? null; // ✅ Récupération du texte
        if ($securityMode !== null && empty(trim($securityMode))) {
            $securityMode = null;
        }
        
        $dateSecurisation = null;
        if (isset($payload['date_securisation']) && !empty($payload['date_securisation']) && 
            $payload['date_securisation'] !== 'null') {
            $dateSecurisation = \DateTimeImmutable::createFromFormat('Y-m-d', $payload['date_securisation']);
            if ($dateSecurisation === false) {
                return $apiResponse->error('Format de date invalide (attendu: Y-m-d).', Response::HTTP_BAD_REQUEST);
            }
        }
        
        $latitude = null;
        if (isset($payload['latitude']) && $payload['latitude'] !== '' && 
            $payload['latitude'] !== 'null') {
            $latitude = (float) $payload['latitude'];
        }
        
        $longitude = null;
        if (isset($payload['longitude']) && $payload['longitude'] !== '' && 
            $payload['longitude'] !== 'null') {
            $longitude = (float) $payload['longitude'];
        }

        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        error_log('Paramètres traités:');
        error_log('asset_ids: ' . print_r($assetIds, true));
        error_log('security_mode: ' . $securityMode);
        error_log('date_securisation: ' . ($dateSecurisation ? $dateSecurisation->format('Y-m-d') : 'null'));
        error_log('latitude: ' . $latitude);
        error_log('longitude: ' . $longitude);
        error_log('documents count: ' . count($documents));

        try {
            $security = $securityService->update(
                $id,
                $assetIds,
                $securityMode, // ✅ Passage du texte
                $dateSecurisation,
                $latitude,
                $longitude,
                $documents,
                $documentLabels
            );
            
            error_log('✅ Sécurisation modifiée avec succès, ID: ' . $security->getId());
        } catch (ResourceNotFoundException $e) {
            error_log('❌ ResourceNotFoundException: ' . $e->getMessage());
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ValidationFailedException $e) {
            error_log('❌ ValidationFailedException: ' . print_r($e->getErrors(), true));
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        } catch (\InvalidArgumentException $e) {
            error_log('❌ InvalidArgumentException: ' . $e->getMessage());
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            error_log('❌ Exception: ' . $e->getMessage());
            error_log('Trace: ' . $e->getTraceAsString());
            return $apiResponse->error('Une erreur est survenue: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($security),
            Response::HTTP_OK,
            'Sécurisation modifiée avec succès.'
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