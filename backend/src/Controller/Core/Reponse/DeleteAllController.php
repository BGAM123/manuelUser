<?php

namespace App\Controller\Core\Reponse;

use App\Repository\Cour\ReponseRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: "Reponse")]
class DeleteAllController extends AbstractController
{
    public function __construct(
        private ReponseRepository $reponseRepository,
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/reponse/delete-all', name: 'app_core_reponse_delete_all', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/reponse/delete-all',
        summary: 'Supprimer toutes les réponses',
        description: 'Supprime TOUTES les réponses de la base de données.  ATTENTION : Cette action est IRREVERSIBLE et ne peut pas être annulée !',
        tags: ['Reponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'confirm',
                in: 'query',
                required: true,
                description: 'Confirmation de suppression. Doit être exactement "DELETE_ALL_REPONSES"',
                schema: new OA\Schema(type: 'string', example: 'DELETE_ALL_REPONSES')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Toutes les réponses ont été supprimées avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Toutes les réponses ont été supprimées avec succès'),
                        new OA\Property(property: 'count', type: 'integer', example: 75, description: 'Nombre de réponses supprimées'),
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
                        new OA\Property(property: 'message', type: 'string', example: 'Confirmation requise. Utilisez le paramètre confirm=DELETE_ALL_REPONSES'),
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
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteAllReponse');

        // ðŸ›¡ï¸ SÃ©curitÃ© : VÃ©rification de la confirmation
        $confirmation = $request->query->get('confirm');
        
        if ($confirmation !== 'DELETE_ALL_REPONSES') {
            return $this->json([
                'code' => 400,
                'message' => 'Confirmation requise. Utilisez le paramètre confirm=DELETE_ALL_REPONSES',
                'hint' => 'Cette action est IRREVERSIBLE. Assurez-vous de vouloir supprimer TOUTES les réponses.'
            ], 400);
        }

        try {
            // ðŸ“Š Compter le nombre de rÃ©ponses avant suppression
            $totalReponses = $this->reponseRepository->count([]);

            if ($totalReponses === 0) {
                return $this->json([
                    'code' => 200,
                    'message' => 'Aucune réponse à supprimer',
                    'count' => 0
                ], 200);
            }

            // ðŸ—‘ï¸ Suppression en masse sans logging individuel
            // Utilisation de DQL pour supprimer directement en base de donnÃ©es
            $query = $this->entityManager->createQuery(
                'DELETE FROM App\Entity\Cour\Reponse r'
            );
            
            $deletedCount = $query->execute();

            // Log simple pour l'administrateur
            $this->logger?->warning('Suppression massive de réponses', [
                'admin' => $this->getUser()?->getUserIdentifier(),
                'count' => $deletedCount,
                'timestamp' => new \DateTime()
            ]);

            return $this->json([
                'code' => 200,
                'message' => 'Toutes les réponses ont été supprimées avec succès',
                'count' => $deletedCount,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ], 200);

        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la suppression massive de réponses', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la suppression des réponses',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
