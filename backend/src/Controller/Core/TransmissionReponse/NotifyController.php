<?php

namespace App\Controller\Core\TransmissionReponse;

use App\Entity\Cour\TransmissionReponse;
use App\Repository\Core\UserRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\MailService;
use App\Service\SmsService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Twig\Environment;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: "TransmissionReponse")]
class NotifyController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private UserRepository $userRepository,
        private MailService $mailService,
        private SmsService $smsService,
        private UserActionLoggerService $actionLogger,
        private Environment $twig,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/transmission-reponse/notify/{id<([1-9][0-9]*)>}', name: 'app_core_transmission_reponse_notify', methods: ['POST'])]
    #[Route('/core/reponse/notify/{id<([1-9][0-9]*)>}', name: 'app_core_reponse_notify', methods: ['POST'])]
    #[OA\Post(
        path: '/core/transmission-reponse/notify/{id}',
        summary: 'Envoyer les notifications d\'une transmission reponse',
        description: 'Envoie email et/ou SMS aux utilisateurs actifs du service destinataire selon sendMail et sendSms.',
        tags: ['TransmissionReponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la transmission reponse',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'sendMail', type: 'boolean', example: true),
                    new OA\Property(property: 'sendSms', type: 'boolean', example: false),
                ],
                required: ['sendMail', 'sendSms']
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notifications traitees.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Notifications envoyees.'),
                        new OA\Property(property: 'sendMail', type: 'boolean', example: true),
                        new OA\Property(property: 'sendSms', type: 'boolean', example: false),
                        new OA\Property(
                            property: 'notifications',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'emailSentToServiceUsers', type: 'boolean', example: true),
                                new OA\Property(property: 'smsSentToServiceUsers', type: 'boolean', example: false),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requete invalide.'),
            new OA\Response(response: 404, description: 'Transmission reponse non trouvee.'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function notify(Request $request, ?TransmissionReponse $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'NotifyReponse');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Transmission reponse non trouvee.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = $request->request->all();
        }

        $sendMail = filter_var($data['sendMail'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendSms = filter_var($data['sendSms'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$sendMail && !$sendSms) {
            return $this->json([
                'code' => 200,
                'message' => 'Aucune notification demandee.',
                'sendMail' => false,
                'sendSms' => false,
                'notifications' => [
                    'emailSentToServiceUsers' => false,
                    'smsSentToServiceUsers' => false,
                ],
            ], 200);
        }

        $serviceDestinataire = $entity->getIdServiceDestinataire();
        if (!$serviceDestinataire) {
            return $this->json([
                'code' => 400,
                'message' => 'Service destinataire introuvable pour cette transmission reponse.',
            ], 400);
        }

        $results = [
            'emailSentToServiceUsers' => false,
            'smsSentToServiceUsers' => false,
        ];

        try {
            if ($sendMail) {
                $results['emailSentToServiceUsers'] = $this->sendEmailToServiceUsers($entity, $serviceDestinataire);
            }

            if ($sendSms) {
                $results['smsSentToServiceUsers'] = $this->sendSmsToServiceUsers($entity, $serviceDestinataire);
            }
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de l\'envoi des notifications pour transmission reponse', [
                'transmission_reponse_id' => $entity->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        $this->actionLogger->logSend(
            'TransmissionReponse',
            $entity->getId(),
            'Envoi des notifications de transmission reponse',
            [
                'sendMail' => $sendMail,
                'sendSms' => $sendSms,
                'notifications' => $results,
            ]
        );

        return $this->json([
            'code' => 200,
            'message' => 'Notifications envoyees.',
            'sendMail' => $sendMail,
            'sendSms' => $sendSms,
            'notifications' => $results,
        ], 200);
    }

    private function sendSmsToServiceUsers(TransmissionReponse $reponse, $serviceDestinataire): bool
    {
        $message = "MINEPIA: Courrier interne: merci de prendre connaissance du courrier numero {$reponse->getId()}. Objet: {$reponse->getObjet()}. qui vous a été transmis par: {$reponse->getIdRedacteur()?->getFirstName()} {$reponse->getIdRedacteur()?->getLastName()}.\nMINEPIA: Internal mail: please take note of mail number {$reponse->getId()}. Subject: {$reponse->getObjet()}. that has been forwarded to you by: {$reponse->getIdRedacteur()?->getFirstName()} {$reponse->getIdRedacteur()?->getLastName()}.";

        $sentCount = 0;

        try {
            $users = $this->userRepository->findBy([
                'idService' => $serviceDestinataire,
                'isActive' => true,
                'isDelete' => false
            ]);

            foreach ($users as $user) {
                if (!empty($user->getPhone())) {
                    try {
                        $userResult = $this->smsService->sendSms(
                            $user->getPhone(),
                            $message,
                            true
                        );

                        if ($userResult['success'] ?? false) {
                            $sentCount++;
                        }
                    } catch (\Exception $userException) {
                        $this->logger?->error('Erreur lors de l\'envoi du SMS a un utilisateur', [
                            'transmission_reponse_id' => $reponse->getId(),
                            'user_id' => $user->getId(),
                            'exception' => $userException->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la recuperation des utilisateurs pour l\'envoi de SMS', [
                'transmission_reponse_id' => $reponse->getId(),
                'service_id' => $serviceDestinataire?->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $sentCount > 0;
    }

    private function sendEmailToServiceUsers(TransmissionReponse $reponse, $serviceDestinataire): bool
    {
        $subject = "Transmission reponse - {$reponse->getObjet()}";

        $htmlContent = $this->twig->render('emails/reponse/notification_service_destinataire.html.twig', [
            'reponse' => $reponse,
            'serviceDestinataire' => $serviceDestinataire,
            'courrier' => null,
        ]);

        $sentCount = 0;

        try {
            $users = $this->userRepository->findBy([
                'idService' => $serviceDestinataire,
                'isActive' => true,
                'isDelete' => false
            ]);

            foreach ($users as $user) {
                if (!empty($user->getEmail())) {
                    try {
                        $this->mailService->sendEmail($user->getEmail(), $subject, $htmlContent);
                        $sentCount++;
                    } catch (\Exception $userException) {
                        $this->logger?->error('Erreur lors de l\'envoi de l\'email a un utilisateur', [
                            'transmission_reponse_id' => $reponse->getId(),
                            'user_id' => $user->getId(),
                            'exception' => $userException->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la recuperation des utilisateurs pour l\'envoi d\'emails', [
                'transmission_reponse_id' => $reponse->getId(),
                'service_id' => $serviceDestinataire?->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $sentCount > 0;
    }
}
