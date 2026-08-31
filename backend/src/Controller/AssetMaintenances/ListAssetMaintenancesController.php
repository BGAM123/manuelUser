<?php

namespace App\Controller\AssetMaintenances;

use App\Repository\AssetMaintenanceRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetMaintenanceResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-maintenances')]
#[OA\Tag(name: 'Asset Maintenances')]
final class ListAssetMaintenancesController extends AbstractController
{
    #[Route('', name: 'app_asset_maintenance_list', methods: ['GET'])]
    #[OA\Get(
        path: '/asset-maintenances',
        summary: 'Lister toutes les maintenances enregistrées',
        description: 'Retourne la liste paginée de toutes les maintenances (en cours et terminées), avec leurs informations.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Parameter(name: 'asset_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filtrer par bien')]
    #[OA\Parameter(
        name: 'statut',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['EN COURS', 'TERMINEE']),
        description: 'Filtrer par statut de la maintenance'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - maintenances retournées',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Maintenances retournées avec succès.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 20, 'total_items' => 1, 'total_pages' => 1],
                    'data' => [
                        [
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
                            'piecesJointes' => [],
                            'createdAt' => '2026-08-01 10:30:00',
                        ],
                    ],
                ],
            ]
        )
    )]
    public function __invoke(
        Request $request,
        AssetMaintenanceRepository $maintenanceRepository,
        AssetMaintenanceResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 20);
        $limit = $limit < 1 ? 20 : ($limit > 200 ? 200 : $limit);

        $assetId = $request->query->has('asset_id') ? $request->query->getInt('asset_id') : null;
        $statut = $request->query->get('statut');

        // Filtrage par utilisateur connecté
        $user = $this->getUser();
        $userId = null;
        if ($user && method_exists($user, 'getId')) {
            // Vérifier si l'utilisateur a un rôle administrateur
            $isAdmin = false;
            $userRoles = $user->getAssignedRoles();
            $adminRoleNames = ['Administrateur', 'Administrateur patrimonial', 'Administrateur système'];

            foreach ($userRoles as $role) {
                if (in_array($role->getNom(), $adminRoleNames, true)) {
                    $isAdmin = true;
                    break;
                }
            }

            // Si pas administrateur, filtrer par l'utilisateur connecté (détenteur actuel)
            if (!$isAdmin) {
                $userId = $user->getId();
            }
        }

        $maintenances = $maintenanceRepository->findPaginated($page, $limit, $assetId, $statut, $userId);
        $total = $maintenanceRepository->countAll($assetId, $statut, $userId);

        $meta = [
            'current_page' => $page,
            'limit' => $limit,
            'total_items' => $total,
            'total_pages' => (int) ceil($total / max(1, $limit)),
        ];

        return $apiResponse->success([
            'meta' => $meta,
            'data' => $responseBuilder->buildList($maintenances),
        ], Response::HTTP_OK, 'Maintenances retournées avec succès.');
    }
}
