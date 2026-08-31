<?php

namespace App\Controller\Users;

use App\Entity\User;
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

#[Route('/users/{id}/password', name: 'app_user_update_password', methods: ['PATCH'])]
#[OA\Tag(name: 'Users')]
final class UpdateUserPasswordController extends AbstractController
{
    #[OA\Patch(
        path: '/users/{id}/password',
        summary: 'Mettre à jour le mot de passe d un utilisateur',
        description: 'Met à jour uniquement le mot de passe d un utilisateur existant.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'L identifiant unique de l utilisateur',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        description: 'Nouveau mot de passe et confirmation',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['password', 'passwordConfirm'],
            properties: [
                new OA\Property(property: 'password', type: 'string', example: 'newPassword123'),
                new OA\Property(property: 'passwordConfirm', type: 'string', example: 'newPassword123')
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
                new OA\Property(property: 'message', type: 'string', example: 'Mot de passe mis à jour avec succès.'),
                new OA\Property(property: 'data', type: 'null', example: null)
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        User $user,
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        $password = (string) ($payload['password'] ?? '');
        $passwordConfirm = (string) ($payload['passwordConfirm'] ?? '');

        if (empty($password) || empty($passwordConfirm)) {
            return $apiResponse->error('Password and passwordConfirm are required.', Response::HTTP_BAD_REQUEST);
        }

        if ($password !== $passwordConfirm) {
            return $apiResponse->error('Passwords do not match.', Response::HTTP_BAD_REQUEST);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $userRepository->updatePassword($user, $hashedPassword);

        return $apiResponse->success(null, Response::HTTP_OK, 'Mot de passe mis à jour avec succès.');
    }
}
