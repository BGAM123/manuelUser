<?php

namespace App\Controller\Core\RolePermission;

use App\Repository\Core\RoleRepository;
use App\Repository\Core\RolePermissionRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "RolePermission")]
class GetByRoleController extends AbstractController
{
    public function __construct(
        private RoleRepository $roleRepository,
        private RolePermissionRepository $rolePermissionRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/role-permission/by-role/{roleId<([1-9][0-9]*)>}', name: 'app_core_role_permission_get_by_role', methods: ['GET'])]
    #[OA\Get(
        path: '/core/role-permission/by-role/{roleId}',
        summary: 'Associations d\'un rôle',
        tags: ['RolePermission'],
        description: "Retourne toutes les associations RolePermission d'un rôle.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 3))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Associations récupérées avec succès.'),
            new OA\Response(response: 404, description: 'Rôle non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getByRole(int $roleId): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'GetRolePermissionsByRole');

        $role = $this->roleRepository->find($roleId);

        if (!$role) {
            return $this->json(['code' => 404, 'message' => 'Rôle non trouvé.'], 404);
        }

        $rolePermissions = $this->rolePermissionRepository->findBy(['idRole' => $role]);

        $data = array_map(fn($rp) => [
            'id' => $rp->getId(),
            'permission' => [
                'id' => $rp->getIdPermission()->getId(),
                'nom' => $rp->getIdPermission()->getNom(),
                'description' => $rp->getIdPermission()->getDescription(),
            ],
            'createdAt' => $rp->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $rolePermissions);

        return $this->json([
            'role' => [
                'id' => $role->getId(),
                'nom' => $role->getNom(),
            ],
            'total' => count($data),
            'rolePermissions' => $data
        ], 200);
    }
}