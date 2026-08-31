<?php

namespace App\Controller\AssetMaintenances;

use App\Entity\AssetMaintenance;
use App\Service\ApiResponseFactory;
use App\Service\AssetMaintenanceResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-maintenances')]
#[OA\Tag(name: 'Asset Maintenances')]
final class GetAssetMaintenanceController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_maintenance_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/asset-maintenances/{id}',
        summary: 'Détail d\'une maintenance de bien',
        description: 'Retourne les détails d\'une maintenance.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 12)]
    #[OA\Response(
        response: 200,
        description: 'Détail de la maintenance',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Maintenance trouvée.',
                'data' => [
                    'id' => 12,
                    'etatBien' => ['id' => 2, 'nom' => 'Bon'],
                    'motif' => 'Entretien préventif',
                    'cout' => 100000,
                    'dateIntervention' => '2024-01-05',
                    'dateRecuperation' => '2025-06-05',
                    'dateRecuperationPrevue' => '2025-06-01',
                    'dateRecuperationReelle' => '2025-06-05',
                    'observations' => 'Maintenance annuelle.',
                    'statut' => 'TERMINEE',
                    'piecesJointes' => [
                        ['id' => 5, 'nom' => 'Facture', 'chemin' => '/uploads/maintenances/facture.pdf']
                    ],
                    'createdAt' => '2026-08-01 10:30:00'
                ]
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Maintenance introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Maintenance introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetMaintenance $maintenance,
        AssetMaintenanceResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        return $apiResponse->success(
            $responseBuilder->buildDetail($maintenance),
            Response::HTTP_OK,
            'Maintenance trouvée.'
        );
    }
}
