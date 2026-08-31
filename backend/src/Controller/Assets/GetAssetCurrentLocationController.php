<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Repository\AssetLocationRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets/{id}/location', name: 'app_asset_current_location', methods: ['GET'])]
#[OA\Tag(name: 'Assets')]
final class GetAssetCurrentLocationController extends AbstractController
{
    #[OA\Get(
        path: '/assets/{id}/location',
        summary: 'Localisation actuelle d\'un bien',
        description: 'Retourne la localisation la plus récente d\'un bien (la dernière ajoutée via POST /assets/{id}/locations).'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Identifiant du bien',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Localisation actuelle',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Localisation actuelle récupérée avec succès.',
                'data' => [
                    'id' => 2,
                    'latitude' => 3.8500,
                    'longitude' => 11.5050,
                    'geometry_type' => 'Point',
                    'geometry' => null
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found - Bien introuvable ou aucune localisation actuelle',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Aucune localisation pour ce bien.', 'data' => null])
    )]
    public function __invoke(
        Asset $asset,
        AssetLocationRepository $assetLocationRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $assetLocation = $assetLocationRepository->findLatestByAsset($asset->getId());

        if (!$assetLocation) {
            return $apiResponse->error('Aucune localisation pour ce bien.', Response::HTTP_NOT_FOUND);
        }

        $location = $assetLocation->getLocation();
        $data = [
            'id' => $location->getId(),
            'latitude' => $location->getLatitude(),
            'longitude' => $location->getLongitude(),
            'geometry_type' => $location->getGeometryType(),
            'geometry' => $location->getGeometry(),
        ];

        return $apiResponse->success($data, Response::HTTP_OK, 'Localisation actuelle récupérée avec succès.');
    }
}
