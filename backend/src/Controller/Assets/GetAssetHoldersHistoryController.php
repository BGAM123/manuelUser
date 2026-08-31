<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Repository\AssetAssignmentRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetAssignmentResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets/{id}/holders-history', name: 'app_asset_holders_history', methods: ['GET'])]
#[OA\Tag(name: 'Assets')]
final class GetAssetHoldersHistoryController extends AbstractController
{
    #[OA\Get(
        path: '/assets/{id}/holders-history',
        summary: 'Historique des détenteurs d\'un bien',
        description: 'Retourne l\'historique complet des affectations (détenteurs) d\'un bien, trié par date de début décroissante.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Identifiant du bien',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Historique des détenteurs',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Historique des détenteurs récupéré avec succès.',
                'data' => [
                    [
                        'id' => 2,
                        'typeAffectation' => 'AFFECTATION',
                        'dateDebut' => '2026-08-08',
                        'dateFin' => null,
                        'commentaire' => 'Affectation au service informatique',
                        'detenteur' => [
                            // 'type' => 'user',
                            'id' => 5,
                            'nom' => 'Dupont',
                            'prenom' => 'Jean',
                            'matricule' => 'MAT-001',
                            'email' => 'jean.dupont@example.com'
                        ],
                        'createdAt' => '08-08-2026 14:30:00'
                    ],
                    [
                        'id' => 1,
                        'typeAffectation' => 'AFFECTATION',
                        'dateDebut' => '2026-08-01',
                        'dateFin' => '2026-08-08',
                        'commentaire' => 'Affectation initiale',
                        'detenteur' => [
                            // 'type' => 'service',
                            'id' => 2,
                            'nom' => 'Informatique',
                            'sigle' => 'IT'
                        ],
                        'createdAt' => '01-08-2026 09:00:00'
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found - Bien introuvable')]
    public function __invoke(
        Asset $asset,
        AssetAssignmentRepository $assignmentRepository,
        AssetAssignmentResponseBuilder $assignmentResponseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $assignments = $assignmentRepository->findByAsset($asset->getId());

        $data = array_map(function ($assignment) use ($assignmentResponseBuilder) {
            $assignmentData = $assignmentResponseBuilder->buildDetail($assignment);
            
            // Ajouter les informations du détenteur avec matricule si c'est un utilisateur
            $user = $assignment->getUser();
            $service = $assignment->getService();
            
            if ($user) {
                $assignmentData['detenteur'] = [
                    // 'type' => 'user',
                    'id' => $user->getId(),
                    'nom' => $user->getLastName(),
                    'prenom' => $user->getFirstName(),
                    'matricule' => $user->getMatricule(),
                    'email' => $user->getEmail()
                ];
            } elseif ($service) {
                $assignmentData['detenteur'] = [
                    // 'type' => 'service',
                    'id' => $service->getId(),
                    'nom' => $service->getNom(),
                    'sigle' => $service->getSigle()
                ];
            }
            
            return $assignmentData;
        }, $assignments);

        // Trier par date de début décroissante
        usort($data, fn($a, $b) => strtotime($b['dateDebut'] ?? 'now') <=> strtotime($a['dateDebut'] ?? 'now'));

        return $apiResponse->success($data, Response::HTTP_OK, 'Historique des détenteurs récupéré avec succès.');
    }
}
