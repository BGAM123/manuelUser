<?php

namespace App\Controller\Core\Transmission;

use App\Entity\Core\User;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Core\NotificationRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Transmission")]
class ToggleAccuseReceptionController extends AbstractController
{
    public function __construct(
        private TransmissionRepository $transmissionRepository,
        private AccessCheckerService $accessChecker,
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/core/transmission/toggle-accuse-reception/{id<([1-9][0-9]*)>}', name: 'app_core_transmission_toggle_accuse_reception', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/transmission/toggle-accuse-reception/{id}',
        summary: 'Basculer l\'accusée de réception',
        description: 'Bascule le statut accuseReception d\'une transmission (true ↔ false).',
        tags: ['Transmission'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la transmission',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Accusée de réception modifiée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Accusée de réception activée.'),
                        new OA\Property(property: 'accuseReception', type: 'boolean', example: true),
                        new OA\Property(property: 'notifications_marked_as_read', type: 'integer', example: 1)
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Transmission non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function toggle(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'ToggleAccuseReception');

        $transmission = $this->transmissionRepository->find($id);

        if (!$transmission) {
            return $this->json(['code' => 404, 'message' => 'Transmission non trouvée.'], 404);
        }

        // ðŸ”„ Basculer accuseReception
        $newStatus = !$transmission->isAccuseReception();
        $transmission->setAccuseReception($newStatus);
        $transmission->setDateReception($newStatus ? new \DateTime() : null);
        $this->entityManager->flush();

        $notificationsMarkedAsRead = 0;
        if ($newStatus) {
            $currentUser = $this->getUser();
            $service = $currentUser instanceof User ? $currentUser->getIdService() : null;
            if ($service && $transmission->getId()) {
                $notificationsMarkedAsRead = $this->notificationRepository
                    ->markTransmissionNotificationsAsReadByServiceAndTransmissionId($service, (int) $transmission->getId());
            }
        }

        return $this->json([
            'code' => 200,
            'message' => $newStatus ? 'Accusée de réception activée.' : 'Accusée de réception désactivée.',
            'accuseReception' => $newStatus,
            'notifications_marked_as_read' => $notificationsMarkedAsRead,
        ], 200);
    }
}
