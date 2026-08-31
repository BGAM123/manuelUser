<?php

namespace App\Controller\AssetSubTypes;

use App\Entity\AssetSubType;
use App\Exception\ResourceInUseException;
use App\Repository\AssetSubTypeRepository;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-sub-types')]
#[OA\Tag(name: 'AssetSubTypes')]
final class SoftDeleteAssetSubTypeController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_asset_sub_type_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-sub-types/{id}/soft-delete',
        summary: 'Suppression d\'un sous-type de bien',
        description: 'Par défaut, passe is_delete à true. Avec force=true, supprime définitivement la ligne en base.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'force',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'true = suppression définitive (hard delete) au lieu de la suppression logique par défaut.'
    )]
    #[OA\Response(response: 200, description: 'Success - Sous-type de bien supprimé')]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found - Sous-type de bien non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - Déjà supprimé')]
    public function __invoke(
        AssetSubType $assetSubType,
        Request $request,
        AssetSubTypeRepository $assetSubTypeRepository,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        if (!$force && $assetSubType->isDelete()) {
            return $apiResponse->error('Ce sous-type de bien est déjà supprimé.', Response::HTTP_CONFLICT);
        }

        if ($force) {
            try {
                $forceDeleteService->delete($assetSubType);
            } catch (ResourceInUseException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            return $apiResponse->success(null, Response::HTTP_OK, 'Sous-type de bien supprimé définitivement avec succès.');
        }

        $assetSubTypeRepository->softDelete($assetSubType);

        return $apiResponse->success(null, Response::HTTP_OK, 'Sous-type de bien supprimé (logiquement) avec succès.');
    }
}