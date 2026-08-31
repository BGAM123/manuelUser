<?php

namespace App\Controller\Roles;

use App\Entity\Role;
use App\Exception\ResourceInUseException;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/roles')]
#[OA\Tag(name: 'Roles')]
final class DeleteRoleController extends AbstractController
{
    #[Route('/{id}', name: 'app_role_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/roles/{id}',
        summary: 'Suppression physique d un rôle',
        description: "Supprime définitivement le rôle de la base de données (entityManager->remove() + flush()). Action irréversible : les associations user_role et role_permission le concernant sont supprimées en cascade."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Rôle supprimé définitivement',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Rôle supprimé définitivement avec succès.', 'data' => null]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found - Rôle non trouvé')]
    public function __invoke(
        Role $role,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $forceDeleteService->delete($role);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(null, Response::HTTP_OK, 'Rôle supprimé définitivement avec succès.');
    }
}
