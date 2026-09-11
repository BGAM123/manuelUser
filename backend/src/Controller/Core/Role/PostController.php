<?php

namespace App\Controller\Core\Role;

use App\Entity\Core\Role;
use App\Entity\Core\RolePermission;
use App\Repository\Core\PermissionRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Role")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private PermissionRepository $permissionRepository,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/core/role', name: 'app_core_role_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/role',
        summary: 'Créer un nouveau rôle avec permissions',
        tags: ['Role'],
        description: "Crée un rôle et lui assigne des permissions en une seule requête.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['nom'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Gestionnaire'),
                    new OA\Property(property: 'description', type: 'string', example: 'Peut gérer les courriers'),
                    new OA\Property(
                        property: 'permissions',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 5, 7],
                        description: 'IDs des permissions à assigner (optionnel)'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Rôle créé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'message', type: 'string', example: 'Rôle créé avec succès'),
                        new OA\Property(property: 'permissionsCount', type: 'integer', example: 4),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostRole');

        $data = json_decode($request->getContent(), true);
        $this->functionService->validate($data);
        
        $permissionsIds = $data['permissions'] ?? [];
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'permissions']);

        try {
            // ðŸ’¾ CrÃ©er le Role
            $role = new Role();
            $role = $this->crudService->postEntity($role, $data);

            // ðŸ”— Assigner les permissions
            $permissionsCount = 0;
            if (!empty($permissionsIds) && is_array($permissionsIds)) {
                foreach ($permissionsIds as $permissionId) {
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
            }

            return $this->json([
                'id' => $role->getId(),
                'message' => 'Rôle créé avec succès',
                'permissionsCount' => $permissionsCount
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}