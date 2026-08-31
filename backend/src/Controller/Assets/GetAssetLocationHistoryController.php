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

#[Route('/assets/{id}/locations-history', name: 'app_asset_location_history', methods: ['GET'])]
#[OA\Tag(name: 'Assets')]
final class GetAssetLocationHistoryController extends AbstractController
{
    #[OA\Get(
        path: '/assets/{id}/locations-history',
        summary: 'Historique des localisations d\'un bien',
        description: 'Retourne l\'historique complet des localisations d\'un bien, trié par date de début décroissante.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Identifiant du bien',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Historique des localisations',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Historique des localisations récupéré avec succès.',
                'data' => [
                    [
                        'id' => 2,
                        'latitude' => 3.8500,
                        'longitude' => 11.5050,
                        'geometry_type' => 'Point',
                        'geometry' => null
                    ],
                    [
                        'id' => 1,
                        'latitude' => 3.8480,
                        'longitude' => 11.5021,
                        'geometry_type' => 'Polygon',
                        'geometry' => [
                            'type' => 'Polygon',
                            'coordinates' => [[[11.5015, 3.8475], [11.5027, 3.8475], [11.5027, 3.8486], [11.5015, 3.8486], [11.5015, 3.8475]]]
                        ]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found - Bien introuvable',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    public function __invoke(
        Asset $asset,
        AssetLocationRepository $assetLocationRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $assetLocations = $assetLocationRepository->findHistoryByAsset($asset->getId());

        $data = array_map(function ($assetLocation) {
            $location = $assetLocation->getLocation();
            return [
                'id' => $location->getId(),
                'latitude' => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
                'geometry_type' => $location->getGeometryType(),
                'geometry' => $location->getGeometry(),
            ];
        }, $assetLocations);

        return $apiResponse->success($data, Response::HTTP_OK, 'Historique des localisations récupéré avec succès.');
    }
}
