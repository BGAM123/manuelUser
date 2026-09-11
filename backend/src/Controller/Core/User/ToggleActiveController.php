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
class ToggleActiveController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/user/toggle-active/{id<([1-9][0-9]*)>}', name: 'app_core_user_toggle_active', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/user/toggle-active/{id}',
        summary: 'Activer/Désactiver un compte utilisateur',
        description: 'Bascule le statut isActive d\'un utilisateur (actif ou inactif).',
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
                description: 'Statut de l\'utilisateur modifié avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Utilisateur activé/désactivé avec succès.'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true)
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function toggle(?User $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'ToggleActiveUser');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Utilisateur non trouvé.'], 404);
        }

        // ðŸ”„ Basculer isActive
        $newStatus = !$entity->isActive();
        $this->functionService->updateBooleanField(User::class, $entity->getId(), 'isActive', $newStatus);

        return $this->json([
            'code' => 200,
            'message' => $newStatus ? 'Utilisateur activé avec succès.' : 'Utilisateur désactivé avec succès.',
            'isActive' => $newStatus
        ], 200);
    }
}
