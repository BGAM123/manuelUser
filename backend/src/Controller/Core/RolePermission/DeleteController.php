<?php

namespace App\Controller\Core\RolePermission;

use App\Entity\Core\RolePermission;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "RolePermission")]
class DeleteController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/core/role-permission/{id<([1-9][0-9]*)>}', name: 'app_core_role_permission_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/role-permission/{id}',
        summary: 'Retirer une association rôle-permission',
        tags: ['RolePermission'],
        description: "Supprime définitivement une association entre un rôle et une permission.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Association supprimée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 204),
                        new OA\Property(property: 'message', type: 'string', example: 'Association supprimée avec succès.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Association non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(RolePermission $entity): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteRolePermission');

        $this->em->remove($entity);
        $this->em->flush();

        return $this->json(['code' => 204, 'message' => 'Association supprimée avec succès.'], 204);
    }
}