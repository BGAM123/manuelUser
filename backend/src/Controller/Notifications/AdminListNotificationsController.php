<?php

namespace App\Controller\Notifications;

use App\Entity\Notification;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Repository\UserRepository;
use App\Service\AcknowledgementService;
use App\Service\ApiResponseFactory;
use App\Service\NotificationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Listing administrateur des notifications, non restreint à l'utilisateur connecté.
 *
 * TODO(gestion des rôles) : cette route n'a aujourd'hui aucune vérification de permission —
 * elle doit être restreinte au rôle/à la permission admin dès que la gestion des rôles sera
 * branchée sur les routes (voir #[CurrentUser] ci-dessous pour le point d'accroche naturel).
 */
#[Route('/notifications')]
#[OA\Tag(name: 'Notifications')]
final class AdminListNotificationsController extends AbstractController
{
    #[Route('/admin', name: 'app_notification_admin_list', methods: ['GET'])]
    #[OA\Get(
        path: '/notifications/admin',
        summary: '[Admin] Lister toutes les notifications, ou celles d\'un utilisateur donné',
        description: "Réservé à un usage administrateur. Sans `user_id`, retourne les notifications de tous les utilisateurs. Avec `user_id`, filtre sur ce destinataire précis."
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'is_read', in: 'query', schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all']), description: 'Filtrer par statut de lecture. Défaut : all.')]
    #[OA\Parameter(name: 'subject_type', in: 'query', schema: new OA\Schema(type: 'string', enum: [Notification::SUBJECT_ASSET_ASSIGNMENT, Notification::SUBJECT_CONSUMABLE_TRANSFER]))]
    #[OA\Parameter(name: 'user_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: "Filtrer sur les notifications d'un destinataire précis. Omis = toutes les notifications, tous utilisateurs confondus.")]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 404, description: "L'utilisateur demandé (user_id) est introuvable")]
    public function __invoke(
        Request $request,
        NotificationService $notificationService,
        AcknowledgementService $acknowledgementService,
        UserRepository $userRepository,
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

        $recipient = null;
        if ($request->query->has('user_id')) {
            $recipient = $userRepository->find($request->query->getInt('user_id'));
            if (!$recipient instanceof User) {
                throw new ResourceNotFoundException("L'utilisateur demandé est introuvable.");
            }
        }

        $notifications = $notificationService->findAllForAdmin($page, $limit, $isRead, $subjectType, $recipient);
        $total = $notificationService->countAllForAdmin($isRead, $subjectType, $recipient);

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
        $recipient = $notification->getRecipient();

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
            'destinataire' => $recipient ? [
                'id' => $recipient->getId(),
                'nom' => $recipient->getLastName(),
                'prenom' => $recipient->getFirstName(),
            ] : null,
        ];
    }
}
