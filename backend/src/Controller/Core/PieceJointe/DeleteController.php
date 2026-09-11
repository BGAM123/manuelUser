<?php

namespace App\Controller\Core\PieceJointe;

use App\Entity\Cour\PieceJointe;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "PieceJointe")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/piece-jointe/{id<([1-9][0-9]*)>}', name: 'app_core_piece_jointe_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/piece-jointe/{id}',
        summary: 'Supprime une pièce jointe (physique)',
        tags: ['PieceJointe'],
        description: "Supprime définitivement une pièce jointe de la base de données.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Pièce jointe supprimée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 204),
                        new OA\Property(property: 'message', type: 'string', example: 'Pièce jointe supprimée avec succès.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Pièce jointe non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(PieceJointe $entity): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeletePieceJointe');

        $this->crudService->deleteEntity(PieceJointe::class, $entity->getId());

        return $this->json(['code' => 204, 'message' => 'Pièce jointe supprimée avec succès.'], 204);
    }
}