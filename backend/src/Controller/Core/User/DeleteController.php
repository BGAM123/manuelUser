<?php

namespace App\Controller\Core\User;

use App\Entity\Core\User;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "User")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/user/{id<([1-9][0-9]*)>}', name: 'app_core_user_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/user/{id}',
        summary: 'Supprime un utilisateur (physique)',
        tags: ['User'],
        description: "Supprime définitivement un utilisateur de la base de données.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de l\'utilisateur',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Utilisateur supprimé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 204),
                        new OA\Property(property: 'message', type: 'string', example: 'Utilisateur supprimé avec succès.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function data(User $entity): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteUser');

        $this->crudService->deleteEntity(User::class, $entity->getId());

        return $this->json(['code' => 204, 'message' => 'Utilisateur supprimé avec succès.'], 204);
    }
}