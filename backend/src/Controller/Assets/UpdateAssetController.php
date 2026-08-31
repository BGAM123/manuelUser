<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\AssetManagementService;
use App\Service\AssetResponseBuilder;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/assets')]
#[OA\Tag(name: 'Assets')]
final class UpdateAssetController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/assets/{id}',
        summary: 'Mettre à jour un bien patrimonial',
        description: 'Modification partielle (multipart/form-data ou JSON). Tous les champs sont facultatifs. Uploads multiples optionnels : photos[], piecesJointes[], piecesJointesNoms[]. '
            . "L'exercice peut être modifié via cet endpoint."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', nullable: true),
                        new OA\Property(property: 'valeur', type: 'number', nullable: true),
                        new OA\Property(property: 'quantiteStock', type: 'integer', nullable: true, example: 100, description: 'Stock disponible (bien de type consomptible uniquement). Décrémenté automatiquement par les sorties BSP.'),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'latitude', type: 'number', nullable: true),
                        new OA\Property(property: 'longitude', type: 'number', nullable: true),
                        // new OA\Property(property: 'exercice', type: 'integer', nullable: true, example: 2026, description: 'Année de l\'exercice patrimonial. Modifiable.'),
                        new OA\Property(property: 'etat_bien_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 16, description: 'ID du service (optionnel, ignoré si user_id est fourni)'),
                        new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 8, description: 'ID de l\'utilisateur (optionnel, prioritaire sur service_id)'),
                        new OA\Property(property: 'category_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'asset_type_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'project_ids[]', type: 'array', items: new OA\Items(type: 'integer')),
                        new OA\Property(property: 'photos[]', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), description: 'Photos à ajouter (multiple)'),
                        new OA\Property(property: 'piecesJointes[]', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), description: 'Documents à ajouter : piecesJointes[0]=facture.pdf, piecesJointes[1]=garantie.pdf'),
                        new OA\Property(property: 'piecesJointesNoms[]', type: 'array', items: new OA\Items(type: 'string'), description: "Noms alignés : piecesJointesNoms[0]=Facture d'achat. Si omis → nom original. CSV Swagger auto-découpé.", example: ["Facture d'achat", 'Bon de livraison']),
                        new OA\Property(property: 'reference', type: 'string', nullable: true, description: 'Si vide/null/"null"/"undefined" → inchangé (update) ou auto (create)'),
                        new OA\Property(property: 'seuil', type: 'number', nullable: true, example: 500000, description: 'Seuil de coût pour les maintenances de ce bien'),
                    ]
                )
            ),
            new OA\JsonContent(
                example: ['nom' => 'Ordinateur HP ProBook', 'etat_bien_id' => 2, 'valeur' => 900000, 'latitude' => 3.8480, 'longitude' => 11.5021, 'description' => '']
            ),
        ]
    )]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Bien mis à jour avec succès.', 'data' => ['id' => 1]]))]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['valeur' => 'Valeur invalide']]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le bien demandé est introuvable.', 'data' => null]))]
    #[OA\Response(response: 409, description: 'Conflit', content: new OA\JsonContent(example: ['success' => false, 'status' => 409, 'message' => 'Ce bien est supprimé.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Asset $asset,
        Request $request,
        AssetManagementService $assetManagementService,
        AssetResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        #[CurrentUser] ?User $currentUser
    ): JsonResponse {
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est supprimé.', Response::HTTP_CONFLICT);
        }

        $contentType = (string) $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $payload = json_decode($request->getContent(), true);
            if (!is_array($payload)) {
                return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
            }
            $photos = [];
            $documents = [];
            $documentLabels = [];
        } else {
            $payload = $request->request->all();
            foreach (['project_ids', 'category_ids', 'asset_type_ids', 'etat_bien_ids', 'service_ids'] as $key) {
                if (isset($payload[$key]) && !is_array($payload[$key])) {
                    $payload[$key] = [$payload[$key]];
                }
                $bracket = $key . '[]';
                if (isset($payload[$bracket])) {
                    $payload[$key] = is_array($payload[$bracket]) ? $payload[$bracket] : [$payload[$bracket]];
                    unset($payload[$bracket]);
                }
            }
            $photos = UploadedFilesNormalizer::fromRequest($request, 'photos');
            $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
            if ([] === $documents) {
                $documents = UploadedFilesNormalizer::fromRequest($request, 'documents');
            }
            $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');
            if ([] === $documentLabels) {
                $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'documents_labels');
            }
        }

        try {
            $asset = $assetManagementService->update($asset, $payload, $photos, $documents, $documentLabels, [], [], $currentUser);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            $decoded = json_decode($e->getMessage(), true);
            if (is_array($decoded)) {
                return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $decoded);
            }
            $status = str_contains($e->getMessage(), 'déjà utilisée') ? Response::HTTP_CONFLICT : Response::HTTP_BAD_REQUEST;

            return $apiResponse->error($e->getMessage(), $status);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($asset),
            Response::HTTP_OK,
            'Bien mis à jour avec succès.'
        );
    }
}
