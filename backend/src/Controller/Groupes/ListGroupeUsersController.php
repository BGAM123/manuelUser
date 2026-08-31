<?php

namespace App\Controller\Groupes;

use App\Repository\GroupeRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/groupes/{groupeId}/users')]
#[OA\Tag(name: 'Groupes')]
final class ListGroupeUsersController extends AbstractController
{
    #[Route('', name: 'app_groupe_list_users', methods: ['GET'])]
    #[OA\Get(
        path: '/groupes/{groupeId}/users',
        summary: 'Lister les utilisateurs d un groupe',
        description: 'Retourne la liste des utilisateurs membres du groupe {groupeId}.'
    )]
    #[OA\Parameter(name: 'groupeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Utilisateurs du groupe retournés',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Utilisateurs du groupe retournés avec succès.',
                'data' => [
                    ['id' => 1, 'firstName' => 'John', 'lastName' => 'Doe', 'email' => 'john.doe@example.com', 'matricule' => 'MAT-00125', 'is_active' => true]
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Groupe introuvable.', 'data' => null]))]
    public function __invoke(
        int $groupeId,
        GroupeRepository $groupeRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $groupe = $groupeRepository->getGroupeById($groupeId);
        if (!$groupe || $groupe->isDelete()) {
            return $apiResponse->error('Groupe introuvable.', Response::HTTP_NOT_FOUND);
        }

        $users = [];
        foreach ($groupe->getUsers() as $user) {
            if ($user->isDelete()) {
                continue;
            }

            $users[] = [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'matricule' => $user->getMatricule(),
                'is_active' => $user->isActive(),
            ];
        }

        return $apiResponse->success($users, Response::HTTP_OK, 'Utilisateurs du groupe retournés avec succès.');
    }
}
