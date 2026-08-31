<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Exception\ResourceNotFoundException;
use App\Repository\ChampRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Champs')]
final class AddChampsToAssetController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }
    
    #[Route('/{id}/champs', name: 'app_asset_add_champs', methods: ['POST'])]
    #[OA\Post(
        path: '/assets/{id}/champs',
        summary: 'Ajouter des champs à un bien',
        description: 'Associe des champs à un bien.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        description: 'ID du bien',
        example: 1
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'champ_ids',
                    type: 'array',
                    items: new OA\Items(type: 'integer'),
                    example: [1, 2, 3],
                    description: 'Liste des IDs de champs à associer'
                ),
            ],
            required: ['champ_ids']
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Champs ajoutés avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Champs ajoutés avec succès.',
                'data' => [
                    'id' => 1,
                    'reference' => 'PAT-2026-00001',
                    'nom' => 'Ordinateur Portable HP ProBook 450 G10',
                    'champs' => [
                        ['id' => 1, 'nom' => 'Type de toit'],
                        ['id' => 2, 'nom' => 'Surface'],
                    ],
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Requête invalide',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'Au moins un ID de champ est requis.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Bien ou champ introuvable',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 404,
                'message' => 'Un ou plusieurs champs sont introuvables.',
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
        response: 409,
        description: 'Conflit - Bien supprimé',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 409,
                'message' => 'Ce bien est supprimé.',
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
        Request $request,
        ChampRepository $champRepository,
        AssetResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // ✅ Vérifier que le bien n'est pas supprimé
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est supprimé.', Response::HTTP_CONFLICT);
        }

        // ✅ Récupérer les IDs des champs
        $payload = json_decode($request->getContent(), true);
        
        if (!is_array($payload)) {
            return $apiResponse->error('Données invalides. Le format JSON est requis.', Response::HTTP_BAD_REQUEST);
        }

        $champIds = $payload['champ_ids'] ?? [];

        // ✅ Vérifier qu'au moins un champ est envoyé
        if (empty($champIds)) {
            return $apiResponse->error('Au moins un ID de champ est requis.', Response::HTTP_BAD_REQUEST);
        }

        // ✅ Récupérer les champs actifs
        $champs = $champRepository->findActiveByIds($champIds);
        
        if (count($champs) !== count($champIds)) {
            return $apiResponse->error('Un ou plusieurs champs sont introuvables.', Response::HTTP_NOT_FOUND);
        }

        // ✅ Ajouter les champs au bien
        foreach ($champs as $champ) {
            if (!$asset->getChamps()->contains($champ)) {
                $asset->addChamp($champ);
            }
        }

        // ✅ Utiliser l'EntityManager injecté au lieu de getDoctrine()
        $this->entityManager->flush();

        return $apiResponse->success(
            $responseBuilder->buildDetail($asset),
            Response::HTTP_OK,
            'Champs ajoutés avec succès.'
        );
    }
}