<?php

namespace App\Controller\Core\Transmission;

use App\Entity\Core\Notification;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Core\ServiceRepository;
use App\Repository\Core\UserRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\MailService;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;
use Twig\Environment;

#[OA\Tag(name: "Transmission")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private TransmissionRepository $transmissionRepository,
        private ServiceRepository $serviceRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private MailService $mailService,
        private SmsService $smsService,
        private Environment $twig,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/transmission/{id<([1-9][0-9]*)>}', name: 'app_core_transmission_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/transmission/{id}',
        summary: 'Met à jour une transmission existante',
        tags: ['Transmission'],
        description: "Met à jour les informations d'une transmission (instruction, statut, etc.).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant de la transmission', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'idServiceDestinataire', type: 'integer', example: 7),
                    new OA\Property(property: 'idEmetteur', type: 'integer', example: 2),
                    new OA\Property(property: 'structuresCopie', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'instruction', type: 'string', example: 'Mise à jour de l\'instruction'),
                    new OA\Property(property: 'delaiTraitement', type: 'integer', example: 3),
                    new OA\Property(property: 'typeTransfert', type: 'string', example: 'Pour information'),
                    new OA\Property(property: 'accuseReception', type: 'boolean', example: true),
                    new OA\Property(property: 'statut', type: 'string', example: 'Traité'),
                    new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 3, description: 'Nombre de pièces jointes (valeur libre saisie par l\'utilisateur)'),
                    new OA\Property(property: 'sendEmail', type: 'boolean', example: true, description: 'Envoyer un email de notification lors de la mise à jour'),
                    new OA\Property(property: 'sendSms', type: 'boolean', example: true, description: 'Envoyer un SMS de notification lors de la mise à jour'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transmission mise à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Transmission mise à jour avec succès'),
                        new OA\Property(property: 'dateInstruction', type: 'string', format: 'date-time', example: '2025-12-18 10:30:00', description: 'Date et heure de la transmission'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 3, description: 'Nombre de pièces jointes'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Transmission non trouvée.'),
            new OA\Response(response: 400, description: 'Erreur de validation.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PatchTransmission');

        $transmission = $this->transmissionRepository->find($id);

        if (!$transmission) {
            return $this->json(['code' => 404, 'message' => 'Transmission non trouvée.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->functionService->validate($data);
        
        // RÃ©cupÃ©ration des paramÃ¨tres d'envoi (non sauvegardÃ©s en base)
        $sendEmail = $data['sendEmail'] ?? false;
        $sendSms = $data['sendSms'] ?? false;
        
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'idCourrier', 'dateInstruction', 'sendEmail', 'sendSms']);

        try {
            // Sauvegarder l'ancien service destinataire pour dÃ©tecter les changements
            $oldServiceDestinataire = $transmission->getIdServiceDestinataire();
            $serviceDestinataireChanged = false;

            // ðŸ”¹ RÃ©solution des relations
            if (!empty($data['idServiceDestinataire'])) {
                $serviceDestinataire = $this->serviceRepository->find($data['idServiceDestinataire']);
                if (!$serviceDestinataire) {
                    return $this->json(['code' => 404, 'message' => 'Service destinataire introuvable.'], 404);
                }
                
                // VÃ©rifier si le service destinataire a changÃ©
                if ($oldServiceDestinataire && $oldServiceDestinataire->getId() !== $serviceDestinataire->getId()) {
                    $serviceDestinataireChanged = true;
                }
                
                $data['idServiceDestinataire'] = $serviceDestinataire;
            }

            if (!empty($data['idEmetteur'])) {
                $emetteur = $this->userRepository->find($data['idEmetteur']);
                if (!$emetteur) {
                    return $this->json(['code' => 404, 'message' => 'émetteur introuvable.'], 404);
                }
                $data['idEmetteur'] = $emetteur;
            }

            // ðŸ’¾ Mise Ã  jour
            $this->crudService->patchEntity($transmission, $data);
            
            // âœ… FLUSH POUR GARANTIR LA MISE Ã€ JOUR AVANT LES NOTIFICATIONS
            $this->entityManager->flush();
            
            // ðŸ”” ENVOI DES NOTIFICATIONS si demandÃ© ET si le service destinataire a changÃ©
            if (($sendEmail || $sendSms) && $serviceDestinataireChanged && isset($serviceDestinataire)) {
                $courrier = $transmission->getIdCourrier();
                $emetteur = $transmission->getIdEmetteur();
                
                if ($courrier && $emetteur) {
                    // Envoi des notifications email/SMS
                    $notificationResults = $this->handleUpdateNotifications(
                        $transmission,
                        $serviceDestinataire,
                        $courrier,
                        $emetteur,
                        $sendEmail,
                        $sendSms,
                        $data['structuresCopie'] ?? null
                    );
                    
                    // CrÃ©ation des notifications en base de donnÃ©es
                    $notificationCount = $this->createUpdateNotifications(
                        $transmission,
                        $serviceDestinataire,
                        $data['structuresCopie'] ?? null,
                        $courrier
                    );
                    
                    return $this->json([
                        'message' => 'Transmission mise à jour avec succès',
                        'dateInstruction' => $transmission->getDateInstruction()?->format('Y-m-d H:i:s'),
                        'nombrePieceJointe' => $transmission->getNombrePieceJointe(),
                        'notifications' => $notificationResults,
                        'notificationsSent' => $notificationCount,
                    ], 200);
                }
            }

            return $this->json([
                'message' => 'Transmission mise à jour avec succès',
                'dateInstruction' => $transmission->getDateInstruction()?->format('Y-m-d H:i:s'),
                'nombrePieceJointe' => $transmission->getNombrePieceJointe(),
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GÃ¨re l'envoi de toutes les notifications (emails + SMS) lors de la mise Ã  jour d'une transmission
     */
    private function handleUpdateNotifications(
        $transmission,
        $serviceDestinataire,
        $courrier,
        $emetteur,
        bool $sendEmail,
        bool $sendSms,
        ?array $structuresCopie = null
    ): array {
        $results = [
            'emailSentToService' => false,
            'smsSentToService' => false,
            'emailSentToParentService' => false,
            'smsSentToParentService' => false,
            'emailSentToServicesCopie' => [],
            'smsSentToServicesCopie' => [],
        ];

        try {
            // ðŸ“§ EMAIL AU SERVICE DESTINATAIRE PRINCIPAL
            if ($sendEmail) {
                if ($serviceDestinataire && !empty($serviceDestinataire->getEmailService())) {
                    $this->sendEmailToService($transmission, $serviceDestinataire, $courrier, $emetteur);
                    $results['emailSentToService'] = true;
                }
            }

            // ðŸ“± SMS AU SERVICE DESTINATAIRE PRINCIPAL
            if ($sendSms) {
                if ($serviceDestinataire && !empty($serviceDestinataire->getTelephone())) {
                    $smsResult = $this->sendSmsToService($transmission, $serviceDestinataire, $courrier, $emetteur);
                    $results['smsSentToService'] = $smsResult['success'];
                }
            }

            // ðŸ†• ðŸ“§ EMAIL AU SERVICE PARENT DU DESTINATAIRE
            if ($sendEmail && $serviceDestinataire) {
                $serviceParent = $serviceDestinataire->getIdServiceParent();
                if ($serviceParent && !empty($serviceParent->getEmailService())) {
                    try {
                        $this->sendEmailToService($transmission, $serviceParent, $courrier, $emetteur);
                        $results['emailSentToParentService'] = true;
                        $this->logger?->info('Email envoyé au service parent (mise à jour)', [
                            'transmission_id' => $transmission->getId(),
                            'service_parent' => $serviceParent->getNom()
                        ]);
                    } catch (\Exception $parentEmailException) {
                        $this->logger?->error('Erreur envoi email service parent (mise à jour)', [
                            'service_parent_id' => $serviceParent->getId(),
                            'exception' => $parentEmailException->getMessage()
                        ]);
                        $results['emailSentToParentService'] = false;
                    }
                }
            }

            // ðŸ†• ðŸ“± SMS AU SERVICE PARENT DU DESTINATAIRE
            if ($sendSms && $serviceDestinataire) {
                $serviceParent = $serviceDestinataire->getIdServiceParent();
                if ($serviceParent && !empty($serviceParent->getTelephone())) {
                    try {
                        $smsResult = $this->sendSmsToService($transmission, $serviceParent, $courrier, $emetteur);
                        $results['smsSentToParentService'] = $smsResult['success'];
                        $this->logger?->info('SMS envoyé au service parent (mise à jour)', [
                            'transmission_id' => $transmission->getId(),
                            'service_parent' => $serviceParent->getNom(),
                            'success' => $smsResult['success']
                        ]);
                    } catch (\Exception $parentSmsException) {
                        $this->logger?->error('Erreur envoi SMS service parent (mise à jour)', [
                            'service_parent_id' => $serviceParent->getId(),
                            'exception' => $parentSmsException->getMessage()
                        ]);
                        $results['smsSentToParentService'] = false;
                    }
                }
            }

            // ðŸ“§ðŸ“± EMAILS ET SMS AUX SERVICES EN COPIE
            if (!empty($structuresCopie) && is_array($structuresCopie)) {
                foreach ($structuresCopie as $serviceId) {
                    try {
                        $serviceCopie = $this->serviceRepository->find($serviceId);
                        
                        if ($serviceCopie) {
                            // ðŸ“§ Email au service en copie
                            if ($sendEmail && !empty($serviceCopie->getEmailService())) {
                                try {
                                    $this->sendEmailToService($transmission, $serviceCopie, $courrier, $emetteur);
                                    $results['emailSentToServicesCopie'][] = [
                                        'service_id' => $serviceId,
                                        'service_nom' => $serviceCopie->getNom(),
                                        'success' => true
                                    ];
                                } catch (\Exception $emailException) {
                                    $results['emailSentToServicesCopie'][] = [
                                        'service_id' => $serviceId,
                                        'service_nom' => $serviceCopie->getNom(),
                                        'success' => false
                                    ];
                                }
                            }

                            // ðŸ“± SMS au service en copie
                            if ($sendSms && !empty($serviceCopie->getTelephone())) {
                                try {
                                    $smsResult = $this->sendSmsToService($transmission, $serviceCopie, $courrier, $emetteur);
                                    $results['smsSentToServicesCopie'][] = [
                                        'service_id' => $serviceId,
                                        'service_nom' => $serviceCopie->getNom(),
                                        'success' => $smsResult['success']
                                    ];
                                } catch (\Exception $smsException) {
                                    $results['smsSentToServicesCopie'][] = [
                                        'service_id' => $serviceId,
                                        'service_nom' => $serviceCopie->getNom(),
                                        'success' => false
                                    ];
                                }
                            }
                        }
                    } catch (\Exception $serviceException) {
                        $this->logger?->error('Erreur traitement service copie (mise à jour)', [
                            'service_id' => $serviceId,
                            'exception' => $serviceException->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de l\'envoi des notifications de mise à jour', [
                'transmission_id' => $transmission->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * Envoie un email de notification de mise Ã  jour de transmission au service destinataire ET Ã  tous ses utilisateurs actifs
     */
    private function sendEmailToService($transmission, $serviceDestinataire, $courrier, $emetteur): void
    {
        $subject = "Mise à jour transmission - Courrier n° {$courrier->getNumero()}";
        
        $htmlContent = $this->twig->render('emails/transmission/notification_transmission.html.twig', [
            'transmission' => $transmission,
            'serviceDestinataire' => $serviceDestinataire,
            'courrier' => $courrier,
            'emetteur' => $emetteur,
            'isUpdate' => true,
        ]);

        // Chemin vers le logo MINEPIA
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/cropped-logo-minepia.png';

        // 1ï¸âƒ£ Envoyer Ã  l'adresse email du service
        try {
            $this->mailService->sendEmailWithLogo($serviceDestinataire->getEmailService(), $subject, $htmlContent, $logoPath);
            $this->logger?->info('Email de mise à jour envoyé au service', [
                'transmission_id' => $transmission->getId(),
                'service' => $serviceDestinataire->getNom(),
                'email' => $serviceDestinataire->getEmailService()
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de l\'envoi de l\'email au service (mise à jour)', [
                'transmission_id' => $transmission->getId(),
                'service' => $serviceDestinataire->getNom(),
                'exception' => $e->getMessage()
            ]);
        }

        // 2ï¸âƒ£ Envoyer Ã  chaque utilisateur actif du service
        try {
            $users = $this->userRepository->findBy([
                'idService' => $serviceDestinataire,
                'isActive' => true,
                'isDelete' => false
            ]);

            foreach ($users as $user) {
                if (!empty($user->getEmail())) {
                    try {
                        $this->mailService->sendEmailWithLogo($user->getEmail(), $subject, $htmlContent, $logoPath);
                        $this->logger?->info('Email de mise à jour envoyé à l\'utilisateur', [
                            'transmission_id' => $transmission->getId(),
                            'user_id' => $user->getId(),
                            'email' => $user->getEmail()
                        ]);
                    } catch (\Exception $userException) {
                        $this->logger?->error('Erreur lors de l\'envoi de l\'email à un utilisateur (mise à jour)', [
                            'transmission_id' => $transmission->getId(),
                            'user_id' => $user->getId(),
                            'exception' => $userException->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la récupération des utilisateurs pour l\'envoi d\'emails (mise à jour)', [
                'transmission_id' => $transmission->getId(),
                'service' => $serviceDestinataire->getNom(),
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * ðŸ“± Envoie un SMS de notification de mise Ã  jour de transmission au service destinataire ET Ã  tous ses utilisateurs actifs
     */
    private function sendSmsToService($transmission, $serviceDestinataire, $courrier, $emetteur): array
    {
        $numero = $courrier->getNumero();
        $emetteurNom = "{$emetteur->getFirstName()} {$emetteur->getLastName()}";
        
        if (mb_strlen($emetteurNom, 'UTF-8') > 20) {
            $emetteurNom = mb_substr($emetteurNom, 0, 20, 'UTF-8') . '.';
        }
        
        $message = "Mise à jour: le courrier n°{$numero} transmis par {$emetteurNom}. consultez: https://minepia.cm/site/consultez-vos-dossiers/\nMerci.\nUpdate: mail no {$numero} sent by {$emetteurNom}. See: https://minepia.cm/site/consultez-vos-dossiers/\nThank you.";
        
        $result = ['success' => false, 'service' => false, 'users' => 0];
        
        // 1ï¸âƒ£ Envoyer au numÃ©ro de tÃ©lÃ©phone du service
        try {
            $serviceResult = $this->smsService->sendSms(
                $serviceDestinataire->getTelephone(),
                $message,
                true
            );
            $result['service'] = $serviceResult['success'] ?? false;
            $result['success'] = $result['service'];
            
            $this->logger?->info('SMS de mise à jour envoyé au service', [
                'transmission_id' => $transmission->getId(),
                'service' => $serviceDestinataire->getNom(),
                'telephone' => $serviceDestinataire->getTelephone(),
                'success' => $result['service']
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de l\'envoi du SMS au service (mise à jour)', [
                'transmission_id' => $transmission->getId(),
                'service' => $serviceDestinataire->getNom(),
                'exception' => $e->getMessage()
            ]);
        }

        // 2ï¸âƒ£ Envoyer Ã  chaque utilisateur actif du service
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
                            $result['users']++;
                            $result['success'] = true;
                        }
                        
                        $this->logger?->info('SMS de mise à jour envoyé à l\'utilisateur', [
                            'transmission_id' => $transmission->getId(),
                            'user_id' => $user->getId(),
                            'telephone' => $user->getPhone(),
                            'success' => $userResult['success'] ?? false
                        ]);
                    } catch (\Exception $userException) {
                        $this->logger?->error('Erreur lors de l\'envoi du SMS à un utilisateur (mise à jour)', [
                            'transmission_id' => $transmission->getId(),
                            'user_id' => $user->getId(),
                            'exception' => $userException->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la récupération des utilisateurs pour l\'envoi de SMS (mise à jour)', [
                'transmission_id' => $transmission->getId(),
                'service' => $serviceDestinataire->getNom(),
                'exception' => $e->getMessage()
            ]);
        }
        
        return $result;
    }

    /**
     * ðŸ”” CrÃ©e des notifications en base de donnÃ©es pour les utilisateurs des services concernÃ©s lors d'une mise Ã  jour
     */
    private function createUpdateNotifications(
        $transmission,
        $serviceDestinataire,
        ?array $structuresCopie,
        $courrier
    ): int {
        $totalCount = 0;

        try {
            // 1ï¸âƒ£ Notifications pour le service destinataire principal
            if ($serviceDestinataire) {
                $count = $this->createNotificationsForService(
                    $serviceDestinataire,
                    $transmission,
                    $courrier,
                    false
                );
                $totalCount += $count;

                $this->logger?->info('Notifications de mise à jour créées pour le service destinataire', [
                    'transmission_id' => $transmission->getId(),
                    'service' => $serviceDestinataire->getNom(),
                    'count' => $count
                ]);
            }

            // ðŸ†• 1bisï¸âƒ£ Notifications pour le service PARENT du destinataire
            if ($serviceDestinataire) {
                $serviceParent = $serviceDestinataire->getIdServiceParent();
                if ($serviceParent) {
                    try {
                        $count = $this->createNotificationsForService(
                            $serviceParent,
                            $transmission,
                            $courrier,
                            false
                        );
                        $totalCount += $count;

                        $this->logger?->info('Notifications de mise à jour créées pour le service parent du destinataire', [
                            'transmission_id' => $transmission->getId(),
                            'service_parent' => $serviceParent->getNom(),
                            'count' => $count
                        ]);
                    } catch (\Exception $parentException) {
                        $this->logger?->error('Erreur lors de la création des notifications pour le service parent (mise à jour)', [
                            'transmission_id' => $transmission->getId(),
                            'service_parent_id' => $serviceParent->getId(),
                            'exception' => $parentException->getMessage()
                        ]);
                    }
                }
            }

            // 2ï¸âƒ£ Notifications pour les services en copie
            if (!empty($structuresCopie) && is_array($structuresCopie)) {
                foreach ($structuresCopie as $serviceId) {
                    try {
                        $serviceCopie = $this->serviceRepository->find($serviceId);
                        
                        if ($serviceCopie) {
                            $count = $this->createNotificationsForService(
                                $serviceCopie,
                                $transmission,
                                $courrier,
                                true
                            );
                            $totalCount += $count;

                            $this->logger?->info('Notifications de mise à jour créées pour un service en copie', [
                                'transmission_id' => $transmission->getId(),
                                'service' => $serviceCopie->getNom(),
                                'count' => $count
                            ]);
                        }
                    } catch (\Exception $serviceException) {
                        $this->logger?->error('Erreur lors de la création des notifications pour un service en copie (mise à jour)', [
                            'transmission_id' => $transmission->getId(),
                            'service_id' => $serviceId,
                            'exception' => $serviceException->getMessage()
                        ]);
                    }
                }
            }

            $this->logger?->info('Notifications de mise à jour créées avec succès', [
                'transmission_id' => $transmission->getId(),
                'total_notifications' => $totalCount
            ]);

        } catch (\Exception $e) {
            $this->logger?->error('Erreur générale lors de la création des notifications de mise à jour', [
                'transmission_id' => $transmission->getId(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }

        return $totalCount;
    }

    /**
     * ðŸ”” CrÃ©e des notifications pour tous les utilisateurs actifs d'un service donnÃ© lors d'une mise Ã  jour
     */
    private function createNotificationsForService(
        $service,
        $transmission,
        $courrier,
        bool $isCopie = false
    ): int {
        $count = 0;

        try {
            $users = $this->userRepository->findBy([
                'idService' => $service,
                'isActive' => true,
                'isDelete' => false
            ]);

            if (empty($users)) {
                return 0;
            }

            if ($isCopie) {
                $titre = "Mise à jour transmission en copie - Courrier n°{$courrier->getNumero()}";
                $message = "Mise à jour d'un courrier qui vous a été transmis en copie. Objet : {$courrier->getObjet()}";
            } else {
                $titre = "Mise à jour transmission - Courrier n°{$courrier->getNumero()}";
                $message = "Mise à jour d'un courrier qui vous a été transmis. Objet : {$courrier->getObjet()}";
            }

            foreach ($users as $user) {
                try {
                    $notification = new Notification();
                    $notification->setTitre($titre);
                    $notification->setMessage($message);
                    $notification->setType('transmission_update');
                    $notification->setData([
                        'transmission_id' => $transmission->getId(),
                        'courrier_id' => $courrier->getId(),
                        'numero' => $courrier->getNumero(),
                        'reference' => $courrier->getReference(),
                        'objet' => $courrier->getObjet(),
                        'priorite' => $courrier->getPriorite(),
                        'expediteur' => $courrier->getNom(),
                        'date_arrivee' => $courrier->getDateArrivee()?->format('Y-m-d'),
                        'date_transmission' => $transmission->getDateInstruction()?->format('Y-m-d H:i:s'),
                        'instruction' => $transmission->getInstruction(),
                        'delai_traitement' => $transmission->getDelaiTraitement(),
                        'is_copie' => $isCopie,
                        'is_update' => true,
                    ]);
                    $notification->setUser($user);
                    $notification->setService($service);

                    $this->entityManager->persist($notification);
                    $count++;
                } catch (\Exception $userException) {
                    $this->logger?->error('Erreur lors de la création d\'une notification pour un utilisateur (mise à jour)', [
                        'transmission_id' => $transmission->getId(),
                        'user_id' => $user->getId(),
                        'service' => $service->getNom(),
                        'exception' => $userException->getMessage()
                    ]);
                }
            }

            if ($count > 0) {
                try {
                    $this->entityManager->flush();
                } catch (\Exception $flushException) {
                    $this->logger?->error('Erreur lors du flush des notifications (mise à jour)', [
                        'transmission_id' => $transmission->getId(),
                        'service' => $service->getNom(),
                        'exception' => $flushException->getMessage()
                    ]);
                    return 0;
                }
            }

        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la création des notifications pour le service (mise à jour)', [
                'transmission_id' => $transmission->getId(),
                'service' => $service->getNom(),
                'exception' => $e->getMessage()
            ]);
            return 0;
        }

        return $count;
    }
}
