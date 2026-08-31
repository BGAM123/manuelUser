<?php

namespace App\Controller\AssetTypes;

use App\Entity\AssetType;
use App\Exception\ResourceInUseException;
use App\Repository\AssetTypeRepository;
use App\Service\ApiResponseFactory;
use App\Service\DefaultAssetReferencesService;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-types')]
#[OA\Tag(name: 'AssetTypes')]
final class SoftDeleteAssetTypeController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_asset_type_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-types/{id}/soft-delete',
        summary: 'Suppression d\'un type de bien',
        description: 'Par défaut, passe is_delete à true. Avec force=true, supprime définitivement la ligne en base.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'force',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'true = suppression définitive (hard delete) au lieu de la suppression logique par défaut.'
    )]
    #[OA\Response(response: 200, description: 'Success - Type de bien supprimé avec succès')]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found - Type de bien non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - Déjà supprimé, ou type de bien par défaut')]
    public function __invoke(
        AssetType $assetType,
        Request $request,
        AssetTypeRepository $assetTypeRepository,
        DefaultAssetReferencesService $defaultAssetReferences,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        if (!$force && $assetType->isDelete()) {
            return $apiResponse->error('Ce type de bien est déjà supprimé.', Response::HTTP_CONFLICT);
        }

        if ($defaultAssetReferences->isDefaultAssetType($assetType)) {
            return $apiResponse->error('Le type de bien par défaut ne peut pas être supprimé.', Response::HTTP_CONFLICT);
        }

        if ($force) {
            try {
                $forceDeleteService->delete($assetType);
            } catch (ResourceInUseException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            return $apiResponse->success(null, Response::HTTP_OK, 'Type de bien supprimé définitivement avec succès.');
        }

        // Comme pour les catégories (reassignAssetTypesToDefaultCategory) : les sous-types et
        // les biens rattachés directement à ce type de bien basculent sur le type de bien par
        // défaut plutôt que de bloquer la suppression logique.
        $defaultAssetReferences->reassignAssetSubTypesToDefaultAssetType($assetType);
        $defaultAssetReferences->reassignAssetsToDefaultAssetType($assetType);
        $assetTypeRepository->softDelete($assetType);

        return $apiResponse->success(null, Response::HTTP_OK, 'Type de bien supprimé (logiquement) avec succès.');
    }
}