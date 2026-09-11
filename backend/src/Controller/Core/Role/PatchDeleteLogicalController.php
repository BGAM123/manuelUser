<?php

namespace App\Controller\Core\Role;

use App\Entity\Core\Role;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Role")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
    ) {}

    // ðŸ”» SUPPRESSION LOGIQUE
    #[Route('/core/role/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_role_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/role/delete-logical/{id}',
        summary: 'Suppression logique d\'un rôle',
        description: 'Marque un rôle comme supprimé sans le retirer de la base de données.',
        tags: ['Role'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Rôle marqué comme supprimé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Rôle supprimé logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Rôle non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?Role $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteLogicalRole');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Rôle non trouvé.'], 404);
        }

        $this->functionService->updateBooleanField(Role::class, $entity->getId(), 'isDelete', true);

        return $this->json(['code' => 200, 'message' => 'Rôle supprimé logiquement avec succès.'], 200);
    }

    // ðŸ” RESTAURATION LOGIQUE
    #[Route('/core/role/restore-logical/{id<([1-9][0-9]*)>}', name: 'app_core_role_restore_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/role/restore-logical/{id}',
        summary: 'Restaure logiquement un rôle',
        description: 'Restaure un rôle précedemment marqué comme supprimé.',
        tags: ['Role'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Rôle restauré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Rôle restauré logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Rôle non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function restore(?Role $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'RestoreLogicalRole');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Rôle non trouvé.'], 404);
        }

        $this->functionService->updateBooleanField(Role::class, $entity->getId(), 'isDelete', false);

        return $this->json(['code' => 200, 'message' => 'Rôle restauré logiquement avec succès.'], 200);
    }
}
