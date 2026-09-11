<?php

namespace App\Controller\Core\RolePermission;

use App\Repository\Core\PermissionRepository;
use App\Repository\Core\RolePermissionRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "RolePermission")]
class GetByPermissionController extends AbstractController
{
    public function __construct(
        private PermissionRepository $permissionRepository,
        private RolePermissionRepository $rolePermissionRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/role-permission/by-permission/{permissionId<([1-9][0-9]*)>}', name: 'app_core_role_permission_get_by_permission', methods: ['GET'])]
    #[OA\Get(
        path: '/core/role-permission/by-permission/{permissionId}',
        summary: 'Associations d\'une permission',
        tags: ['RolePermission'],
        description: "Retourne toutes les associations RolePermission d'une permission.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'permissionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 5))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Associations récupérées avec succès.'),
            new OA\Response(response: 404, description: 'Permission non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getByPermission(int $permissionId): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'GetRolePermissionsByPermission');

        $permission = $this->permissionRepository->find($permissionId);

        if (!$permission) {
            return $this->json(['code' => 404, 'message' => 'Permission non trouvée.'], 404);
        }

        $rolePermissions = $this->rolePermissionRepository->findBy(['idPermission' => $permission]);

        $data = array_map(fn($rp) => [
            'id' => $rp->getId(),
            'role' => [
                'id' => $rp->getIdRole()->getId(),
                'nom' => $rp->getIdRole()->getNom(),
                'description' => $rp->getIdRole()->getDescription(),
            ],
            'createdAt' => $rp->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $rolePermissions);

        return $this->json([
            'permission' => [
                'id' => $permission->getId(),
                'nom' => $permission->getNom(),
            ],
            'total' => count($data),
            'rolePermissions' => $data
        ], 200);
    }
}