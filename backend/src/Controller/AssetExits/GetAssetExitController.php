<?php

namespace App\Controller\AssetExits;

use App\Entity\AssetExit;
use App\Exception\ResourceNotFoundException;
use App\Service\ApiResponseFactory;
use App\Service\AssetExitResponseBuilder;
use App\Service\AssetExitService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-exits')]
#[OA\Tag(name: 'Asset Exits')]
final class GetAssetExitController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_exit_get', methods: ['GET'])]
    #[OA\Get(
        path: '/asset-exits/{id}',
        summary: 'Détail d\'une sortie de bien',
        description: 'Retourne la sortie complète avec le bien, le service et les pièces jointes.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Détail de la sortie récupéré avec succès.',
                'data' => [
                    'id' => 1,
                    'exitType' => [
                        'id' => 1,
                        'nom' => 'Réforme',
                        'code' => 'REFORME'
                    ],
                    'motifSortie' => 'REFORME',
                    'dateSortie' => '2026-08-01',
                    'protocoleReference' => 'REF-2026-001',
                    'observations' => 'Matériel obsolète.',
                    'asset' => [
                        'id' => 5,
                        'reference' => 'PAT-2026-00001',
                        'nom' => 'Ordinateur HP'
                    ],
                    'service' => [
                        'id' => 16,
                        'nom' => 'Comptabilité'
                    ],
                    'piecesJointes' => [
                        [
                            'id' => 21,
                            'nom' => 'PV de réforme',
                            'chemin' => '/uploads/exits/pv-reforme.pdf'
                        ]
                    ],
                    'bspsCount' => 2,
                    'bsps' => [
                        ['id' => 1, 'numero' => 'BSP-2026-00001', 'quantiteServie' => 30, 'beneficiaire' => ['id' => 4, 'nom' => 'NGUEMA', 'prenom' => 'Paul']],
                        ['id' => 2, 'numero' => 'BSP-2026-00002', 'quantiteServie' => 40, 'beneficiaire' => null],
                    ],
                    'createdAt' => '2026-08-01 10:00:00'
                ]
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Sortie introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Sortie introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetExit $exit,
        AssetExitResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($exit->isDelete()) {
            return $apiResponse->error('Cette sortie est supprimée.', Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($exit),
            Response::HTTP_OK,
            'Détail de la sortie récupéré avec succès.'
        );
    }
}
