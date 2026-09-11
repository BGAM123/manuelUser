<?php

namespace App\Controller\Core\Salle;

use App\Repository\Core\SalleRepository;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Salle")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
        private SalleRepository $salleRepository,
    ) {}

    #[Route('/core/salle/{id}', name: 'app_core_salle_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/salle/{id}',
        summary: 'Supprimer une salle',
        tags: ['Salle'],
        description: "Supprime logiquement une salle (soft delete). La salle est marquée comme supprimée mais conservée dans la base de données.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant de la salle', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Salle supprimÃ©e avec succÃ¨s.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Salle supprimée avec succès'),
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Salle de réunion A'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Salle non trouvée.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Salle non trouvée.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteSalle');

        $salle = $this->salleRepository->find($id);

        if (!$salle || $salle->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Salle non trouvée.'], 404);
        }

        try {
            // Suppression logique
            $this->crudService->patchEntity($salle, ['isDelete' => true]);

            return $this->json([
                'message' => 'Salle supprimée avec succès',
                'id' => $salle->getId(),
                'nom' => $salle->getNom()
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
