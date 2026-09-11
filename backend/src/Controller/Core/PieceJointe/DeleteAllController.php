<?php

namespace App\Controller\Core\PieceJointe;

use App\Repository\Cour\PieceJointeRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: "PieceJointe")]
class DeleteAllController extends AbstractController
{
    public function __construct(
        private PieceJointeRepository $pieceJointeRepository,
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/piece-jointe/delete-all', name: 'app_core_piece_jointe_delete_all', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/piece-jointe/delete-all',
        summary: 'Supprimer toutes les pièces jointes',
        description: 'Supprime TOUTES les pièces jointes de la base de données. ⚠️ ATTENTION : Cette action est IRRÉVERSIBLE et ne peut pas être annulée ! Note : Les fichiers physiques sur le disque ne sont PAS supprimés automatiquement.',
        tags: ['PieceJointe'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'confirm',
                in: 'query',
                required: true,
                description: 'Confirmation de suppression. Doit être exactement "DELETE_ALL_PIECES_JOINTES"',
                schema: new OA\Schema(type: 'string', example: 'DELETE_ALL_PIECES_JOINTES')
            ),
            new OA\Parameter(
                name: 'deleteFiles',
                in: 'query',
                required: false,
                description: 'Supprimer aussi les fichiers physiques (true/false). Par défaut: false',
                schema: new OA\Schema(type: 'boolean', example: false)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Toutes les pièces jointes ont été supprimées avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Toutes les pièces jointes ont été supprimées avec succès'),
                        new OA\Property(property: 'count', type: 'integer', example: 320, description: 'Nombre de pièces jointes supprimées de la base'),
                        new OA\Property(property: 'filesDeleted', type: 'integer', example: 310, description: 'Nombre de fichiers physiques supprimés (si deleteFiles=true)'),
                        new OA\Property(property: 'filesFailed', type: 'integer', example: 10, description: 'Nombre de fichiers qui n\'ont pas pu être supprimés'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Confirmation manquante ou invalide.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Confirmation requise. Utilisez le paramètre confirm=DELETE_ALL_PIECES_JOINTES'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.'),
            new OA\Response(response: 403, description: 'Permissions insuffisantes. Seuls les administrateurs peuvent effectuer cette action.')
        ]
    )]
    public function deleteAll(Request $request): Response
    {
        // ðŸ” VÃ©rification des droits d'accÃ¨s - ADMIN UNIQUEMENT
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteAllPieceJointe');

        // ðŸ›¡ï¸ SÃ©curitÃ© : VÃ©rification de la confirmation
        $confirmation = $request->query->get('confirm');
        
        if ($confirmation !== 'DELETE_ALL_PIECES_JOINTES') {
            return $this->json([
                'code' => 400,
                'message' => 'Confirmation requise. Utilisez le paramètre confirm=DELETE_ALL_PIECES_JOINTES',
                'hint' => 'Cette action est IRRÉVERSIBLE. Assurez-vous de vouloir supprimer TOUTES les pièces jointes.'
            ], 400);
        }

        // ðŸ—‚ï¸ Option pour supprimer aussi les fichiers physiques
        $deleteFiles = filter_var($request->query->get('deleteFiles', 'false'), FILTER_VALIDATE_BOOLEAN);

        try {
            // ðŸ“Š Compter le nombre de piÃ¨ces jointes avant suppression
            $totalPiecesJointes = $this->pieceJointeRepository->count([]);

            if ($totalPiecesJointes === 0) {
                return $this->json([
                    'code' => 200,
                    'message' => 'Aucune pièce jointe à supprimer',
                    'count' => 0,
                    'filesDeleted' => 0,
                    'filesFailed' => 0
                ], 200);
            }

            $filesDeleted = 0;
            $filesFailed = 0;

            // ðŸ—‚ï¸ Si demandÃ©, supprimer les fichiers physiques AVANT la suppression en base
            if ($deleteFiles) {
                $this->logger?->info('Suppression des fichiers physiques demandée', [
                    'total_pieces' => $totalPiecesJointes
                ]);

                // RÃ©cupÃ©rer toutes les piÃ¨ces jointes avec leurs chemins
                $piecesJointes = $this->pieceJointeRepository->findAll();
                
                foreach ($piecesJointes as $piece) {
                    $chemin = $piece->getChemin();
                    
                    if ($chemin && file_exists($chemin)) {
                        try {
                            if (unlink($chemin)) {
                                $filesDeleted++;
                            } else {
                                $filesFailed++;
                                $this->logger?->warning('Impossible de supprimer le fichier', [
                                    'piece_id' => $piece->getId(),
                                    'chemin' => $chemin
                                ]);
                            }
                        } catch (\Exception $fileException) {
                            $filesFailed++;
                            $this->logger?->error('Erreur lors de la suppression du fichier', [
                                'piece_id' => $piece->getId(),
                                'chemin' => $chemin,
                                'exception' => $fileException->getMessage()
                            ]);
                        }
                    } else {
                        // Fichier n'existe pas ou chemin vide
                        if ($chemin) {
                            $this->logger?->debug('Fichier introuvable', [
                                'piece_id' => $piece->getId(),
                                'chemin' => $chemin
                            ]);
                        }
                    }
                }
            }

            // ðŸ—‘ï¸ Suppression en masse de la base de donnÃ©es sans logging individuel
            // Utilisation de DQL pour supprimer directement en base de donnÃ©es
            $query = $this->entityManager->createQuery(
                'DELETE FROM App\Entity\Cour\PieceJointe p'
            );
            
            $deletedCount = $query->execute();

            // Log simple pour l'administrateur
            $this->logger?->warning('Suppression massive de piéces jointes', [
                'admin' => $this->getUser()?->getUserIdentifier(),
                'count_database' => $deletedCount,
                'files_deleted' => $filesDeleted,
                'files_failed' => $filesFailed,
                'delete_files_option' => $deleteFiles,
                'timestamp' => new \DateTime()
            ]);

            return $this->json([
                'code' => 200,
                'message' => 'Toutes les pièces jointes ont été supprimées avec succès',
                'count' => $deletedCount,
                'filesDeleted' => $filesDeleted,
                'filesFailed' => $filesFailed,
                'deleteFilesEnabled' => $deleteFiles,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ], 200);

        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la suppression massive de piéces jointes', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la suppression des piéces jointes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
