<?php

namespace App\Controller\Core\PieceJointe;

use App\Repository\Cour\PieceJointeRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "PieceJointe")]
class GetController extends AbstractController
{
    public function __construct(
        private PieceJointeRepository $pieceJointeRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/piece-jointe/{id}', name: 'app_core_piece_jointe_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/core/piece-jointe/{id}',
        summary: 'Récupérer une pièce jointe par son ID',
        tags: ['PieceJointe'],
        description: "Retourne les détails d'une pièce jointe.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pièce jointe récupérée avec succès.'),
            new OA\Response(response: 404, description: 'Pièce jointe non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function show(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetPieceJointe');

        $pieceJointe = $this->pieceJointeRepository->find($id);

        if (!$pieceJointe) {
            return $this->json(['code' => 404, 'message' => 'Pièce jointe non trouvée.'], 404);
        }

        return $this->json([
            'id' => $pieceJointe->getId(),
            'nom' => $pieceJointe->getNom(),
            'chemin' => $pieceJointe->getChemin(),
            'type' => $pieceJointe->getType(),
            'idParent' => $pieceJointe->getIdParent(),
            'typeParent' => $pieceJointe->getTypeParent(),
            'createdAt' => $pieceJointe->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $pieceJointe->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ], 200);
    }
}