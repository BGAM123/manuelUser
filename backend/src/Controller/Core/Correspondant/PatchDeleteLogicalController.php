<?php

namespace App\Controller\Core\Correspondant;

use App\Entity\Core\Correspondant;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Correspondant")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    // ðŸ”» SUPPRESSION LOGIQUE
    #[Route('/core/correspondant/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_correspondant_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/correspondant/delete-logical/{id}',
        summary: 'Suppression logique d\'un correspondant',
        description: 'Marque un correspondant comme supprimé sans le retirer de la base de données.',
        tags: ['Correspondant'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du correspondant à  marquer comme supprimé',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Correspondant marqué comme supprimé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Correspondant supprimé logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Correspondant non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?Correspondant $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'DeleteLogicalCorrespondant');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Correspondant non trouvÃ©.'], 404);
        }

        $this->functionService->updateBooleanField(Correspondant::class, $entity->getId(), 'isDelete', true);

        // âœ… LOG ASYNCHRONE - Suppression logique d'un correspondant
        $this->actionLogger->logDelete(
            'Correspondant',
            $entity->getId(),
            'Suppression logique du correspondant',
            [
                'correspondant' => [
                    'id' => $entity->getId(),
                    'nom' => $entity->getNom(),
                    'email' => $entity->getEmail(),
                    'type' => $entity->getType()
                ]
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Correspondant supprimé logiquement avec succès.'], 200);
    }

    // ðŸ” RESTAURATION LOGIQUE
    #[Route('/core/correspondant/restore-logical/{id<([1-9][0-9]*)>}', name: 'app_core_correspondant_restore_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/correspondant/restore-logical/{id}',
        summary: 'Restaure logiquement un correspondant',
        description: 'Restaure un correspondant précédemment marquéd comme supprimé.',
        tags: ['Correspondant'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du correspondant à  restaurer',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Correspondant restauré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Correspondant restauré logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Correspondant non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function restore(?Correspondant $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'RestoreCorrespondant');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Correspondant non trouvé.'], 404);
        }

        $this->functionService->updateBooleanField(Correspondant::class, $entity->getId(), 'isDelete', false);

        // âœ… LOG ASYNCHRONE - Restauration logique d'un correspondant
        $this->actionLogger->logAction(
            'restore',
            'Restauration logique du correspondant',
            'Correspondant',
            $entity->getId(),
            [
                'correspondant' => [
                    'id' => $entity->getId(),
                    'nom' => $entity->getNom(),
                    'email' => $entity->getEmail(),
                    'type' => $entity->getType()
                ]
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Correspondant restauré logiquement avec succès.'], 200);
    }
}
