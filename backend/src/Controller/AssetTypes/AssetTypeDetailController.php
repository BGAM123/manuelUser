<?php

namespace App\Controller\AssetTypes;

use App\Entity\AssetType;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/asset-types')]
#[OA\Tag(name: 'AssetTypes')]
final class AssetTypeDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_type_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/asset-types/{id}',
        summary: 'Détail d\'un type de bien',
        description: 'Retourne le détail d\'un type de bien actif, avec sa catégorie et les états de bien associés.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Type de bien retourné',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Type de bien retourné avec succès.',
                'data' => [
                    'id' => 4,
                    'nom' => 'Véhicule léger',
                    'description' => '4x4, berline, utilitaire léger',
                    'dureeVie' => 10,
                    'taux' => '20.00',
                    'is_delete' => false,
                    'category_id' => 1,
                    'etatBiens' => [
                        ['id' => 1, 'nom' => 'Bon état'],
                        ['id' => 2, 'nom' => 'En panne'],
                    ],
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
        AssetType $assetType,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($assetType->isDelete()) {
            return $apiResponse->error('Type de bien non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($assetType, 'json', [
            'groups' => ['asset_type:detail', 'etat_bien:list', 'asset_sub_type:list'],
            'circular_reference_handler' => static fn (object $object): ?int => method_exists($object, 'getId') ? $object->getId() : null,
        ]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Type de bien retourné avec succès.');
    }
}
