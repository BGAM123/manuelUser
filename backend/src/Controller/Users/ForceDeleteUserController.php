<?php

namespace App\Controller\Users;

use App\Entity\User;
use App\Exception\ResourceInUseException;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users')]
#[OA\Tag(name: 'Users')]
final class ForceDeleteUserController extends AbstractController
{
    #[Route('/{id}/force', name: 'app_user_force_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/users/{id}/force',
        summary: 'Supprimer physiquement un utilisateur',
        description: 'Suppression physique définitive d\'un utilisateur. Attention : cette action est irréversible.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'L identifiant unique de l utilisateur',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Utilisateur supprimé physiquement avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Utilisateur supprimé physiquement avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request - Suppression impossible car la ressource est liée à d\'autres données',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'Cet utilisateur est utilisé dans d\'autres données et ne peut pas être supprimé physiquement.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 404,
                'message' => 'La ressource demandée est introuvable.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        User $user,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $forceDeleteService->delete($user);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Utilisateur supprimé physiquement avec succès.'
        );
    }
}
