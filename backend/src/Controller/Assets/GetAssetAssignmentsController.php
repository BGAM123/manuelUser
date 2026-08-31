<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Service\ApiResponseFactory;
use App\Service\AssetAssignmentResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Asset Assignments')]
final class GetAssetAssignmentsController extends AbstractController
{
    #[Route('/{id}/assignments', name: 'app_asset_assignments_list', methods: ['GET'])]
    #[OA\Get(
        path: '/assets/{id}/assignments',
        summary: 'Lister les affectations d\'un bien',
        description: 'Retourne la liste de toutes les affectations d\'un bien, triées par ordre chronologique.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 8)]
    #[OA\Response(
        response: 200,
        description: 'Liste des affectations',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Liste des affectations.',
                'data' => [
                    [
                        'id' => 1,
                        'typeAffectation' => 'AFFECTATION',
                        'dateDebut' => '2026-08-01',
                        'dateFin' => null,
                        'commentaire' => 'Affectation pour le suivi des activités terrain.',
                        'service' => [
                            'id' => 16,
                            'nom' => 'Service de la Santé Animale'
                        ],
                        'localisation' => [
                            'region' => ['id' => 1, 'nom' => 'Centre'],
                            'departement' => ['id' => 5, 'nom' => 'Mfoundi'],
                            'arrondissement' => ['id' => 12, 'nom' => 'Yaoundé I']
                        ],
                        'utilisateur' => [
                            'id' => 5,
                            'firstName' => 'Claire',
                            'lastName' => 'MVONDO'
                        ],
                        'piecesJointes' => [
                            [
                                'id' => 21,
                                'nom' => 'PV de remise',
                                'chemin' => '/uploads/assignments/pv-remise.pdf'
                            ]
                        ],
                        'createdAt' => '2026-08-01 09:15:00'
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Bien introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le bien demandé est introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Asset $asset,
        AssetAssignmentResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est supprimé.', Response::HTTP_CONFLICT);
        }

        $assignments = $asset->getAssignments()->filter(fn($a) => !$a->isDelete())->toArray();
        
        // Trier par date de création décroissante (plus récent en premier)
        usort($assignments, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $apiResponse->success(
            $responseBuilder->buildList($assignments),
            Response::HTTP_OK,
            'Liste des affectations.'
        );
    }
}
