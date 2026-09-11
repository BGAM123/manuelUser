<?php

namespace App\Controller\Core\TypeCourrier;

use App\Entity\Core\TypeCourrier;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeCourrier")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    // ðŸ”» SUPPRESSION LOGIQUE
    #[Route('/core/type-courrier/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_type_courrier_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/type-courrier/delete-logical/{id}',
        summary: 'Suppression logique d\'un type de courrier',
        description: 'Marque un type de courrier comme supprimé sans le retirer de la base de données.',
        tags: ['TypeCourrier'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du type de courrier',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Type de courrier marqué comme supprimé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Type de courrier supprimé logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Type de courrier non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?TypeCourrier $entity = null): Response
    {
        $user = $this->getUser();
        $this->accessChecker->checker($user, $this->isGranted('ROLE_ADMIN'), 'DeleteTypeCourrier');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Type de courrier non trouvé.'], 404);
        }

        // Capture des données avant suppression logique
        $deletedData = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'type' => $entity->getType(),
            'classe_courrier' => $entity->getClasseCourrier(),
            'categories' => $entity->getCategories()->map(fn($cat) => [
                'id' => $cat->getId(),
                'nom' => $cat->getNom(),
            ])->toArray(),
            'parent' => $entity->getIdTypeParent() ? [
                'id' => $entity->getIdTypeParent()->getId(),
                'nom' => $entity->getIdTypeParent()->getNom(),
            ] : null,
            'type_operation' => 'logical'
        ];

        $this->functionService->updateBooleanField(TypeCourrier::class, $entity->getId(), 'isDelete', true);

        // Log de la suppression logique
        $this->actionLogger->logDelete(
            'TypeCourrier',
            $entity->getId(),
            'Suppression logique d\'un type de courrier',
            $deletedData
        );

        return $this->json(['code' => 200, 'message' => 'Type de courrier supprimé logiquement avec succès.'], 200);
    }

    // ðŸ” RESTAURATION LOGIQUE
    #[Route('/core/type-courrier/restore-logical/{id<([1-9][0-9]*)>}', name: 'app_core_type_courrier_restore_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/type-courrier/restore-logical/{id}',
        summary: 'Restaure logiquement un type de courrier',
        description: 'Restaure un type de courrier précédemment marqué comme supprimé.',
        tags: ['TypeCourrier'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du type de courrier',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Type de courrier restauré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Type de courrier restauré logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Type de courrier non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function restore(?TypeCourrier $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'RestoreTypeCourrier');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Type de courrier non trouvé.'], 404);
        }

        // Capture des données avant restauration
        $restoredData = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'type' => $entity->getType(),
            'classe_courrier' => $entity->getClasseCourrier(),
            'categories' => $entity->getCategories()->map(fn($cat) => [
                'id' => $cat->getId(),
                'nom' => $cat->getNom(),
            ])->toArray(),
            'parent' => $entity->getIdTypeParent() ? [
                'id' => $entity->getIdTypeParent()->getId(),
                'nom' => $entity->getIdTypeParent()->getNom(),
            ] : null,
            'type_operation' => 'restore'
        ];

        $this->functionService->updateBooleanField(TypeCourrier::class, $entity->getId(), 'isDelete', false);

        // Log de la restauration
        $this->actionLogger->logUpdate(
            'TypeCourrier',
            $entity->getId(),
            'Restauration logique d\'un type de courrier',
            $restoredData
        );

        return $this->json(['code' => 200, 'message' => 'Type de courrier restauré logiquement avec succès.'], 200);
    }
}
