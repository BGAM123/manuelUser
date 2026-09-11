<?php

namespace App\Controller\Core\PieceJointe;

use App\Entity\Cour\PieceJointe;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "PieceJointe")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
    ) {}

    // ðŸ”» SUPPRESSION LOGIQUE
    #[Route('/core/piece-jointe/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_piece_jointe_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/piece-jointe/delete-logical/{id}',
        summary: 'Suppression logique d\'une pièce jointe',
        description: 'Marque une pièce jointe comme supprimée sans la retirer de la base de données.',
        tags: ['PieceJointe'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pièce jointe marquée comme supprimée.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Pièce jointe supprimée logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Pièce jointe non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?PieceJointe $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'DeleteLogicalPieceJointe');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Pièce jointe non trouvée.'], 404);
        }

        $this->functionService->updateBooleanField(PieceJointe::class, $entity->getId(), 'isDelete', true);

        return $this->json(['code' => 200, 'message' => 'Pièce jointe supprimée logiquement avec succès.'], 200);
    }

    // ðŸ” RESTAURATION LOGIQUE
    #[Route('/core/piece-jointe/restore-logical/{id<([1-9][0-9]*)>}', name: 'app_core_piece_jointe_restore_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/piece-jointe/restore-logical/{id}',
        summary: 'Restaure logiquement une pièce jointe',
        description: 'Restaure une pièce jointe précédemment marquée comme supprimée.',
        tags: ['PieceJointe'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pièce jointe restaurée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Pièce jointe restaurée logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Pièce jointe non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function restore(?PieceJointe $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'RestoreLogicalPieceJointe');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Pièce jointe non trouvée.'], 404);
        }

        $this->functionService->updateBooleanField(PieceJointe::class, $entity->getId(), 'isDelete', false);

        return $this->json(['code' => 200, 'message' => 'Pièce jointe restaurée logiquement avec succès.'], 200);
    }
}
