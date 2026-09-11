<?php

namespace App\Controller\Core\PieceJointe;

use App\Repository\Cour\PieceJointeRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "PieceJointe")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private PieceJointeRepository $pieceJointeRepository,
    ) {}

    #[Route('/core/piece-jointe/{id<([1-9][0-9]*)>}', name: 'app_core_piece_jointe_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/piece-jointe/{id}',
        summary: 'Met à jour une pièce jointe existante',
        tags: ['PieceJointe'],
        description: "Met à jour les métadonnées d'une pièce jointe (nom, type, parent). Ne permet pas de changer le fichier lui-même.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Document officiel renommé.pdf', description: 'Nouveau nom'),
                    new OA\Property(property: 'intitule', type: 'string', example: 'Rapport financier 2025', description: 'Nouvel intitulé/titre de la pièce jointe'),
                    new OA\Property(property: 'type', type: 'string', example: 'application/pdf', description: 'Nouveau type MIME'),
                    new OA\Property(property: 'idParent', type: 'integer', example: 30, description: 'Nouveau parent ID (pour déplacer vers une autre entité)'),
                    new OA\Property(property: 'typeParent', type: 'string', example: 'Courrier', description: 'Nouveau type parent (pour déplacer vers une autre entité)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pièce jointe mise à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Pièce jointe mise à jour avec succès'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Pièce jointe non trouvée.'),
            new OA\Response(response: 400, description: 'Erreur de validation.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PatchPieceJointe');

        $pieceJointe = $this->pieceJointeRepository->find($id);

        if (!$pieceJointe) {
            return $this->json(['code' => 404, 'message' => 'Pièce jointe non trouvée.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->functionService->validate($data);
        
        // ðŸ”¹ On ne peut PAS modifier le chemin (le fichier lui-mÃªme)
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'chemin']);

        try {
            // ðŸ’¾ Mise Ã  jour
            $this->crudService->patchEntity($pieceJointe, $data);

            return $this->json([
                'message' => 'Pièce jointe mise à jour avec succès',
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}