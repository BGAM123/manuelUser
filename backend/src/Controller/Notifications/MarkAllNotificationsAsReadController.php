<?php

namespace App\Controller\Notifications;

use App\Entity\User;
use App\Service\ApiResponseFactory;
use App\Service\NotificationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/notifications')]
#[OA\Tag(name: 'Notifications')]
final class MarkAllNotificationsAsReadController extends AbstractController
{
    #[Route('/read-all', name: 'app_notification_mark_all_as_read', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/notifications/read-all',
        summary: 'Marquer toutes les notifications de l\'utilisateur connecté comme lues'
    )]
    #[OA\Response(response: 200, description: 'Notifications marquées comme lues', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Notifications marquées comme lues.', 'data' => ['updated' => 3]]))]
    public function __invoke(
        #[CurrentUser] User $user,
        NotificationService $notificationService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $updated = $notificationService->markAllAsReadForUser($user);

        return $apiResponse->success(['updated' => $updated], Response::HTTP_OK, 'Notifications marquées comme lues.');
    }
}
