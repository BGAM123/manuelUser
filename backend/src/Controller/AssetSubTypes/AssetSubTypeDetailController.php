<?php

namespace App\Controller\AssetSubTypes;

use App\Entity\AssetSubType;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/asset-sub-types')]
#[OA\Tag(name: 'AssetSubTypes')]
final class AssetSubTypeDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_sub_type_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/asset-sub-types/{id}',
        summary: 'Détail d\'un sous-type de bien',
        description: 'Retourne le détail d\'un sous-type de bien actif, avec le type de bien parent auquel il est rattaché.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Sous-type de bien retourné',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Sous-type de bien retourné avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Berline',
                    'description' => 'Véhicule léger à 4 portes',
                    'is_delete' => false,
                    'asset_type_id' => 1,
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    public function __invoke(
        AssetSubType $assetSubType,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($assetSubType->isDelete()) {
            return $apiResponse->error('Sous-type de bien non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($assetSubType, 'json', ['groups' => ['asset_sub_type:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Sous-type de bien retourné avec succès.');
    }
}