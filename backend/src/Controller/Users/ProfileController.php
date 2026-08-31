<?php

// src/Controller/ProfileController.php

namespace App\Controller\Users;

use App\Entity\User;
use App\Repository\AssetRepository;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/profile')]
#[OA\Tag(name: 'Profile')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile_show', methods: ['GET'])]
    #[OA\Get(
        path: '/profile',
        summary: 'Récupérer le profil de l utilisateur connecté',
        description: 'Retourne les détails du profil de l utilisateur authentifié.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Profil de l utilisateur retourné avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Profil récupéré avec succès.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Profil récupéré avec succès.',
                'data' => [
                    'id' => 9,
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                    'email' => 'john.doe@example.com',
                    'matricule' => 'MAT-00125',
                    'is_active' => true,
                    'twoFactorEnabled' => true,
                    'createdAt' => '27-07-2026 14:30:45',
                    'service' => [
                        'id' => 2,
                        'nom' => 'Comptabilité',
                        'sigle' => 'COMP',
                        'type_service' => 'poste',
                        'ordre' => 1,
                        'is_active' => true
                    ],
                    'assignedRoles' => [
                        [
                            'id' => 1,
                            'nom' => 'Administrateur'
                        ],
                        [
                            'id' => 2,
                            'nom' => 'Gestionnaire'
                        ]
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
                    ],
                    'permissions' => [
                        'inheritedFromRoles' => [],
                        'inheritedFromGroupes' => [],
                        'granted' => [],
                        'revoked' => [],
                        'effective' => []
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 401,
                'message' => 'Unauthorized.',
                'data' => null
            ]
        )
    )]
    public function getProfile(
        #[CurrentUser] User $user,
        AssetRepository $assetRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): Response {
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

        // Sérialiser les informations du profil
        $userData = $serializer->normalize(
            $user,
            null,
            ['groups' => ['user:detail']]
        );
        $userData['langue'] = $user->getLangue();
        // Conserver les biens de l'utilisateur
        $userData['assets'] = $assetsData;
        

        // Ajouter les permissions de l'utilisateur
        $breakdown = $user->getPermissionsBreakdown();

        $userData['permissions'] = [
            'inheritedFromRoles' => json_decode(
                $serializer->serialize(
                    $breakdown['inheritedFromRoles'],
                    'json',
                    ['groups' => ['permission:list']]
                ),
                true
            ),
            'inheritedFromGroupes' => json_decode(
                $serializer->serialize(
                    $breakdown['inheritedFromGroupes'],
                    'json',
                    ['groups' => ['permission:list']]
                ),
                true
            ),
            'granted' => json_decode(
                $serializer->serialize(
                    $breakdown['granted'],
                    'json',
                    ['groups' => ['permission:list']]
                ),
                true
            ),
            'revoked' => json_decode(
                $serializer->serialize(
                    $breakdown['revoked'],
                    'json',
                    ['groups' => ['permission:list']]
                ),
                true
            ),
            'effective' => json_decode(
                $serializer->serialize(
                    $breakdown['effective'],
                    'json',
                    ['groups' => ['permission:list']]
                ),
                true
            ),
        ];

        return $apiResponse->success(
            $userData,
            Response::HTTP_OK,
            'Profil récupéré avec succès.'
        );
    }

    #[Route('/password', name: 'app_profile_update_password', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/profile/password',
        summary: 'Mettre à jour le mot de passe de l utilisateur connecté',
        description: 'Met à jour le mot de passe de l utilisateur authentifié.'
    )]
    #[OA\RequestBody(
        description: 'Nouveau mot de passe et confirmation',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['password', 'passwordConfirm'],
            properties: [
                new OA\Property(
                    property: 'password',
                    type: 'string',
                    example: 'newPassword123'
                ),
                new OA\Property(
                    property: 'passwordConfirm',
                    type: 'string',
                    example: 'newPassword123'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Mot de passe mis à jour avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Mot de passe mis à jour avec succès.'
                ),
                new OA\Property(
                    property: 'data',
                    type: 'null',
                    example: null
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'La validation a échoué.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized - L utilisateur n est pas authentifié'
    )]
    public function updatePassword(
        #[CurrentUser] User $user,
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $apiResponse->error(
                'Invalid JSON payload.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $password = (string) ($payload['password'] ?? '');
        $passwordConfirm = (string) ($payload['passwordConfirm'] ?? '');

        if (empty($password) || empty($passwordConfirm)) {
            return $apiResponse->error(
                'Password and passwordConfirm are required.',
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($password !== $passwordConfirm) {
            return $apiResponse->error(
                'Passwords do not match.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $password);

        $userRepository->updatePassword(
            $user,
            $hashedPassword
        );

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Mot de passe mis à jour avec succès.'
        );
    }
}
