<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Repository\AssetRepository;
use App\Repository\EtatBienRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets/{id}/etat', name: 'app_asset_patch_etat', methods: ['PATCH'])]
#[OA\Tag(name: 'EtatBiens')]
final class PatchAssetEtatController extends AbstractController
{
    #[OA\Patch(
        path: '/assets/{id}/etat',
        summary: 'Modifier l\'état d\'un bien',
        description: 'Met à jour uniquement l\'état (etatBien) d\'un bien existant.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Identifiant du bien',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['etat_bien_ids'],
            properties: [
                new OA\Property(
                    property: 'etat_bien_ids',
                    type: 'array',
                    items: new OA\Items(type: 'integer'),
                    example: [1, 2],
                    description: 'IDs des états de biens à associer (remplace les existants)'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - État mis à jour avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'État du bien mis à jour avec succès.',
                'data' => [
                    'id' => 1,
                    'reference' => 'PAT-2026-00001',
                    'nom' => 'Ordinateur Portable HP ProBook 450 G10',
                    'etatBiens' => [
                        ['id' => 1, 'nom' => 'Fonctionnel'],
                        ['id' => 2, 'nom' => 'En maintenance']
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - IDs invalides')]
    #[OA\Response(response: 404, description: 'Not Found - Bien ou état introuvable')]
    public function __invoke(
        Asset $asset,
        Request $request,
        AssetRepository $assetRepository,
        EtatBienRepository $etatBienRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['etat_bien_ids']) || !is_array($payload['etat_bien_ids'])) {
            return $apiResponse->error('etat_bien_ids est requis et doit être un tableau.', Response::HTTP_BAD_REQUEST);
        }

        $ids = array_values(array_unique(array_map('intval', $payload['etat_bien_ids'])));
        $etats = $etatBienRepository->findActiveByIds($ids);

        if (count($etats) !== count($ids)) {
            return $apiResponse->error('Un ou plusieurs états de biens sont introuvables ou supprimés.', Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si l'état RÉFORMÉ est demandé sans demande validée
        foreach ($etats as $etat) {
            if ($etat->getNom() === 'RÉFORMÉ') {
                return $apiResponse->error('Pour passer un bien à l\'état RÉFORMÉ, vous devez utiliser le workflow de réforme : créer une demande via POST /assets/reforme/request puis la valider via POST /assets/reforme/validate.', Response::HTTP_BAD_REQUEST);
            }
        }

        // Synchroniser les états du bien
        $asset->syncEtatBiens($etats);
        $assetRepository->save($asset);

        // Construire la réponse
        $etatData = array_map(function ($etat) {
            return [
                'id' => $etat->getId(),
                'nom' => $etat->getNom()
            ];
        }, $etats);

        $data = [
            'id' => $asset->getId(),
            'reference' => $asset->getReference(),
            'nom' => $asset->getNom(),
            'etatBiens' => $etatData
        ];

        return $apiResponse->success($data, Response::HTTP_OK, 'État du bien mis à jour avec succès.');
    }
}
