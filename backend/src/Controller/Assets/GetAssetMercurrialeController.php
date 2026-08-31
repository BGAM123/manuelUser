<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// #[Route('/assets/{id}/mercurriale', name: 'app_asset_mercurriale', methods: ['GET'])]
// #[OA\Tag(name: 'Assets')]
final class GetAssetMercurrialeController extends AbstractController
{
    private const MERCURIALE_BASE_URL = 'https://www.mercuriale.cm/#/articles-found?key=';

    #[OA\Get(
        path: '/assets/{id}/mercurriale',
        summary: 'Récupérer les informations pour la recherche Mercuriale',
        description: 'Retourne l\'ID, le nom et le lien de recherche Mercuriale pour un bien donné.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Identifiant du bien',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Informations Mercuriale récupérées',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Informations Mercuriale récupérées avec succès.',
                'data' => [
                    'id' => 25,
                    'nom' => 'NANKY TOMATO P',
                    'lien' => 'https://www.mercuriale.cm/#/articles-found?key=NANKY%20TOMATO%20P'
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found - Bien introuvable',
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
        response: 400,
        description: 'Bad Request - Nom du bien manquant',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'Le nom du bien est requis pour générer le lien Mercuriale.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        Asset $asset,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $nom = $asset->getNom();

        if (empty($nom)) {
            return $apiResponse->error(
                'Le nom du bien est requis pour générer le lien Mercuriale.',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Construire le lien automatiquement à partir du nom
        $lien = self::MERCURIALE_BASE_URL . rawurlencode($nom);

        $data = [
            'id' => $asset->getId(),
            'nom' => $nom,
            'lien' => $lien
        ];

        return $apiResponse->success($data, Response::HTTP_OK, 'Informations Mercuriale récupérées avec succès.');
    }
}
