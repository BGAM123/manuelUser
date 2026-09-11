<?php

namespace App\Controller\Core\Role;

use App\Repository\Core\RoleRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Role")]
class GetController extends AbstractController
{
    public function __construct(
        private RoleRepository $roleRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/role/{id}', name: 'app_core_role_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/core/role/{id}',
        summary: 'Récupérer un rôle par son ID',
        tags: ['Role'],
        description: "Retourne les détails d'un rôle, incluant toutes ses permissions.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rôle récupéré avec succès.'),
            new OA\Response(response: 404, description: 'Rôle non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function show(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'GetRole');

        $role = $this->roleRepository->find($id);

        if (!$role) {
            return $this->json(['code' => 404, 'message' => 'Rôle non trouvé.'], 404);
        }

        $permissions = array_map(fn($rp) => [
            'id' => $rp->getIdPermission()->getId(),
            'nom' => $rp->getIdPermission()->getNom(),
            'description' => $rp->getIdPermission()->getDescription(),
        ], $role->getRolePermissions()->toArray());

        return $this->json([
            'id' => $role->getId(),
            'nom' => $role->getNom(),
            'description' => $role->getDescription(),
            'permissions' => $permissions,
            'createdAt' => $role->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $role->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ], 200);
    }
}