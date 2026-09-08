<?php

namespace App\Controller\Notifications;

use App\Entity\Notification;
use App\Entity\User;
use App\Service\AcknowledgementService;
use App\Service\ApiResponseFactory;
use App\Service\NotificationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/notifications')]
#[OA\Tag(name: 'Notifications')]
final class ListNotificationsController extends AbstractController
{
    #[Route('', name: 'app_notification_list', methods: ['GET'])]
    #[OA\Get(
        path: '/notifications',
        summary: 'Lister les notifications de l\'utilisateur connecté',
        description: 'Liste paginée des notifications, filtrée automatiquement sur le destinataire connecté.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'is_read', in: 'query', schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all']), description: 'Filtrer par statut de lecture. Défaut : all.')]
    #[OA\Parameter(name: 'subject_type', in: 'query', schema: new OA\Schema(type: 'string', enum: [Notification::SUBJECT_ASSET_ASSIGNMENT, Notification::SUBJECT_CONSUMABLE_TRANSFER]))]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Notifications retournées avec succès.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 1, 'total_pages' => 1],
                    'data' => [
                        [
                            'id' => 1,
                            'subjectType' => 'asset_assignment',
                            'subjectId' => 5,
                            'type' => 'created',
                            'title' => 'Nouvelle affectation de bien',
                            'message' => 'Le bien "Ordinateur portable" vous a été affecté.',
                            'isRead' => false,
                            'readAt' => null,
                            'createdAt' => '2026-08-24 09:00:00',
                        ],
                    ],
                ],
            ]
        )
    )]
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        NotificationService $notificationService,
        AcknowledgementService $acknowledgementService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);

        $isReadParam = $request->query->get('is_read');
        $isRead = match ($isReadParam) {
            'true' => true,
            'false' => false,
            default => null,
        };

        $subjectType = $request->query->get('subject_type');

        $notifications = $notificationService->findForUser($user, $page, $limit, $isRead, $subjectType);
        $total = $notificationService->countForUser($user, $isRead, $subjectType);

        $acknowledgedMap = $this->buildAcknowledgedMap($notifications, $acknowledgementService);

        $data = array_map(fn (Notification $n) => $this->normalize($n, $acknowledgedMap), $notifications);

        return $apiResponse->success([
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / max(1, $limit)),
            ],
            'data' => $data,
        ], Response::HTTP_OK, 'Notifications retournées avec succès.');
    }

    /**
     * @param Notification[] $notifications
     * @return array<string, bool> clé "subjectType:subjectId" => accusé de réception effectué
     */
    private function buildAcknowledgedMap(array $notifications, AcknowledgementService $acknowledgementService): array
    {
        $idsBySubjectType = [];
        foreach ($notifications as $notification) {
            $idsBySubjectType[$notification->getSubjectType()][] = $notification->getSubjectId();
        }

        $map = [];
        foreach ($idsBySubjectType as $subjectType => $subjectIds) {
            $acks = $acknowledgementService->findForSubjects($subjectType, array_values(array_unique($subjectIds)));
            foreach ($subjectIds as $subjectId) {
                $map["{$subjectType}:{$subjectId}"] = isset($acks[$subjectId]);
            }
        }

        return $map;
    }

    /**
     * @param array<string, bool> $acknowledgedMap
     */
    private function normalize(Notification $notification, array $acknowledgedMap): array
    {
        return [
            'id' => $notification->getId(),
            'subjectType' => $notification->getSubjectType(),
            'subjectId' => $notification->getSubjectId(),
            'type' => $notification->getType(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'isRead' => $notification->isRead(),
            'readAt' => $notification->getReadAt()?->format('Y-m-d H:i:s'),
            'createdAt' => $notification->getCreatedAt()?->format('Y-m-d H:i:s'),
            'isAcknowledged' => $acknowledgedMap["{$notification->getSubjectType()}:{$notification->getSubjectId()}"] ?? false,
        ];
    }
}
