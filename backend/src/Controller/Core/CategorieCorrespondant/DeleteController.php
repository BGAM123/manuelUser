<?php

namespace App\Controller\Core\CategorieCorrespondant;

use App\Entity\Core\CategorieCorrespondant;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CategorieCorrespondant")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/categorie-correspondant/{id<([1-9][0-9]*)>}', name: 'app_core_categorie_correspondant_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/categorie-correspondant/{id}',
        summary: 'Suppression physique d\'une catégory de correspondant',
        description: "Supprime définitivement une catégorie de correspondant de la base de données (⚠️ à utiliser avec prudence).",
        tags: ['CategorieCorrespondant'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la catégorie à supprimer',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Suppression effectuée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 204),
                        new OA\Property(property: 'message', type: 'string', example: 'Catégorie supprimée avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Catégorie non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?CategorieCorrespondant $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteCategorieCorrespondant');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Catégorie non trouvée.'], 404);
        }

        // Sauvegarder les données avant suppression
        $deletedData = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'is_delete' => $entity->isDelete(),
            'created_at' => $entity->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $entity->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];

        // âš™ï¸ Suppression dÃ©finitive
        $this->crudService->deleteEntity(CategorieCorrespondant::class, $entity->getId());

        // Logger la suppression physique
        $this->actionLogger->logDelete(
            'CategorieCorrespondant',
            $deletedData['id'],
            'Suppression physique d\'une catégorie de correspondant',
            [
                'deleted_data' => $deletedData,
                'deletion_type' => 'physical'
            ]
        );

        return $this->json(['code' => 204, 'message' => 'Catégorie supprimée avec succès.'], 204);
    }
}
