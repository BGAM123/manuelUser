<?php

namespace App\Controller\Core\TypeCourrier;

use App\Entity\Core\TypeCourrier;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeCourrier")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker
    ) {}

    #[Route('/core/type-courrier/{id<([1-9][0-9]*)>}', name: 'app_core_type_courrier_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/type-courrier/{id}',
        summary: 'Supprimer un type de courrier',
        description: 'Supprime définitivement un type de courrier de la base de données.',
        tags: ['TypeCourrier'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du type de courrier à supprimer',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Suppression réussie.'),
            new OA\Response(response: 404, description: 'Type de courrier non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?TypeCourrier $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteTypeCourrier');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Type de courrier non trouvé.'], 404);
        }

        try {
            $this->crudService->deleteEntity(TypeCourrier::class, $entity->getId());
            return $this->json(['code' => 204, 'message' => 'Type de courrier supprimé avec succès.'], 204);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
            ], 500);
        }
    }
}
