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
final class MarkNotificationAsReadController extends AbstractController
{
    #[Route('/{id}/read', name: 'app_notification_mark_as_read', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/notifications/{id}/read',
        summary: 'Marquer une notification comme lue',
        description: 'Idempotent : sans effet si la notification est déjà lue.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(response: 200, description: 'Notification marquée comme lue')]
    #[OA\Response(response: 403, description: 'La notification appartient à un autre utilisateur')]
    #[OA\Response(response: 404, description: 'Notification introuvable')]
    public function __invoke(
        int $id,
        #[CurrentUser] User $user,
        NotificationService $notificationService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $notification = $notificationService->getOwnedOrFail($id, $user);
        $notificationService->markAsRead($notification, $user);

        return $apiResponse->success(null, Response::HTTP_OK, 'Notification marquée comme lue.');
    }
}
