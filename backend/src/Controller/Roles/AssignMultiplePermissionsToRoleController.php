<?php

/**
 * AssignMultiplePermissionsToRoleController - Affecte plusieurs permissions à un rôle en une seule requête.
 *
 * Endpoint : POST /roles/{roleId}/permissions
 * Body: { "permission_ids": [1, 2, 3] }
 *
 * Gère :
 * - Validation de l'existence du rôle
 * - Validation de l'existence des permissions
 * - Évite les doublons
 * - Crée les relations role_permission
 */

namespace App\Controller\Roles;

use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Gère l'affectation de plusieurs permissions existantes à un rôle existant
 * (ajout de plusieurs lignes dans la table de jointure role_permission).
 */
#[Route('/roles/{roleId}/permissions')]
#[OA\Tag(name: 'Roles')]
final class AssignMultiplePermissionsToRoleController extends AbstractController
{
    #[Route('', name: 'app_role_assign_multiple_permissions', methods: ['POST'])]
    #[OA\Post(
        path: '/roles/{roleId}/permissions',
        summary: 'Affecter plusieurs permissions à un rôle',
        description: "Ajoute les permissions spécifiées à la liste des permissions du rôle {roleId}. Évite les doublons automatiquement."
    )]
    #[OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['permission_ids'],
            properties: [
                new OA\Property(property: 'permission_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3, 4])
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Permissions affectées au rôle avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Permissions affectées au rôle avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Administrateur',
                    'is_active' => true,
                    'permissions' => [
                        ['id' => 1, 'nom' => 'USER_CREATE', 'is_active' => true],
                        ['id' => 2, 'nom' => 'USER_DELETE', 'is_active' => true],
                        ['id' => 3, 'nom' => 'PROJECT_EDIT', 'is_active' => true]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        int $roleId,
        Request $request,
        RoleRepository $roleRepository,
        PermissionRepository $permissionRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // 1. Valider que le rôle existe
        $role = $roleRepository->getRoleById($roleId);
        if (!$role || $role->isDelete()) {
            return $apiResponse->error('Rôle non trouvé.', Response::HTTP_NOT_FOUND);
        }

        // 2. Récupérer et valider le payload
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['permission_ids']) || !is_array($payload['permission_ids'])) {
            return $apiResponse->error('permission_ids is required and must be an array.', Response::HTTP_BAD_REQUEST);
        }

        if (empty($payload['permission_ids'])) {
            return $apiResponse->error('permission_ids cannot be empty.', Response::HTTP_BAD_REQUEST);
        }

        $permissionIds = array_unique(array_map('intval', $payload['permission_ids']));

        // 3. Valider l'existence de toutes les permissions
        $permissions = [];
        foreach ($permissionIds as $permissionId) {
            $permission = $permissionRepository->getPermissionById($permissionId);
            if (!$permission || $permission->isDelete()) {
                return $apiResponse->error(sprintf('Permission %d introuvable.', $permissionId), Response::HTTP_BAD_REQUEST);
            }
            $permissions[] = $permission;
        }

        // 4. Affecter les permissions au rôle (évite les doublons)
        $assignedCount = 0;
        foreach ($permissions as $permission) {
            if (!$role->getPermissions()->contains($permission)) {
                $role->getPermissions()->add($permission);
                $assignedCount++;
            }
        }

        // 5. Persister et retourner
        $roleRepository->save($role);

        $data = json_decode($serializer->serialize($role, 'json', ['groups' => ['role:detail']]), true);
        return $apiResponse->success(
            $data,
            Response::HTTP_CREATED,
            sprintf('Permissions affectées au rôle avec succès. %d nouvelles relations créées.', $assignedCount)
        );
    }
}
