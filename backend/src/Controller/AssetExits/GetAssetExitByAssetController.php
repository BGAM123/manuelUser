<?php

namespace App\Controller\AssetExits;

use App\Entity\Asset;
use App\Repository\AssetExitRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetExitResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Asset Exits')]
final class GetAssetExitByAssetController extends AbstractController
{
    #[Route('/{id}/exit', name: 'app_asset_exit_by_asset', methods: ['GET'])]
    #[OA\Get(
        path: '/assets/{id}/exit',
        summary: 'Voir la sortie d\'un bien',
        description: 'Retourne la sortie associée au bien si elle existe.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 5)]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Sortie du bien récupérée avec succès.',
                'data' => [
                    'asset' => [
                        'id' => 5,
                        'reference' => 'PAT-2026-00001',
                        'nom' => 'Ordinateur HP'
                    ],
                    'sortie' => [
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
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Bien introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Bien introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Asset $asset,
        AssetExitRepository $exitRepository,
        AssetExitResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est supprimé.', Response::HTTP_NOT_FOUND);
        }

        $exit = $exitRepository->findByAssetId($asset->getId());

        $data = [
            'asset' => [
                'id' => $asset->getId(),
                'reference' => $asset->getReference(),
                'nom' => $asset->getNom(),
            ],
            'sortie' => $exit ? $responseBuilder->buildDetail($exit) : null,
        ];

        return $apiResponse->success(
            $data,
            Response::HTTP_OK,
            'Sortie du bien récupérée avec succès.'
        );
    }
}
