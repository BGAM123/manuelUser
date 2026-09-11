<?php

namespace App\Controller\Core\CategorieCorrespondant;

use App\Entity\Core\CategorieCorrespondant;
use OpenApi\Attributes as OA;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CategorieCorrespondant")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    // ðŸ”» SUPPRESSION LOGIQUE
    #[Route('/core/categorie-correspondant/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_categorie_correspondant_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/categorie-correspondant/delete-logical/{id}',
        summary: 'Suppression logique d\'une catégorie de correspondant',
        description: 'Marque une catégorie comme supprimée sans la retirer de la base de données.',
        tags: ['CategorieCorrespondant'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la catégorie',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catégorie marquée comme supprimée.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Catégorie supprimée logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Catégorie non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?CategorieCorrespondant $entity = null): Response
    {
        $user = $this->getUser();
        $this->accessChecker->checker($user, $this->isGranted('ROLE_USER'), 'DeleteCategorieCorrespondant');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Catégorie non trouvée.'], 404);
        }

        // Sauvegarder l'état avant suppression logique
        $categorieData = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'is_delete_before' => $entity->isDelete(),
            'updated_at' => $entity->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];

        $this->functionService->updateBooleanField(CategorieCorrespondant::class, $entity->getId(), 'isDelete', true);

        // Logger la suppression logique
        $this->actionLogger->logDelete(
            'CategorieCorrespondant',
            $entity->getId(),
            'Suppression logique d\'une catégorie de correspondant',
            [
                'categorie' => $categorieData,
                'deletion_type' => 'logical',
                'is_delete' => true
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Catégorie supprimée logiquement avec succès.'], 200);
    }

    // ðŸ” RESTAURATION LOGIQUE
    #[Route('/core/categorie-correspondant/restore-logical/{id<([1-9][0-9]*)>}', name: 'app_core_categorie_correspondant_restore_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/categorie-correspondant/restore-logical/{id}',
        summary: 'Restaure logiquement une catégorie de correspondant',
        description: 'Restaure une catégorie prédemment marquée comme supprimée.',
        tags: ['CategorieCorrespondant'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la catégorie',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catégorie restaurée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Catégorie restaurée logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Catégorie non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function restore(?CategorieCorrespondant $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'RestoreCategorieCorrespondant');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Catégorie non trouvée.'], 404);
        }

        // Sauvegarder l'état avant restauration
        $categorieData = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'is_delete_before' => $entity->isDelete(),
            'updated_at' => $entity->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];

        $this->functionService->updateBooleanField(CategorieCorrespondant::class, $entity->getId(), 'isDelete', false);

        // Logger la restauration
        $this->actionLogger->logUpdate(
            'CategorieCorrespondant',
            $entity->getId(),
            'Restauration logique d\'une catégorie de correspondant',
            [
                'categorie' => $categorieData,
                'action' => 'restore',
                'is_delete' => false
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Catégorie restaurée logiquement avec succès.'], 200);
    }
}
