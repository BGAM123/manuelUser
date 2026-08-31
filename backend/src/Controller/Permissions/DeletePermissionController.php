<?php

namespace App\Controller\Permissions;

use App\Entity\Permission;
use App\Exception\ResourceInUseException;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/permissions')]
#[OA\Tag(name: 'Permissions')]
final class DeletePermissionController extends AbstractController
{
    #[Route('/{id}', name: 'app_permission_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/permissions/{id}',
        summary: 'Suppression physique d une permission',
        description: "Supprime définitivement la permission de la base de données (entityManager->remove() + flush()). Action irréversible."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Permission supprimée définitivement',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Permission supprimée définitivement avec succès.', 'data' => null]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found - Permission non trouvée')]
    public function __invoke(
        Permission $permission,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $forceDeleteService->delete($permission);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(null, Response::HTTP_OK, 'Permission supprimée définitivement avec succès.');
    }
}
