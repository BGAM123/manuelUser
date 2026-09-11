<?php

namespace App\Controller\Core\Notification;

use App\Entity\Core\User;
use App\Repository\Cour\CourrierInterneRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Repository\Core\NotificationRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Notification")]
class GetController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private TransmissionReponseRepository $transmissionReponseRepository,
        private CourrierInterneRepository $courrierInterneRepository,
        private TransmissionRepository $transmissionRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/notification/{id}', name: 'app_core_notification_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/core/notification/{id}',
        summary: 'Récupérer une notification par son ID',
        description: 'Retourne les détails d\'une notification spécifique',
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
                description: 'Notification récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'titre', type: 'string', example: 'Nouveau courrier reçu'),
                        new OA\Property(property: 'message', type: 'string', example: 'Un nouveau courrier a été enregistré pour votre service'),
                        new OA\Property(property: 'type', type: 'string', example: 'courrier', nullable: true),
                        new OA\Property(property: 'isRead', type: 'boolean', example: false),
                        new OA\Property(property: 'readAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'data', type: 'object', nullable: true),
                        new OA\Property(property: 'user', type: 'object'),
                        new OA\Property(property: 'service', type: 'object', nullable: true),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Notification non trouvée.'),
            new OA\Response(response: 403, description: 'Accès refusé.'),
        ]
    )]
    public function get(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetNotification');

        /** @var User $user */
        $user = $this->getUser();

        $notification = $this->notificationRepository->find($id);

        if (!$notification) {
            return $this->json(['error' => 'Notification non trouvÃ©e'], Response::HTTP_NOT_FOUND);
        }

        // VÃ©rifier que l'utilisateur a accÃ¨s Ã  cette notification
        $hasAccess = $notification->getUser()->getId() === $user->getId();
        
        if (!$hasAccess && $user->getIdService() && $notification->getService()) {
            $hasAccess = $notification->getService()->getId() === $user->getIdService()->getId();
        }

        if (!$hasAccess) {
            return $this->json(['error' => 'Accès refusé à cette notification'], Response::HTTP_FORBIDDEN);
        }

        $data = $this->injectNumeroIntoTransmissionReponseNotificationData($notification->getData());

        return $this->json([
            'id' => $notification->getId(),
            'titre' => $notification->getTitre(),
            'message' => $notification->getMessage(),
            'type' => $notification->getType(),
            'isRead' => $notification->isRead(),
            'readAt' => $notification->getReadAt()?->format('c'),
            'data' => $data,
            'user' => [
                'id' => $notification->getUser()->getId(),
                'username' => $notification->getUser()->getUsername(),
                'fullName' => trim($notification->getUser()->getFirstName() . ' ' . $notification->getUser()->getLastName()),
            ],
            'service' => $notification->getService() ? [
                'id' => $notification->getService()->getId(),
                'nom' => $notification->getService()->getNom(),
                'sigle' => $notification->getService()->getSigle(),
            ] : null,
            'createdAt' => $notification->getCreatedAt()->format('c'),
            'updatedAt' => $notification->getUpdatedAt()->format('c'),
        ]);
    }

    private function injectNumeroIntoTransmissionReponseNotificationData(?array $data): ?array
    {
        if (!is_array($data)) {
            return $data;
        }

        $transmissionReponseId = $data['transmission_reponse_id'] ?? null;
        if (!is_numeric($transmissionReponseId)) {
            return $data;
        }

        $transmissionReponseId = (int) $transmissionReponseId;
        if ($transmissionReponseId <= 0) {
            return $data;
        }

        if (!empty($data['numero'])) {
            return $data;
        }

        $transmissionReponse = $this->transmissionReponseRepository->find($transmissionReponseId);
        if (!$transmissionReponse instanceof \App\Entity\Cour\TransmissionReponse) {
            return $data;
        }

        $numero = null;

        $courrierInterneIds = $transmissionReponse->getIdCourrierInternes();
        if (is_array($courrierInterneIds) && !empty($courrierInterneIds)) {
            $firstCourrierInterneId = (int) (reset($courrierInterneIds) ?: 0);
            if ($firstCourrierInterneId > 0) {
                $courrierInterne = $this->courrierInterneRepository->find($firstCourrierInterneId);
                $numero = $courrierInterne instanceof \App\Entity\Cour\CourrierInterne ? $courrierInterne->getNumero() : null;
            }
        }

        if (!$numero) {
            $transmissionIds = $transmissionReponse->getIdTransmission();
            if (is_array($transmissionIds) && !empty($transmissionIds)) {
                $firstTransmissionId = (int) (reset($transmissionIds) ?: 0);
                if ($firstTransmissionId > 0) {
                    $transmission = $this->transmissionRepository->find($firstTransmissionId);
                    $numero = $transmission instanceof \App\Entity\Cour\Transmission ? $transmission->getIdCourrier()?->getNumero() : null;
                }
            }
        }

        if (is_string($numero) && $numero !== '') {
            $data['numero'] = $numero;
        }

        return $data;
    }
}
