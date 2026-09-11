<?php

namespace App\Controller\Core\Notification;

use App\Repository\Core\NotificationRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: "Notification")]
class DeleteAllController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/notification/delete-all', name: 'app_core_notification_delete_all', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/notification/delete-all',
        summary: 'Supprimer toutes les notifications',
        description: 'Supprime TOUTES les notifications de la base de données. ATTENTION : Cette action est IRREVERSIBLE et ne peut pas être annulée !',
        tags: ['Notification'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'confirm',
                in: 'query',
                required: true,
                description: 'Confirmation de suppression. Doit être exactement "DELETE_ALL_NOTIFICATIONS"',
                schema: new OA\Schema(type: 'string', example: 'DELETE_ALL_NOTIFICATIONS')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Toutes les notifications ont été supprimées avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Toutes les notifications ont été supprimées avec succès'),
                        new OA\Property(property: 'count', type: 'integer', example: 250, description: 'Nombre de notifications supprimées'),
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
                        new OA\Property(property: 'message', type: 'string', example: 'Confirmation requise. Utilisez le paramètre confirm=DELETE_ALL_NOTIFICATIONS'),
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
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteAllNotification');

        // ðŸ›¡ï¸ SÃ©curitÃ© : VÃ©rification de la confirmation
        $confirmation = $request->query->get('confirm');
        
        if ($confirmation !== 'DELETE_ALL_NOTIFICATIONS') {
            return $this->json([
                'code' => 400,
                'message' => 'Confirmation requise. Utilisez le paramètre confirm=DELETE_ALL_NOTIFICATIONS',
                'hint' => 'Cette action est IRREVERSIBLE. Assurez-vous de vouloir supprimer TOUTES les notifications.'
            ], 400);
        }

        try {
            // ðŸ“Š Compter le nombre de notifications avant suppression
            $totalNotifications = $this->notificationRepository->count([]);

            if ($totalNotifications === 0) {
                return $this->json([
                    'code' => 200,
                    'message' => 'Aucune notification à supprimer',
                    'count' => 0
                ], 200);
            }

            // ðŸ—‘ï¸ Suppression en masse sans logging individuel
            // Utilisation de DQL pour supprimer directement en base de donnÃ©es
            $query = $this->entityManager->createQuery(
                'DELETE FROM App\Entity\Core\Notification n'
            );
            
            $deletedCount = $query->execute();

            // Log simple pour l'administrateur
            $this->logger?->warning('Suppression massive de notifications', [
                'admin' => $this->getUser()?->getUserIdentifier(),
                'count' => $deletedCount,
                'timestamp' => new \DateTime()
            ]);

            return $this->json([
                'code' => 200,
                'message' => 'Toutes les notifications ont été supprimées avec succès',
                'count' => $deletedCount,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ], 200);

        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la suppression massive de notifications', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la suppression des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
