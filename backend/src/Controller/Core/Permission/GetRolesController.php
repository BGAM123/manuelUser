<?php

namespace App\Controller\Core\Permission;

use App\Repository\Core\PermissionRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Permission")]
class GetRolesController extends AbstractController
{
    public function __construct(
        private PermissionRepository $permissionRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/permission/{permissionId<([1-9][0-9]*)>}/roles', name: 'app_core_permission_get_roles', methods: ['GET'])]
    #[OA\Get(
        path: '/core/permission/{permissionId}/roles',
        summary: 'Rôles ayant cette permission',
        tags: ['Permission'],
        description: "Retourne tous les rôles qui ont cette permission.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'permissionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 5))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rôles récupérés avec succès.'),
            new OA\Response(response: 404, description: 'Permission non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getRoles(int $permissionId): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'GetPermissionRoles');

        $permission = $this->permissionRepository->find($permissionId);

        if (!$permission) {
            return $this->json(['code' => 404, 'message' => 'Permission non trouvée.'], 404);
        }

        $roles = array_map(fn($rp) => [
            'id' => $rp->getIdRole()->getId(),
            'nom' => $rp->getIdRole()->getNom(),
            'description' => $rp->getIdRole()->getDescription(),
        ], $permission->getRolePermissions()->toArray());

        return $this->json([
            'permission' => [
                'id' => $permission->getId(),
                'nom' => $permission->getNom(),
            ],
            'total' => count($roles),
            'roles' => $roles
        ], 200);
    }
}