<?php

namespace App\Controller\AssetTypes;

use App\Entity\AssetType;
use App\Repository\AssetTypeRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/asset-types')]
#[OA\Tag(name: 'AssetTypes')]
final class RestoreAssetTypeController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_asset_type_restore', methods: ['POST'])]
    #[OA\Post(
        path: '/asset-types/{id}/restore',
        summary: 'Restaurer un type de bien supprimé',
        description: 'Réservé à l\'administrateur. Refusée si la catégorie parente est elle-même supprimée.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success - Type de bien restauré avec succès')]
    #[OA\Response(response: 404, description: 'Not Found - Type de bien non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - N\'est pas supprimé, ou sa catégorie est supprimée')]
    public function __invoke(
        AssetType $assetType,
        AssetTypeRepository $assetTypeRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (!$assetType->isDelete()) {
            return $apiResponse->error('Ce type de bien n\'est pas supprimé.', Response::HTTP_CONFLICT);
        }

        if ($assetType->getCategory()?->isDelete()) {
            return $apiResponse->error(
                'Impossible de restaurer : la catégorie est elle-même supprimée.',
                Response::HTTP_CONFLICT
            );
        }

        $assetTypeRepository->restore($assetType);

        $data = json_decode($serializer->serialize($assetType, 'json', ['groups' => ['asset_type:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Type de bien restauré avec succès.');
    }
}