<?php

namespace App\Controller\AssetMaintenances;

use App\Entity\AssetMaintenance;
use App\Service\ApiResponseFactory;
use App\Service\AssetMaintenanceResponseBuilder;
use App\Service\AssetMaintenanceService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-maintenances')]
#[OA\Tag(name: 'Asset Maintenances')]
final class TerminateAssetMaintenanceController extends AbstractController
{
    #[Route('/{id}/terminer', name: 'app_asset_maintenance_terminate', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/asset-maintenances/{id}/terminer',
        summary: 'Terminer une maintenance en cours',
        description: 'Pose dateRecuperation (aujourd\'hui par défaut, ou une date fournie) et repasse le bien à ACTIF — sauf s\'il a encore une autre maintenance ouverte.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 12)]
    #[OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'dateRecuperation', type: 'string', format: 'date', nullable: true, example: '2026-08-20'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Maintenance terminée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Maintenance terminée avec succès.',
                'data' => [
                    'id' => 12,
                    'etatBien' => ['id' => 2, 'nom' => 'Bon'],
                    'motif' => 'Entretien préventif',
                    'cout' => 100000,
                    'dateIntervention' => '2024-01-05',
                    'dateRecuperation' => '2026-08-20',
                    'dateRecuperationPrevue' => null,
                    'dateRecuperationReelle' => '2026-08-20',
                    'observations' => 'Maintenance annuelle.',
                    'statut' => 'TERMINEE',
                    'piecesJointes' => [],
                    'createdAt' => '2026-08-01 10:30:00',
                ],
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Maintenance introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Maintenance introuvable.', 'data' => null]))]
    #[OA\Response(response: 409, description: 'Conflict - Déjà terminée', content: new OA\JsonContent(example: ['success' => false, 'status' => 409, 'message' => 'Cette maintenance est déjà terminée.', 'data' => null]))]
    public function __invoke(
        AssetMaintenance $maintenance,
        Request $request,
        AssetMaintenanceService $maintenanceService,
        AssetMaintenanceResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (null !== $maintenance->getDateRecuperation()) {
            return $apiResponse->error('Cette maintenance est déjà terminée.', Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $dateRecuperation = null;
        if (!empty($payload['dateRecuperation'])) {
            $dateRecuperation = new \DateTime((string) $payload['dateRecuperation']);
        }

        $maintenance = $maintenanceService->terminate($maintenance, $dateRecuperation);

        return $apiResponse->success(
            $responseBuilder->buildDetail($maintenance),
            Response::HTTP_OK,
            'Maintenance terminée avec succès.'
        );
    }
}
