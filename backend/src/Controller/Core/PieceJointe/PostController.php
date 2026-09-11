<?php

namespace App\Controller\Core\PieceJointe;

use App\Entity\Cour\PieceJointe;
use App\Service\Core\CrudService;
use App\Service\Core\FileService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "PieceJointe")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FileService $fileService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/piece-jointe', name: 'app_core_piece_jointe_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/piece-jointe',
        summary: 'Uploader une pièce jointe',
        tags: ['PieceJointe'],
        description: "Upload un fichier et l'associe à une entité parent.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['file', 'idParent', 'typeParent'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Fichier à uploader'),
                        new OA\Property(property: 'idParent', type: 'integer', example: 25, description: 'ID de l\'entité parent'),
                        new OA\Property(property: 'typeParent', type: 'string', example: 'Reponse', description: 'Type de l\'entité parent (Courrier, Reponse, Transmission, etc.)'),
                        new OA\Property(property: 'nom', type: 'string', example: 'Document officiel.pdf', description: 'Nom personnalisé (optionnel, sinon nom du fichier)'),
                        new OA\Property(property: 'type', type: 'string', example: 'application/pdf', description: 'Type MIME personnalisé (optionnel, sinon détecté automatiquement)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Pièce jointe uploadée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'message', type: 'string', example: 'Pièce jointe uploadée avec succès'),
                        new OA\Property(property: 'chemin', type: 'string', example: '/uploads/pieces/document-abc123.pdf'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PostPieceJointe');

        $data = $request->request->all();
        $this->functionService->validate($data);

        if (empty($data['idParent']) || empty($data['typeParent'])) {
            return $this->json(['code' => 400, 'message' => 'idParent et typeParent sont requis.'], 400);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['code' => 400, 'message' => 'Aucun fichier fourni.'], 400);
        }

        try {
            // ðŸ“‚ Upload du fichier avec FileService
            $uploadDirectory = $this->getParameter('app_uploads_piece_jointe_directory');
            $filePath = $this->fileService->uploadFile($uploadDirectory, $file);

            if (!$filePath) {
                return $this->json(['code' => 500, 'message' => 'Erreur lors de l\'upload du fichier.'], 500);
            }

            // ðŸ’¾ CrÃ©er la PieceJointe
            $pieceJointe = new PieceJointe();
            
            // ðŸ”¹ Nom : utiliser le nom fourni, sinon le nom original du fichier
            $nom = !empty($data['nom']) ? $data['nom'] : $file->getClientOriginalName();
            $pieceJointe->setNom($nom);
            
            // ðŸ”¹ Chemin
            $pieceJointe->setChemin($this->getParameter('app_uploads_piece_jointe') . $filePath);
            
            // ðŸ”¹ Type MIME : utiliser le type fourni, sinon dÃ©tecter automatiquement
            $type = !empty($data['type']) ? $data['type'] : $file->getClientMimeType();
            $pieceJointe->setType($type);
            
            // ðŸ”¹ Parent
            $pieceJointe->setIdParent((int)$data['idParent']);
            $pieceJointe->setTypeParent($data['typeParent']);

            $pieceJointe = $this->crudService->postEntity($pieceJointe, []);

            return $this->json([
                'id' => $pieceJointe->getId(),
                'message' => 'Pièce jointe uploadée avec succès',
                'nom' => $pieceJointe->getNom(),
                'chemin' => $pieceJointe->getChemin(),
                'type' => $pieceJointe->getType(),
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}