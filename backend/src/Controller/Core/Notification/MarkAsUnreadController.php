<?php

namespace App\Controller\Core\Notification;

use App\Entity\Core\User;
use App\Repository\Core\NotificationRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Notification")]
class MarkAsUnreadController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/notification/{id}/mark-as-unread', name: 'app_core_notification_mark_as_unread', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/notification/{id}/mark-as-unread',
        summary: 'Marquer une notification comme non lue',
        description: 'Marque une notification spécifique comme non lue',
        tags: ['Notification'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la notification',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification marquée comme non lue avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Notification marquée comme non lue'),
                        new OA\Property(property: 'notification', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Notification non trouvée.'),
            new OA\Response(response: 403, description: 'Accès refusé.'),
        ]
    )]
    public function markAsUnread(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'MarkNotificationAsUnread');

        /** @var User $user */
        $user = $this->getUser();

        $notification = $this->notificationRepository->find($id);

        if (!$notification) {
            return $this->json(['error' => 'Notification non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur a accès à cette notification
        $hasAccess = $notification->getUser()->getId() === $user->getId();
        
        if (!$hasAccess && $user->getIdService() && $notification->getService()) {
            $hasAccess = $notification->getService()->getId() === $user->getIdService()->getId();
        }

        if (!$hasAccess) {
            return $this->json(['error' => 'Accès refusé à cette notification'], Response::HTTP_FORBIDDEN);
        }

        // Marquer comme non lue
        $notification->markAsUnread();
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Notification marquée comme non lue',
            'notification' => [
                'id' => $notification->getId(),
                'isRead' => $notification->isRead(),
                'readAt' => $notification->getReadAt()?->format('c'),
            ]
        ]);
    }
}
