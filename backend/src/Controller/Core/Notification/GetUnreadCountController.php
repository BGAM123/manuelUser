<?php

namespace App\Controller\Core\Notification;

use App\Entity\Core\User;
use App\Repository\Core\NotificationRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Notification")]
class GetUnreadCountController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/notification/unread-count', name: 'app_core_notification_unread_count', methods: ['GET'])]
    #[OA\Get(
        path: '/core/notification/unread-count',
        summary: 'Obtenir le nombre de notifications non lues',
        description: 'Retourne le nombre total de notifications non lues pour l\'utilisateur connecté',
        tags: ['Notification'],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Nombre de notifications non lues récupéré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'count', type: 'integer', example: 12),
                    ]
                )
            ),
        ]
    )]
    public function getUnreadCount(): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetUnreadNotificationCount');

        /** @var User $user */
        $user = $this->getUser();

        $count = $this->notificationRepository->countUnreadByUserOrService($user);

        return $this->json([
            'count' => $count
        ]);
    }
}
