<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Repository\ChampRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetResponseBuilder;
use Doctrine\ORM\EntityManagerInterface; // ✅ Ajouter cet import
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Champs')]
final class RemoveChampFromAssetController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager // ✅ Ajouter le constructeur
    ) {
    }

    #[Route('/{id}/champs/{champId}', name: 'app_asset_remove_champ', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/assets/{id}/champs/{champId}',
        summary: 'Supprimer un champ d\'un bien',
        description: 'Dissocie un champ d\'un bien.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        description: 'ID du bien',
        example: 1
    )]
    #[OA\Parameter(
        name: 'champId',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        description: 'ID du champ à dissocier',
        example: 2
    )]
    #[OA\Response(
        response: 200,
        description: 'Champ supprimé avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Champ supprimé avec succès.',
                'data' => [
                    'id' => 1,
                    'reference' => 'PAT-2026-00001',
                    'nom' => 'Ordinateur Portable HP ProBook 450 G10',
                    'champs' => [
                        ['id' => 1, 'nom' => 'Type de toit'],
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
                'message' => 'Requête invalide.',
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
                'message' => 'Ce champ est introuvable.',
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
        int $champId,
        ChampRepository $champRepository,
        AssetResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // ✅ Vérifier que le bien n'est pas supprimé
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est supprimé.', Response::HTTP_CONFLICT);
        }

        // ✅ Vérifier que le champ existe
        $champ = $champRepository->getActiveById($champId);
        if (!$champ) {
            return $apiResponse->error('Ce champ est introuvable.', Response::HTTP_NOT_FOUND);
        }

        // ✅ Vérifier que le champ est bien associé au bien
        if (!$asset->getChamps()->contains($champ)) {
            return $apiResponse->error('Ce champ n\'est pas associé à ce bien.', Response::HTTP_NOT_FOUND);
        }

        // ✅ Supprimer le champ du bien
        $asset->removeChamp($champ);

        // ✅ Utiliser l'EntityManager injecté
        $this->entityManager->flush();

        return $apiResponse->success(
            $responseBuilder->buildDetail($asset),
            Response::HTTP_OK,
            'Champ supprimé avec succès.'
        );
    }
}