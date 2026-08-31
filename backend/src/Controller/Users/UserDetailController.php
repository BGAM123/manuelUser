<?php

namespace App\Controller\Users;

use App\Repository\AssetRepository;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;


#[Route('/users/{id}', name: 'app_user_detail', methods: ['GET'])]
#[OA\Tag(name: 'Users')]
final class UserDetailController extends AbstractController
{
    #[OA\Get(
        path: '/users/{id}',
        summary: 'Retourne les détails d un utilisateur',
        description: 'Retourne les détails d un utilisateur spécifique.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'L identifiant unique de l utilisateur',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - détails de l utilisateur retournés avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Détails de l utilisateur retournés avec succès.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'User details returned successfully.',
                'data' => [
                    'id' => 1,
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                    'email' => 'john.doe@example.com',
                    'matricule' => 'MAT-00125',
                    'is_active' => true,
                    'twoFactorEnabled' => true,
                    'createdAt' => '01-01-2024 10:30:45',
                    'service' => [
                        'id' => 2,
                        'nom' => 'Informatique',
                        // 'sigle' => 'IT',
                        // 'type_service' => 'poste',
                        // 'ordre' => 1,
                        'is_active' => true
                    ],
                    'assets' => [
                        [
                            'id' => 25,
                            'nom' => 'Ordinateur HP',
                            'categorie' => [
                                'id' => 2,
                                'nom' => 'Informatique'
                            ],
                            'typeBien' => [
                                'id' => 5,
                                'nom' => 'Ordinateur'
                            ],
                            'etatBien' => [
                                'id' => 1,
                                'nom' => 'Bon'
                            ],
                            'statut' => 'ACTIF'
                        ]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        UserRepository $userRepository,
        AssetRepository $assetRepository,
        Request $request,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse,
        int $id
    ): Response {
        $user = $userRepository->getUserById($id);
        if (!$user) {
            return $apiResponse->error("L'utilisateur demandé est introuvable.", Response::HTTP_NOT_FOUND);
        }

        // Récupérer les biens actifs de l'utilisateur
        $assets = $assetRepository->findActiveAssetsByUser($user->getId());

        // Transformer les biens pour une structure simplifiée
        $assetsData = [];
        foreach ($assets as $asset) {
            $category = $asset->getCategories()->first();
            $assetType = $asset->getAssetTypes()->first();
            $etatBien = $asset->getEtatBiens()->first();
            
            $assetsData[] = [
                'id' => $asset->getId(),
                'nom' => $asset->getNom(),
                'categorie' => $category ? [
                    'id' => $category->getId(),
                    'nom' => $category->getNom()
                ] : null,
                'typeBien' => $assetType ? [
                    'id' => $assetType->getId(),
                    'nom' => $assetType->getNom()
                ] : null,
                'etatBien' => $etatBien ? [
                    'id' => $etatBien->getId(),
                    'nom' => $etatBien->getNom()
                ] : null,
                'statut' => $asset->getStatut()
            ];
        }

        // Utiliser le normalizer personnalisé pour maîtriser la sérialisation
        // $userData = $serializer->normalize($user, 'json', ['_user_list' => true]);
        // ✅ CORRECTION : Utiliser les groupes de sérialisation avec gestion de circular reference
        $userData = $serializer->normalize($user, 'json', [
            'groups' => ['user:detail'], // Ou ['user:read'] selon vos besoins
            // Gestion de la circular reference pour les relations
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            },
            // Limiter la profondeur de sérialisation
            'circular_reference_limit' => 2
        ]);

        // Ajouter les biens aux données utilisateur
        $userData['assets'] = $assetsData;

        // Ajouter la décomposition des permissions pour afficher :
        // - héritées des rôles
        // - héritées des groupes
        // - accordées explicitement
        // - révoquées explicitement
        // - effectives
        $breakdown = $user->getPermissionsBreakdown();
        $userData['permissions'] = [
            'inheritedFromRoles' => json_decode(
                $serializer->serialize($breakdown['inheritedFromRoles'], 'json', ['groups' => ['permission:list']]),
                true
            ),
            'inheritedFromGroupes' => json_decode(
                $serializer->serialize($breakdown['inheritedFromGroupes'], 'json', ['groups' => ['permission:list']]),
                true
            ),
            'granted' => json_decode(
                $serializer->serialize($breakdown['granted'], 'json', ['groups' => ['permission:list']]),
                true
            ),
            'revoked' => json_decode(
                $serializer->serialize($breakdown['revoked'], 'json', ['groups' => ['permission:list']]),
                true
            ),
            'effective' => json_decode(
                $serializer->serialize($breakdown['effective'], 'json', ['groups' => ['permission:list']]),
                true
            ),
        ];

        return $apiResponse->success($userData, Response::HTTP_OK, 'Détails de l utilisateur retournés avec succès.');
    }
}
