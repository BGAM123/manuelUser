<?php

namespace App\Controller\Core\Permission;

use App\Repository\Core\PermissionRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Permission")]
class GetController extends AbstractController
{
    public function __construct(
        private PermissionRepository $permissionRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/permission/{id}', name: 'app_core_permission_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/core/permission/{id}',
        summary: 'Récupérer une permission par son ID',
        tags: ['Permission'],
        description: "Retourne les détails d'une permission.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permission récupérée avec succès.'),
            new OA\Response(response: 404, description: 'Permission non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function show(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'GetPermission');

        $permission = $this->permissionRepository->find($id);

        if (!$permission) {
            return $this->json(['code' => 404, 'message' => 'Permission non trouvée.'], 404);
        }

        return $this->json([
            'id' => $permission->getId(),
            'nom' => $permission->getNom(),
            'description' => $permission->getDescription(),
        ], 200);
    }
}