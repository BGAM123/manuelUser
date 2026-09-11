<?php

namespace App\Controller\Core\PieceJointe;

use App\Repository\Cour\PieceJointeRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "PieceJointe")]
class GetByParentController extends AbstractController
{
    public function __construct(
        private PieceJointeRepository $pieceJointeRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/piece-jointe/by-parent/{typeParent}/{idParent<([1-9][0-9]*)>}', name: 'app_core_piece_jointe_get_by_parent', methods: ['GET'])]
    #[OA\Get(
        path: '/core/piece-jointe/by-parent/{typeParent}/{idParent}',
        summary: 'Récupérer toutes les pièces jointes d\'une entité',
        tags: ['PieceJointe'],
        description: "Retourne toutes les pièces jointes associées à une entité (ex: Reponse, Courrier, etc.).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'typeParent', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'Reponse')),
            new OA\Parameter(name: 'idParent', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 25)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pièces jointes récupérées avec succès.'),
            new OA\Response(response: 404, description: 'Aucune pièce jointe trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getByParent(string $typeParent, int $idParent): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetPieceJointesByParent');

        $piecesJointes = $this->pieceJointeRepository->findBy([
            'typeParent' => $typeParent,
            'idParent' => $idParent,
            'isDelete' => false
        ], ['createdAt' => 'DESC']);

        if (empty($piecesJointes)) {
            return $this->json([
                'code' => 404,
                'message' => 'Aucune pièce jointe trouvée pour cette entité.',
                'typeParent' => $typeParent,
                'idParent' => $idParent,
                'total' => 0,
                'piecesJointes' => []
            ], 404);
        }

        $data = array_map(fn($pj) => [
            'id' => $pj->getId(),
            'nom' => $pj->getNom(),
            'chemin' => $pj->getChemin(),
            'type' => $pj->getType(),
            'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $piecesJointes);

        return $this->json([
            'typeParent' => $typeParent,
            'idParent' => $idParent,
            'total' => count($data),
            'piecesJointes' => $data
        ], 200);
    }
}