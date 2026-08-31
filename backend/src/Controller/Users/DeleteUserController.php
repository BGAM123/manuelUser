<?php

namespace App\Controller\Users;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users/{id}', name: 'app_user_delete', methods: ['DELETE'])]
#[OA\Tag(name: 'Users')]
final class DeleteUserController extends AbstractController
{
    #[OA\Delete(
        path: '/users/{id}',
        summary: 'Supprimer un utilisateur logiquement',
        description: 'Supprime un utilisateur existant de la base de données.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'L identifiant unique de l utilisateur',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Utilisateur supprimé avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Utilisateur supprimé avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    public function __invoke(
        User $user,
        UserRepository $userRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $userRepository->removeUser($user);
        return $apiResponse->success(null, Response::HTTP_OK, 'Utilisateur supprimé avec succès .');
    }
}
