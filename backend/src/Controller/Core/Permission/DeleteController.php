<?php

namespace App\Controller\Core\Permission;

use App\Entity\Core\Permission;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Permission")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/permission/{id<([1-9][0-9]*)>}', name: 'app_core_permission_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/permission/{id}',
        summary: 'Supprime une permission (physique)',
        tags: ['Permission'],
        description: "Supprime définitivement une permission de la base de donnée.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Permission supprimée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 204),
                        new OA\Property(property: 'message', type: 'string', example: 'Permission supprimée avec succès.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Permission non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(Permission $entity): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeletePermission');

        $this->crudService->deleteEntity(Permission::class, $entity->getId());

        return $this->json(['code' => 204, 'message' => 'Permission supprimée avec succès.'], 204);
    }
}