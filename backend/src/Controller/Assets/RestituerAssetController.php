<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\AssetAssignmentService;
use App\Service\AssetResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/assets')]
#[OA\Tag(name: 'Assets')]
final class RestituerAssetController extends AbstractController
{
    #[Route('/{id}/restituer', name: 'app_asset_restituer', methods: ['POST'])]
    #[OA\Post(
        path: '/assets/{id}/restituer',
        summary: 'Restituer un bien patrimonial',
        description: 'Restitue un bien en créant une affectation de type RESTITUTION avec logique automatique. Si user_id non fourni, utilise userRestitution du bien. Si affectation en cours, renseigne dateFin automatiquement.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 8, description: 'Optionnel. ID de l\'utilisateur de restitution. Si non fourni, utilise userRestitution du bien.'),
                    new OA\Property(property: 'dateDebut', type: 'string', format: 'date', nullable: true, example: '2026-08-29', description: 'Optionnel. Date de début de la restitution. Si non fournie, utilise la date du jour.'),
                    new OA\Property(property: 'dateFin', type: 'string', format: 'date', nullable: true, example: '2026-08-29', description: 'Optionnel. Date de fin de l\'affectation précédente. Si non fournie, utilise la date du jour.'),
                    new OA\Property(property: 'commentaire', type: 'string', nullable: true, example: 'Restitution suite à fin de projet.'),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Bien restitué avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Bien restitué avec succès.',
                'data' => [
                    'id' => 1,
                    'reference' => 'PAT-2026-00001',
                    'nom' => 'Ordinateur Portable HP ProBook 450 G10',
                ],
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le bien demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        Asset $asset,
        Request $request,
        AssetAssignmentService $assignmentService,
        AssetResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        #[CurrentUser] ?User $currentUser
    ): JsonResponse {
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est supprimé.', Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $assignment = $assignmentService->restituerAsset($asset, $payload, $currentUser);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($asset),
            Response::HTTP_CREATED,
            'Bien restitué avec succès.'
        );
    }
}
