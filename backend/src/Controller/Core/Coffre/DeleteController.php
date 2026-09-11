<?php

namespace App\Controller\Core\Coffre;

use App\Repository\Core\CoffreRepository;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Coffre")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
        private CoffreRepository $coffreRepository,
    ) {}

    #[Route('/core/coffre/{id}', name: 'app_core_coffre_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/coffre/{id}',
        summary: 'Supprimer un coffre',
        tags: ['Coffre'],
        description: "Supprime logiquement un coffre (soft delete). Le coffre est marqué comme supprimé mais conservé dans la base de données.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant du coffre', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coffre supprimé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Coffre supprimé avec succès'),
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Coffre A1'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Coffre non trouvé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Coffre non trouvé.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteCoffre');

        $coffre = $this->coffreRepository->find($id);

        if (!$coffre || $coffre->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Coffre non trouvé.'], 404);
        }

        try {
            // Suppression logique
            $this->crudService->patchEntity($coffre, ['isDelete' => true]);

            return $this->json([
                'message' => 'Coffre supprimé avec succès',
                'id' => $coffre->getId(),
                'nom' => $coffre->getNom()
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
