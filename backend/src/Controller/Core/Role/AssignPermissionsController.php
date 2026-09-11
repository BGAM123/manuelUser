<?php

namespace App\Controller\Core\Role;

use App\Entity\Core\RolePermission;
use App\Repository\Core\RoleRepository;
use App\Repository\Core\PermissionRepository;
use App\Repository\Core\RolePermissionRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Role")]
class AssignPermissionsController extends AbstractController
{
    public function __construct(
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository,
        private RolePermissionRepository $rolePermissionRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/core/role/{roleId<([1-9][0-9]*)>}/assign-permissions', name: 'app_core_role_assign_permissions', methods: ['POST'])]
    #[OA\Post(
        path: '/core/role/{roleId}/assign-permissions',
        summary: 'Assigner des permissions à un rôle (REMPLACER)',
        tags: ['Role'],
        description: "Remplace toutes les permissions d'un rôle par les nouvelles fournies.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 3))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['permissions'],
                properties: [
                    new OA\Property(
                        property: 'permissions',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 5, 7],
                        description: 'IDs des permissions à assigner'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Permissions assignées avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Permissions assignées avec succès'),
                        new OA\Property(property: 'permissionsCount', type: 'integer', example: 4),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Rôle non trouvé.'),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function assignPermissions(Request $request, int $roleId): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'AssignPermissionsToRole');

        $role = $this->roleRepository->find($roleId);

        if (!$role) {
            return $this->json(['code' => 404, 'message' => 'Rôle non trouvé.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['permissions']) || !is_array($data['permissions'])) {
            return $this->json(['code' => 400, 'message' => 'Le champ "permissions" est requis et doit être un tableau.'], 400);
        }

        try {
            // ðŸ”¥ Ã‰TAPE 1 : SUPPRIMER toutes les anciennes permissions
            $oldRolePermissions = $this->rolePermissionRepository->findBy(['idRole' => $role]);
            foreach ($oldRolePermissions as $oldRp) {
                $this->em->remove($oldRp);
            }
            $this->em->flush();

            // âœ… Ã‰TAPE 2 : AJOUTER les nouvelles permissions
            $permissionsCount = 0;
            foreach ($data['permissions'] as $permissionId) {
                $permission = $this->permissionRepository->find($permissionId);
                if ($permission) {
                    $rolePermission = new RolePermission();
                    $rolePermission->setIdRole($role);
                    $rolePermission->setIdPermission($permission);
                    $this->em->persist($rolePermission);
                    $permissionsCount++;
                }
            }
            $this->em->flush();

            return $this->json([
                'message' => 'Permissions assignées avec succès',
                'permissionsCount' => $permissionsCount
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}