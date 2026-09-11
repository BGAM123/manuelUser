<?php

namespace App\Controller\Core\Role;

use App\Entity\Core\Role;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Role")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/role/{id<([1-9][0-9]*)>}', name: 'app_core_role_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/role/{id}',
        summary: 'Supprime un rôle (physique)',
        tags: ['Role'],
        description: "Supprime définitivement un rôle de la base de données.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Rôle supprimé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 204),
                        new OA\Property(property: 'message', type: 'string', example: 'Rôle supprimé avec succès.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Rôle non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(Role $entity): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteRole');

        $this->crudService->deleteEntity(Role::class, $entity->getId());

        return $this->json(['code' => 204, 'message' => 'Rôle supprimé avec succès.'], 204);
    }
}