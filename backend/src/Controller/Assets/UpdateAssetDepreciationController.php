<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Service\ApiResponseFactory;
use App\Service\AssetManagementService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Asset Depreciations')]
final class UpdateAssetDepreciationController extends AbstractController
{
    #[Route('/{id}/depreciation', name: 'app_asset_update_depreciation', methods: ['POST'])]
    #[OA\Post(
        path: '/assets/{id}/depreciation',
        summary: 'Activer ou désactiver la dépréciation d\'un bien',
        description: "Permet d'activer ou de désactiver la dépréciation pour un bien spécifique.\n\n"
            . "**Statut actuel :** retourné dans la réponse.\n\n"
            . "**Exemple :** si `active` est true, la dépréciation est activée ; si false, elle est désactivée."
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
                        description: 'true pour activer la dépréciation, false pour la désactiver'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Dépréciation mise à jour avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Dépréciation mise à jour avec succès.',
                'data' => [
                    'id' => 1,
                    'reference' => 'PAT-2026-00001',
                    'nom' => 'Ordinateur Portable HP ProBook 450 G10',
                    'activeDepreciation' => true,
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
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est supprimé.', Response::HTTP_CONFLICT);
        }

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
            $asset->setActiveDepreciation($payload['active']);
            $asset->setUpdatedAt(new \DateTimeImmutable());
            $assetManagementService->updateDepreciation($asset, $payload['active']);
        } catch (\InvalidArgumentException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            [
                'id' => $asset->getId(),
                'reference' => $asset->getReference(),
                'nom' => $asset->getNom(),
                'activeDepreciation' => $asset->isActiveDepreciation(),
            ],
            Response::HTTP_OK,
            'Dépréciation mise à jour avec succès.'
        );
    }
}
