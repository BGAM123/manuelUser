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
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Gère l'affectation d'une permission existante à un rôle existant
 * (ajout d'une ligne dans la table de jointure role_permission).
 */
#[Route('/roles/{roleId}/permissions/{permissionId}')]
#[OA\Tag(name: 'Roles')]
final class AssignPermissionToRoleController extends AbstractController
{
    #[Route('', name: 'app_role_assign_permission', methods: ['POST'])]
    #[OA\Post(
        path: '/roles/{roleId}/permissions/{permissionId}',
        summary: 'Affecter une permission à un rôle',
        description: "Ajoute la permission {permissionId} à la liste des permissions du rôle {roleId}."
    )]
    #[OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'permissionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 201,
        description: 'Created - Permission affectée au rôle avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Permission affectée au rôle avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Administrateur',
                    'is_active' => true,
                    'permissions' => [
                        ['id' => 2, 'nom' => 'assign_permission_to_role', 'is_active' => true]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found - Rôle ou permission non trouvé(e)')]
    #[OA\Response(response: 409, description: 'Conflict - Cette permission est déjà affectée à ce rôle')]
    public function __invoke(
        int $roleId,
        int $permissionId,
        RoleRepository $roleRepository,
        PermissionRepository $permissionRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $role = $roleRepository->getRoleById($roleId);
        if (!$role || $role->isDelete()) {
            return $apiResponse->error('Rôle non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $permission = $permissionRepository->getPermissionById($permissionId);
        if (!$permission || $permission->isDelete()) {
            return $apiResponse->error('Permission non trouvée.', Response::HTTP_NOT_FOUND);
        }

        $assigned = $roleRepository->assignPermission($role, $permission);
        if (!$assigned) {
            return $apiResponse->error('Cette permission est déjà affectée à ce rôle.', Response::HTTP_CONFLICT);
        }

        $data = json_decode($serializer->serialize($role, 'json', ['groups' => ['role:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_CREATED, 'Permission affectée au rôle avec succès.');
    }
}
