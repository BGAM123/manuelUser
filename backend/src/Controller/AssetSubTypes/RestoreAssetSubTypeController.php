<?php

namespace App\Controller\AssetSubTypes;

use App\Entity\AssetSubType;
use App\Repository\AssetSubTypeRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/asset-sub-types')]
#[OA\Tag(name: 'AssetSubTypes')]
final class RestoreAssetSubTypeController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_asset_sub_type_restore', methods: ['POST'])]
    #[OA\Post(
        path: '/asset-sub-types/{id}/restore',
        summary: 'Restaurer un sous-type de bien supprimé logiquement',
        description: 'Réservé à l\'administrateur. Refusée si le type de bien parent est lui-même supprimé.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success - Sous-type de bien restauré avec succès')]
    #[OA\Response(response: 404, description: 'Not Found - Sous-type de bien non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - N\'est pas supprimé, ou son type de bien parent est supprimé')]
    public function __invoke(
        AssetSubType $assetSubType,
        AssetSubTypeRepository $assetSubTypeRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (!$assetSubType->isDelete()) {
            return $apiResponse->error('Ce sous-type de bien n\'est pas supprimé.', Response::HTTP_CONFLICT);
        }

        if ($assetSubType->getAssetType()?->isDelete()) {
            return $apiResponse->error(
                'Impossible de restaurer : le type de bien parent est lui-même supprimé. Restaurez-le d\'abord.',
                Response::HTTP_CONFLICT
            );
        }

        $assetSubTypeRepository->restore($assetSubType);

        $data = json_decode($serializer->serialize($assetSubType, 'json', ['groups' => ['asset_sub_type:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Sous-type de bien restauré avec succès.');
    }
}