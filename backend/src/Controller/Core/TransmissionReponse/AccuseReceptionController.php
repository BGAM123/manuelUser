<?php

namespace App\Controller\Core\TransmissionReponse;

use App\Entity\Cour\TransmissionReponse;
use App\Entity\Core\User;
use App\Repository\Cour\CourrierInterneRepository;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Repository\Core\NotificationRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\MailService;
use App\Service\SmsService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: "TransmissionReponse")]
class AccuseReceptionController extends AbstractController
{
    public function __construct(
        private TransmissionReponseRepository $transmissionReponseRepository,
        private CourrierInterneRepository $courrierInterneRepository,
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
        private MailService $mailService,
        private SmsService $smsService,
        private UserActionLoggerService $actionLogger,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/transmission-reponse/accuse-reception', name: 'app_core_transmission_reponse_accuse_reception', methods: ['PATCH'])]
    #[Route('/core/transmission-reponse/accuse-reception/{id<([1-9][0-9]*)>}', name: 'app_core_transmission_reponse_accuse_reception_single', methods: ['PATCH'])]
    #[Route('/core/reponse/accuse-reception', name: 'app_core_reponse_accuse_reception', methods: ['PATCH'])]
    #[Route('/core/reponse/accuse-reception/{id<([1-9][0-9]*)>}', name: 'app_core_reponse_accuse_reception_single', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/transmission-reponse/accuse-reception',
        summary: 'Accuser reception d\'une ou plusieurs transmissions reponse',
        description: 'Met accuseReception a true pour une ou plusieurs transmissions reponse et notifie le redacteur par email et SMS.',
        tags: ['TransmissionReponse'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['ids'],
                properties: [
                    new OA\Property(
                        property: 'ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3],
                        description: 'Liste des IDs des transmissions reponse a accuser reception'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Accuses de reception traites.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: '2 transmission(s) reponse accusee(s) avec succes'),
                        new OA\Property(property: 'total', type: 'integer', example: 3),
                        new OA\Property(property: 'success', type: 'integer', example: 2),
                        new OA\Property(property: 'errors', type: 'integer', example: 1),
                        new OA\Property(
                            property: 'details',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                                    new OA\Property(property: 'accuseReception', type: 'boolean', example: true),
                                    new OA\Property(property: 'statut', type: 'string', example: 'Reçu'),
                                    new OA\Property(
                                        property: 'notifications',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'email_sent', type: 'boolean', example: true),
                                            new OA\Property(property: 'sms_sent', type: 'boolean', example: true),
                                        ]
                                    )
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requete invalide (IDs manquants ou format incorrect).'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function accuse(Request $request, ?int $id = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'AccuseReceptionReponse');

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = $request->request->all();
        }

        $ids = $data['ids'] ?? null;
        if ($ids === null && $id !== null) {
            $ids = [$id];
        }

        if (!is_array($ids) || empty($ids)) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "ids" est requis et doit etre un tableau non vide.'
            ], 400);
        }

        $results = [];
        $successCount = 0;
        $errorCount = 0;
        $toNotify = [];
        $transmissionReponseIdsToMarkNotificationAsRead = [];

        $currentUser = $this->getUser();
        $currentService = $currentUser instanceof User ? $currentUser->getIdService() : null;

        foreach ($ids as $rawId) {
            if (!is_numeric($rawId)) {
                $results[] = [
                    'id' => $rawId,
                    'status' => 'error',
                    'message' => 'ID invalide (doit etre un nombre entier).'
                ];
                $errorCount++;
                continue;
            }

            $transmissionReponse = $this->transmissionReponseRepository->find((int) $rawId);
            if (!$transmissionReponse) {
                $results[] = [
                    'id' => $rawId,
                    'status' => 'error',
                    'message' => 'Transmission reponse non trouvee.'
                ];
                $errorCount++;
                continue;
            }

            if ($transmissionReponse->isAccuseReception()) {
                if ($transmissionReponse->getId()) {
                    $transmissionReponseIdsToMarkNotificationAsRead[] = (int) $transmissionReponse->getId();
                }

                $results[] = [
                    'id' => $transmissionReponse->getId(),
                    'status' => 'warning',
                    'message' => 'Accuse de reception deja active.',
                    'accuseReception' => true,
                    'notifications' => [
                        'email_sent' => false,
                        'sms_sent' => false,
                    ]
                ];
                $errorCount++;
                continue;
            }

            try {
                $transmissionReponse->setAccuseReception(true);
                $transmissionReponse->refreshStatut();

                $linkedCourrierInterneIds = $transmissionReponse->getIdCourrierInternes();
                if (is_array($linkedCourrierInterneIds)) {
                    foreach ($linkedCourrierInterneIds as $courrierInterneId) {
                        if (!is_numeric($courrierInterneId)) {
                            continue;
                        }
                        $linkedCourrierInterne = $this->courrierInterneRepository->find((int) $courrierInterneId);
                        if ($linkedCourrierInterne && !$linkedCourrierInterne->isAccuseReception()) {
                            $linkedCourrierInterne->setAccuseReception(true);
                            $linkedCourrierInterne->refreshStatut();
                        }
                    }
                }

                $toNotify[] = $transmissionReponse;

                if ($transmissionReponse->getId()) {
                    $transmissionReponseIdsToMarkNotificationAsRead[] = (int) $transmissionReponse->getId();
                }

                $results[] = [
                    'id' => $transmissionReponse->getId(),
                    'status' => 'success',
                    'accuseReception' => true,
                    'statut' => $transmissionReponse->getStatut(),
                    'notifications' => [
                        'email_sent' => false,
                        'sms_sent' => false,
                    ]
                ];
                $successCount++;
            } catch (\Exception $e) {
                $results[] = [
                    'id' => $transmissionReponse->getId(),
                    'status' => 'error',
                    'message' => 'Erreur lors de la mise a jour : ' . $e->getMessage()
                ];
                $errorCount++;
            }
        }

        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage()
            ], 500);
        }

        if ($currentService) {
            try {
                $this->notificationRepository->markTransmissionReponseNotificationsAsReadByServiceAndTransmissionReponseIds(
                    $currentService,
                    $transmissionReponseIdsToMarkNotificationAsRead
                );

                // Suppression des notifications après les avoir marquées comme lues
                $this->notificationRepository->deleteTransmissionReponseNotificationsByServiceAndTransmissionReponseIds(
                    $currentService,
                    $transmissionReponseIdsToMarkNotificationAsRead
                );
            } catch (\Exception $e) {
                $this->logger?->error('Erreur lors du marquage et suppression des notifications (transmission reponse)', [
                    'service_id' => $currentService->getId(),
                    'transmission_reponse_ids' => $transmissionReponseIdsToMarkNotificationAsRead,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        foreach ($toNotify as $transmissionReponse) {
            $notif = $this->notifyRedacteur($transmissionReponse);
            foreach ($results as &$row) {
                if (($row['id'] ?? null) === $transmissionReponse->getId()) {
                    $row['notifications'] = $notif;
                    break;
                }
            }
            unset($row);
        }

        $this->actionLogger->logSend(
            'TransmissionReponse',
            null,
            'Accuse de reception et notifications redacteur (transmission reponse)',
            [
                'ids' => $ids,
                'success' => $successCount,
                'errors' => $errorCount,
            ]
        );

        return $this->json([
            'message' => sprintf(
                '%d transmission(s) reponse accusee(s) avec succes%s',
                $successCount,
                $errorCount > 0 ? sprintf(', %d erreur(s)', $errorCount) : ''
            ),
            'total' => count($ids),
            'success' => $successCount,
            'errors' => $errorCount,
            'details' => $results
        ], 200);
    }

    private function notifyRedacteur(TransmissionReponse $reponse): array
    {
        $result = [
            'email_sent' => false,
            'sms_sent' => false,
        ];

        $redacteur = $reponse->getIdRedacteur();
        if (!$redacteur) {
            return $result;
        }

        if (!empty($redacteur->getEmail())) {
            try {
                $subject = 'Accuse de reception de votre courrier interne';
                $htmlContent = '<p>Votre courrier interne numero ' . $reponse->getId() . ' a ete accusee reception.</p>';
                $this->mailService->sendEmail($redacteur->getEmail(), $subject, $htmlContent);
                $result['email_sent'] = true;
            } catch (\Exception $e) {
                    $this->logger?->error('Erreur envoi email redacteur', [
                        'transmission_reponse_id' => $reponse->getId(),
                        'user_id' => $redacteur->getId(),
                        'exception' => $e->getMessage()
                    ]);
            }
        }

        if (!empty($redacteur->getPhone())) {
            $message = 'MINEPIA: votre courrier interne N° ' . $reponse->getId() . ' a ete accusee reception.' . "\n" . 'MINEPIA: your internal mail no ' . $reponse->getId() . ' has been acknowledged.';
            $smsResult = $this->smsService->sendSms($redacteur->getPhone(), $message, true);
            if ($smsResult['success'] ?? false) {
                $result['sms_sent'] = true;
            }
        }

        return $result;
    }
}
