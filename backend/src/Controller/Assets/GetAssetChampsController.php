<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Champs')]
final class GetAssetChampsController extends AbstractController
{
    #[Route('/{id}/champs', name: 'app_asset_get_champs', methods: ['GET'])]
    #[OA\Get(
        path: '/assets/{id}/champs',
        summary: 'Récupérer les champs d\'un bien avec leurs valeurs',
        description: 'Retourne la liste des champs associés à un bien avec leurs valeurs (inputs).'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        description: 'ID du bien',
        example: 1
    )]
    #[OA\Response(
        response: 200,
        description: 'Champs récupérés avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Champs récupérés avec succès.',
                'data' => [
                    [
                        'id' => 1,
                        'nom' => 'Type de toit',
                        'inputs' => [
                            [
                                'id' => 1,
                                'valeur' => 'Plat',
                                // 'createdAt' => '2026-08-14 10:00:00',
                                // 'updatedAt' => '2026-08-14 10:00:00',
                            ],
                            [
                                'id' => 3,
                                'valeur' => 'Végétalisé',
                                // 'createdAt' => '2026-08-14 11:00:00',
                                // 'updatedAt' => '2026-08-14 11:00:00',
                            ],
                        ]
                    ],
                    [
                        'id' => 2,
                        'nom' => 'Surface',
                        'inputs' => [
                            [
                                'id' => 2,
                                'valeur' => '120 m²',
                                // 'createdAt' => '2026-08-14 10:00:00',
                                // 'updatedAt' => '2026-08-14 10:00:00',
                            ],
                        ]
                    ],
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Bien introuvable',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 404,
                'message' => 'Le bien demandé est introuvable.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 401,
                'message' => 'Authentification requise.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Erreur serveur',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 500,
                'message' => 'Une erreur interne du serveur est survenue.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        Asset $asset,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est supprimé.', Response::HTTP_NOT_FOUND);
        }

        $champs = $asset->getChamps();
        $data = [];

        foreach ($champs as $champ) {
            if (!$champ->isDelete()) {
                // ✅ Récupérer les inputs du champ
                $inputs = [];
                foreach ($champ->getInputs() as $input) {
                    if (!$input->isDelete()) {
                        $inputs[] = [
                            'id' => $input->getId(),
                            'valeur' => $input->getValeur(),
                            // 'createdAt' => $input->getCreatedAt()?->format('Y-m-d H:i:s'),
                            // 'updatedAt' => $input->getUpdatedAt()?->format('Y-m-d H:i:s'),
                        ];
                    }
                }

                $data[] = [
                    'id' => $champ->getId(),
                    'nom' => $champ->getNom(),
                    'inputs' => $inputs,
                ];
            }
        }

        return $apiResponse->success(
            $data,
            Response::HTTP_OK,
            'Champs récupérés avec succès.'
        );
    }
}