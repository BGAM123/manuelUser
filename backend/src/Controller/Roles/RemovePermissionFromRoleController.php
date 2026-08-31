<?php

namespace App\Controller\Roles;

use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Retire une permission d'un rôle (suppression de la ligne de jointure role_permission),
 * sans jamais toucher aux entités Role/Permission elles-mêmes.
 */
#[Route('/roles/{roleId}/permissions/{permissionId}')]
#[OA\Tag(name: 'Roles')]
final class RemovePermissionFromRoleController extends AbstractController
{
    #[Route('', name: 'app_role_remove_permission', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/roles/{roleId}/permissions/{permissionId}',
        summary: 'Retirer une permission d un rôle',
        description: "Supprime uniquement l'association entre le rôle {roleId} et la permission {permissionId}. Ni le rôle ni la permission ne sont supprimés."
    )]
    #[OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'permissionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Permission retirée du rôle',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Permission retirée du rôle avec succès.', 'data' => null]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        int $roleId,
        int $permissionId,
        RoleRepository $roleRepository,
        PermissionRepository $permissionRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $role = $roleRepository->getRoleById($roleId);
        if (!$role || $role->isDelete()) {
            return $apiResponse->error('Rôle non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $permission = $permissionRepository->getPermissionById($permissionId);
        if (!$permission) {
            return $apiResponse->error('Permission non trouvée.', Response::HTTP_NOT_FOUND);
        }

        $removed = $roleRepository->unassignPermission($role, $permission);
        if (!$removed) {
            return $apiResponse->error("Cette permission n'est pas affectée à ce rôle.", Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(null, Response::HTTP_OK, 'Permission retirée du rôle avec succès.');
    }
}
