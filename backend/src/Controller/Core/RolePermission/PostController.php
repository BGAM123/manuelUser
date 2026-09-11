<?php

namespace App\Controller\Core\RolePermission;

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

#[OA\Tag(name: "RolePermission")]
class PostController extends AbstractController
{
    public function __construct(
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository,
        private RolePermissionRepository $rolePermissionRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/core/role-permission', name: 'app_core_role_permission_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/role-permission',
        summary: 'Associer UNE permission à un rôle',
        tags: ['RolePermission'],
        description: "Crée une association entre un rôle et une permission.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['idRole', 'idPermission'],
                properties: [
                    new OA\Property(property: 'idRole', type: 'integer', example: 3, description: 'ID du rôle'),
                    new OA\Property(property: 'idPermission', type: 'integer', example: 5, description: 'ID de la permission'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Association créée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'message', type: 'string', example: 'Association créée avec succès'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Association déjà existante ou requête invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostRolePermission');

        $data = json_decode($request->getContent(), true);

        if (empty($data['idRole']) || empty($data['idPermission'])) {
            return $this->json(['code' => 400, 'message' => 'idRole et idPermission sont requis.'], 400);
        }

        try {
            $role = $this->roleRepository->find($data['idRole']);
            if (!$role) {
                return $this->json(['code' => 404, 'message' => 'RÃ´le non trouvÃ©.'], 404);
            }

            $permission = $this->permissionRepository->find($data['idPermission']);
            if (!$permission) {
                return $this->json(['code' => 404, 'message' => 'Permission non trouvÃ©e.'], 404);
            }

            // ðŸ”¹ VÃ©rifier si l'association existe dÃ©jÃ 
            $existing = $this->rolePermissionRepository->findOneBy([
                'idRole' => $role,
                'idPermission' => $permission
            ]);

            if ($existing) {
                return $this->json(['code' => 400, 'message' => 'Cette association existe déjà.'], 400);
            }

            // ðŸ’¾ CrÃ©er l'association
            $rolePermission = new RolePermission();
            $rolePermission->setIdRole($role);
            $rolePermission->setIdPermission($permission);

            $this->em->persist($rolePermission);
            $this->em->flush();

            return $this->json([
                'id' => $rolePermission->getId(),
                'message' => 'Association créée avec succès',
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}