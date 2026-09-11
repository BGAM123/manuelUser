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
class MarkAllAsReadController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/notification/mark-all-as-read', name: 'app_core_notification_mark_all_as_read', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/notification/mark-all-as-read',
        summary: 'Marquer toutes les notifications comme lues',
        description: 'Marque toutes les notifications de l\'utilisateur connecté comme lues',
        tags: ['Notification'],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Toutes les notifications ont été marquées comme lues.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Toutes les notifications ont été marquées comme lues'),
                        new OA\Property(property: 'count', type: 'integer', example: 15, description: 'Nombre de notifications marquées comme lues'),
                    ]
                )
            ),
        ]
    )]
    public function markAllAsRead(): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'MarkAllNotificationsAsRead');

        /** @var User $user */
        $user = $this->getUser();

        // Marquer toutes les notifications de l'utilisateur comme lues
        $count = $this->notificationRepository->markAllAsReadByUser($user);

        return $this->json([
            'message' => 'Toutes les notifications ont été marquées comme lues',
            'count' => $count
        ]);
    }
}
