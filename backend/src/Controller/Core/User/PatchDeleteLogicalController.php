<?php

namespace App\Controller\Core\User;

use App\Entity\Core\User;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "User")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
    ) {}

    // ðŸ”» SUPPRESSION LOGIQUE
    #[Route('/core/user/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_user_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/user/delete-logical/{id}',
        summary: 'Suppression logique d\'un utilisateur',
        description: 'Marque un utilisateur comme supprimé sans le retirer de la base de données.',
        tags: ['User'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de l\'utilisateur',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilisateur marqué comme supprimé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Utilisateur supprimé logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?User $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteLogicalUser');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Utilisateur non trouvÃ©.'], 404);
        }

        $this->functionService->updateBooleanField(User::class, $entity->getId(), 'isDelete', true);
    
    	// ✅ NOUVEAU : Désactiver aussi l'utilisateur
        $this->functionService->updateBooleanField(User::class, $entity->getId(), 'isActive', false);

        return $this->json(['code' => 200, 'message' => 'Utilisateur supprimé logiquement avec succès.'], 200);
    }

    // ðŸ” RESTAURATION LOGIQUE
    #[Route('/core/user/restore-logical/{id<([1-9][0-9]*)>}', name: 'app_core_user_restore_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/user/restore-logical/{id}',
        summary: 'Restaure logiquement un utilisateur',
        description: 'Restaure un utilisateur précédemment marquéd comme supprimé.',
        tags: ['User'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de l\'utilisateur',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilisateur restaurÃ© avec succÃ¨s.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Utilisateur restauré logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function restore(?User $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'RestoreLogicalUser');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Utilisateur non trouvé.'], 404);
        }

        $this->functionService->updateBooleanField(User::class, $entity->getId(), 'isDelete', false);

        return $this->json(['code' => 200, 'message' => 'Utilisateur restauré logiquement avec succès.'], 200);
    }
}
