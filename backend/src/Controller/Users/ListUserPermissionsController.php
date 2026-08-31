<?php

namespace App\Controller\Users;

use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/users/{id}/permissions')]
#[OA\Tag(name: 'Users')]
final class ListUserPermissionsController extends AbstractController
{
    #[Route('', name: 'app_user_permissions_list', methods: ['GET'])]
    #[OA\Get(
        path: '/users/{id}/permissions',
        summary: 'Lister les permissions d un utilisateur',
        description: 'Retourne toutes les permissions d un utilisateur, héritées de ses rôles et groupes, ainsi que celles accordées ou révoquées explicitement.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Permissions utilisateur retournées',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Permissions utilisateur retournées avec succès.',
                'data' => [
                    'inheritedFromRoles' => [
                        ['id' => 1, 'nom' => 'voir_rapports']
                    ],
                    'inheritedFromGroupes' => [
                        ['id' => 2, 'nom' => 'gerer_biens']
                    ],
                    'granted' => [
                        ['id' => 3, 'nom' => 'gerer_clients']
                    ],
                    'revoked' => [
                        ['id' => 4, 'nom' => 'gerer_projets']
                    ],
                    'effective' => [
                        ['id' => 1, 'nom' => 'voir_rapports'],
                        ['id' => 2, 'nom' => 'gerer_biens'],
                        ['id' => 3, 'nom' => 'gerer_clients']
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Utilisateur introuvable.', 'data' => null]))]
    public function __invoke(
        int $id,
        UserRepository $userRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $user = $userRepository->getUserById($id);
        if (!$user) {
            return $apiResponse->error('Utilisateur introuvable.', Response::HTTP_NOT_FOUND);
        }

        $breakdown = $user->getPermissionsBreakdown();

        $data = [
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

        return $apiResponse->success($data, Response::HTTP_OK, 'Permissions utilisateur retournées avec succès.');
    }
}
