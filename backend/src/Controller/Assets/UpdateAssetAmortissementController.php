<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\AssetResponseBuilder;
use App\Service\AssetManagementService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Amortissements')]
final class UpdateAssetAmortissementController extends AbstractController
{
    #[Route('/{id}/amortissement', name: 'app_asset_update_amortissement', methods: ['POST'])]
    #[OA\Post(
        path: '/assets/{id}/amortissement',
        summary: 'Activer ou désactiver l\'amortissement d\'un bien',
        description: "Permet d'activer ou de désactiver l'amortissement pour un bien spécifique.\n\n"
            . "**Statut actuel :** retourné dans la réponse.\n\n"
            . "**Exemple :** si `active` est true, l'amortissement est activé ; si false, il est désactivé."
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        example: 1,
        description: 'ID du bien'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                type: 'object',
                required: ['active'],
                properties: [
                    new OA\Property(
                        property: 'active',
                        type: 'boolean',
                        example: true,
                        description: 'true pour activer l\'amortissement, false pour le désactiver'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Amortissement mis à jour avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Amortissement mis à jour avec succès.',
                'data' => [
                    'id' => 1,
                    'reference' => 'PAT-2026-00001',
                    'nom' => 'Ordinateur Portable HP ProBook 450 G10',
                    'activeAmortissement' => true,
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation échouée',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'Le champ "active" est requis et doit être un booléen.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Bien introuvable',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 404,
                'message' => 'Le bien demandé est introuvable.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 409,
        description: 'Bien supprimé',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 409,
                'message' => 'Ce bien est supprimé.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Erreur serveur',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 500,
                'message' => 'Une erreur interne du serveur est survenue.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        Asset $asset,
        Request $request,
        AssetManagementService $assetManagementService,
        AssetResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // Vérifier si le bien est supprimé
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est supprimé.', Response::HTTP_CONFLICT);
        }

        // Récupérer et valider le paramètre 'active'
        $payload = json_decode($request->getContent(), true);
        if (!isset($payload['active'])) {
            return $apiResponse->error(
                'Le champ "active" est requis.',
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!is_bool($payload['active'])) {
            return $apiResponse->error(
                'Le champ "active" doit être un booléen (true/false).',
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            // Mettre à jour l'amortissement
            $asset = $assetManagementService->updateAmortissement($asset, $payload['active']);
        } catch (\InvalidArgumentException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        // Retourner la réponse avec les informations mises à jour
        return $apiResponse->success(
            [
                'id' => $asset->getId(),
                'reference' => $asset->getReference(),
                'nom' => $asset->getNom(),
                'activeAmortissement' => $asset->isActiveAmortissement(),
            ],
            Response::HTTP_OK,
            'Amortissement mis à jour avec succès.'
        );
    }
}