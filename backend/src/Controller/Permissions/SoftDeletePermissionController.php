<?php

namespace App\Controller\Permissions;

use App\Entity\Permission;
use App\Repository\PermissionRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/permissions')]
#[OA\Tag(name: 'Permissions')]
final class SoftDeletePermissionController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_permission_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/permissions/{id}/soft-delete',
        summary: 'Suppression logique d une permission',
        description: "Passe is_delete (et is_active) à true : la permission reste en base (les rôles qui la référencent ne sont pas impactés) mais disparaît des listes actives."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Permission désactivée (suppression logique)',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Permission supprimée (logiquement) avec succès.', 'data' => null]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found - Permission non trouvée')]
    public function __invoke(
        Permission $permission,
        PermissionRepository $permissionRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $permissionRepository->softDelete($permission);

        return $apiResponse->success(null, Response::HTTP_OK, 'Permission supprimée (logiquement) avec succès.');
    }
}
