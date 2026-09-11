<?php

namespace App\Controller\Core\Transmission;

use App\Entity\Cour\PieceJointe;
use App\Entity\Cour\Transmission;
use App\Service\Core\CrudService;
use App\Service\Core\FileService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PieceJointeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CrudService $crudService,
        private FileService $fileService,
        private ParameterBagInterface $params
    ) {
    }

    #[Route('/api/transmission/{id}/pieces-jointes', name: 'api_transmission_update_pieces_jointes', methods: ['POST'])]
    #[OA\Post(
        path: '/api/transmission/{id}/pieces-jointes',
        summary: 'Ajouter des pièces jointes à une transmission',
        description: 'Ajoute de nouvelles pièces jointes à une transmission. Les anciennes pièces jointes sont conservées. Permet d\'associer un intitulé à chaque pièce jointe.',
        security: [["bearerAuth" => []]],
        tags: ['Transmission'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la transmission',
                schema: new OA\Schema(type: 'integer', example: 42)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'piecesJointes[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'Fichiers pièces jointes à ajouter - Peut être un ou plusieurs fichiers (PDF, Word, Image, etc.)'
                        ),
                        new OA\Property(
                            property: 'intitulesPiecesJointes',
                            type: 'string',
                            example: '["Bordereau de transmission", "Liste des courriers", "Récépissé"]',
                            description: 'Intitulés des pièces jointes (optionnel). Formats acceptés : 
1) Format JSON (recommandé) : ["Intitulé 1", "Intitulé 2", "Intitulé 3"]
2) Une ligne par intitulé : Intitulé 1\nIntitulé 2\nIntitulé 3
3) Séparé par ||| : "Intitulé 1|||Intitulé 2|||Intitulé 3"
L\'ordre doit correspondre à celui des piecesJointes[]'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pièces jointes ajoutées avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Pièces jointes ajoutées avec succès'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'transmissionId', type: 'integer', example: 42),
                                new OA\Property(property: 'nombrePiecesJointesAjoutees', type: 'integer', example: 3, description: 'Nombre de nouvelles piÃ¨ces jointes ajoutÃ©es'),
                                new OA\Property(
                                    property: 'piecesJointes',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 250),
                                            new OA\Property(property: 'nom', type: 'string', example: 'bordereau.pdf'),
                                            new OA\Property(property: 'intitule', type: 'string', nullable: true, example: 'Bordereau de transmission'),
                                            new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier/pieces/6942c641c1399.pdf'),
                                            new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                                        ]
                                    ),
                                    description: 'Liste des nouvelles pièces jointes créées'
                                ),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Requête invalide',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Aucune pièce jointe fournie')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Transmission non trouvée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Transmission non trouvée')
                    ]
                )
            ),
        ]
    )]
    public function updatePiecesJointes(int $id, Request $request): JsonResponse
    {
        try {
            // RÃ©cupÃ©rer la transmission
            $transmission = $this->entityManager->getRepository(Transmission::class)->find($id);
            
            if (!$transmission) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Transmission non trouvée'
                ], 404);
            }

            // âœ… RÃ©cupÃ©ration des fichiers (mÃªme logique que PostController)
            $pieceJointeFiles = $request->files->get('piecesJointes');
            
            // Si c'est un seul fichier, le mettre dans un tableau pour traitement uniforme
            if ($pieceJointeFiles && !is_array($pieceJointeFiles)) {
                $pieceJointeFiles = [$pieceJointeFiles];
            }
            
            // VÃ©rifier qu'il y a des fichiers
            if (empty($pieceJointeFiles) || !is_array($pieceJointeFiles)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Aucune pièce jointe fournie'
                ], 400);
            }

            // RÃ©cupÃ©rer les intitulÃ©s (optionnel)
            $intitulesInput = $request->request->get('intitulesPiecesJointes', '');
            $intitules = $this->parseIntitules($intitulesInput);

            // Uploader les nouveaux fichiers (sans supprimer les anciens)
            $uploadedPiecesJointes = [];
            foreach ($pieceJointeFiles as $index => $file) {
                if ($file) {
                    $filePath = $this->fileService->uploadFile(
                        $this->params->get('app_uploads_courrier_piece_directory'),
                        $file
                    );

                    if ($filePath) {
                        $intitule = $intitules[$index] ?? null;
                        
                        $uploadedPiecesJointes[] = [
                            'nom' => $file->getClientOriginalName(),
                            'intitule' => $intitule,
                            'chemin' => $this->params->get('app_uploads_courrier_piece') . $filePath,
                            'type' => $file->getClientMimeType()
                        ];
                    }
                }
            }

            // CrÃ©er les nouvelles piÃ¨ces jointes en base de donnÃ©es
            $newPiecesJointes = [];
            foreach ($uploadedPiecesJointes as $pieceData) {
                $pieceJointe = new PieceJointe();
                $pieceJointe->setIdParent($id);
                $pieceJointe->setTypeParent('Transmission');
                $pieceJointe->setNom($pieceData['nom']);
                $pieceJointe->setIntitule($pieceData['intitule']);
                $pieceJointe->setChemin($pieceData['chemin']);
                $pieceJointe->setType($pieceData['type']);
                $pieceJointe->setCreatedAt(new \DateTimeImmutable());
                $pieceJointe->setDelete(false);

                $this->entityManager->persist($pieceJointe);
                $this->entityManager->flush();

                $newPiecesJointes[] = [
                    'id' => $pieceJointe->getId(),
                    'nom' => $pieceJointe->getNom(),
                    'intitule' => $pieceJointe->getIntitule(),
                    'chemin' => $pieceJointe->getChemin(),
                    'type' => $pieceJointe->getType()
                ];
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Pièces jointes ajoutées avec succès',
                'data' => [
                    'transmissionId' => $id,
                    'nombrePiecesJointesAjoutees' => count($newPiecesJointes),
                    'piecesJointes' => $newPiecesJointes
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout des pièces jointes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse les intitulÃ©s depuis diffÃ©rents formats
     * Formats supportÃ©s: JSON array, sÃ©paration par retour Ã  la ligne, par "|||" ou par ";"
     */
    private function parseIntitules(string $input): array
    {
        if (empty($input)) {
            return [];
        }

        // Essayer de parser comme JSON
        $decoded = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values($decoded);
        }

        // Essayer de sÃ©parer par retour Ã  la ligne
        if (strpos($input, "\n") !== false) {
            return array_filter(array_map('trim', explode("\n", $input)));
        }

        // Essayer de sÃ©parer par |||
        if (strpos($input, '|||') !== false) {
            return array_filter(array_map('trim', explode('|||', $input)));
        }

        // Essayer de sÃ©parer par point-virgule
        if (strpos($input, ';') !== false) {
            return array_filter(array_map('trim', explode(';', $input)));
        }

        // Sinon retourner comme valeur unique
        return [trim($input)];
    }
}
