<?php

namespace App\Controller\Core\Courrier;

use App\Repository\Cour\CourrierRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\MailService;
use App\Service\SmsService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: "CourrierNotification")]
class NotificationController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private AccessCheckerService $accessChecker,
        private MailService $mailService,
        private SmsService $smsService,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/courrier/notify-service', name: 'app_core_courrier_notify_service', methods: ['POST'])]
    #[OA\Post(
        path: '/core/courrier/notify-service',
        summary: 'Notifier le service traitant d\'un courrier spécifique',
        tags: ['CourrierNotification'],
        description: "Envoie des notifications par email et/ou SMS au service traitant d'un courrier existant en fonction des paramètres choisis.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['courrierArrive'],
                    properties: [
                        new OA\Property(
                            property: 'courrierArrive',
                            type: 'integer',
                            example: 15,
                            description: 'ID du courrier pour lequel notifier le service traitant'
                        ),
                        new OA\Property(
                            property: 'sendServiceTraitant',
                            type: 'boolean',
                            example: true,
                            description: 'Envoyer un email au service traitant'
                        ),
                        new OA\Property(
                            property: 'sendSmsServiceTraitant',
                            type: 'boolean',
                            example: false,
                            description: 'Envoyer un SMS au service traitant'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notifications envoyées avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Notifications envoyées avec succès'),
                        new OA\Property(property: 'courrier_id', type: 'integer', example: 15),
                        new OA\Property(property: 'service', type: 'string', example: 'Direction des Ressources Humaines'),
                        new OA\Property(
                            property: 'notifications',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'emailSentToService', type: 'boolean', example: true),
                                new OA\Property(property: 'smsSentToService', type: 'boolean', example: false),
                                new OA\Property(property: 'emailMessage', type: 'string', example: 'Email envoyé avec succès'),
                                new OA\Property(property: 'smsMessage', type: 'string', example: 'SMS non demandé')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Requête invalide.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'L\'ID du courrier est requis')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Courrier non trouvé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier non trouvé')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function notifyService(Request $request): Response
    {
        // ðŸ” VÃ©rification des droits d'accÃ¨s
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'NotifyCourrier');

        // ðŸ“¥ RÃ©cupÃ©ration des donnÃ©es de la requÃªte
        $data = json_decode($request->getContent(), true);

        // âœ… Validation des donnÃ©es requises
        if (empty($data['courrierArrive'])) {
            return $this->json([
                'code' => 400,
                'message' => 'L\'ID du courrier est requis'
            ], 400);
        }

        $courrierId = (int) $data['courrierArrive'];
        $sendServiceTraitant = filter_var($data['sendServiceTraitant'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendSmsServiceTraitant = filter_var($data['sendSmsServiceTraitant'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // ðŸ” VÃ©rification si au moins une notification est demandÃ©e
        if (!$sendServiceTraitant && !$sendSmsServiceTraitant) {
            return $this->json([
                'code' => 400,
                'message' => 'Au moins une méthode de notification doit être sélectionnée (email ou SMS)'
            ], 400);
        }

        try {
            // ðŸ“‹ RÃ©cupÃ©ration du courrier
            $courrier = $this->courrierRepository->find($courrierId);

            if (!$courrier) {
                return $this->json([
                    'code' => 404,
                    'message' => 'Courrier non trouvé'
                ], 404);
            }

            // ðŸ¢ VÃ©rification de l'existence du service traitant
            // $serviceTraitant = $courrier->getIdServiceTraitant();
            // if (!$serviceTraitant) {
            //     return $this->json([
            //         'code' => 400,
            //         'message' => 'Aucun service traitant assigné à ce courrier'
            //     ], 400);
            // }

            // 🏢 Récupération de la dernière transmission du courrier Alex
            $transmissions = $courrier->getTransmissions();

            if ($transmissions->isEmpty()) {
                return $this->json([
                    'code' => 400,
                    'message' => 'Aucune transmission trouvée pour ce courrier'
                ], 400);
            }

            // On trie pour avoir la plus récente en premier
            $derniereTransmission = $transmissions->filter(
                fn($t) => !$t->isDelete()
            )->last();

            if (!$derniereTransmission) {
                return $this->json([
                    'code' => 400,
                    'message' => 'Aucune transmission active trouvée pour ce courrier'
                ], 400);
            }

            $serviceTraitant = $derniereTransmission->getIdServiceDestinataire();

            if (!$serviceTraitant) {
                return $this->json([
                    'code' => 400,
                    'message' => 'Aucun service destinataire trouvé sur la dernière transmission'
                ], 400);
            }

            // ðŸ“¤ Envoi des notifications
            // $notificationResults = $this->sendServiceNotifications(
            //     $courrier,
            //     $sendServiceTraitant,
            //     $sendSmsServiceTraitant
            // );

            // 👥 Récupération des utilisateurs actifs du service traitant (Alex)
            $utilisateurs = $serviceTraitant->getUsers()->filter(
                fn($user) => !$user->isDelete() && $user->isActive()
            );

            if ($utilisateurs->isEmpty()) {
                return $this->json([
                    'code' => 400,
                    'message' => 'Aucun utilisateur actif trouvé dans le service "' . $serviceTraitant->getNom() . '"'
                ], 400);
            }

            // 📤 Envoi des notifications
            $notificationResults = $this->sendNotificationsToUsers(
                $courrier,
                $utilisateurs->toArray(),
                $sendServiceTraitant,
                $sendSmsServiceTraitant
            );

            return $this->json([
                'code' => 200,
                'message' => 'Notifications envoyées avec succès',
                'courrier_id' => $courrier->getId(),
                'service' => $serviceTraitant->getNom(),
                'utilisateurs_notifies' => count($utilisateurs), // ← ajouter cette ligne (alex)
                'notifications' => $notificationResults
            ], 200);

        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de l\'envoi des notifications de service', [
                'courrier_id' => $courrierId,
                'exception' => $e->getMessage()
            ]);

            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de l\'envoi des notifications : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GÃ¨re l'envoi des notifications au service traitant
     */
    // private function sendServiceNotifications(
    //     $courrier,
    //     bool $sendServiceTraitant,
    //     bool $sendSmsServiceTraitant
    // ): array {
    //     $results = [
    //         'emailSentToService' => false,
    //         'smsSentToService' => false,
    //         'emailMessage' => '',
    //         'smsMessage' => ''
    //     ];

    //     $serviceTraitant = $courrier->getIdServiceTraitant();

    //     try {
    //         // ðŸ“§ ENVOI EMAIL
    //         if ($sendServiceTraitant) {
    //             if (!empty($serviceTraitant->getEmailService())) {
    //                 $this->sendEmailToService($courrier);
    //                 $results['emailSentToService'] = true;
    //                 $results['emailMessage'] = 'Email envoyé avec succès';
    //             } else {
    //                 $results['emailMessage'] = 'Aucune adresse email configurée pour le service';
    //                 $this->logger?->warning('Email service demandé mais adresse manquante', [
    //                     'courrier_id' => $courrier->getId(),
    //                     'service' => $serviceTraitant->getNom()
    //                 ]);
    //             }
    //         } else {
    //             $results['emailMessage'] = 'Email non demandé';
    //         }

    //         // ðŸ“± ENVOI SMS
    //         if ($sendSmsServiceTraitant) {
    //             if (!empty($serviceTraitant->getTelephone())) {
    //                 $smsResult = $this->sendSmsToService($courrier);
    //                 $results['smsSentToService'] = $smsResult['success'];
    //                 $results['smsMessage'] = $smsResult['success'] ? 'SMS envoyé avec succès' : $smsResult['message'];
    //             } else {
    //                 $results['smsMessage'] = 'Aucun numéro de téléphone configuré pour le service';
    //                 $this->logger?->warning('SMS service demandé mais numéro manquant', [
    //                     'courrier_id' => $courrier->getId(),
    //                     'service' => $serviceTraitant->getNom()
    //                 ]);
    //             }
    //         } else {
    //             $results['smsMessage'] = 'SMS non demandé';
    //         }

    //     } catch (\Exception $e) {
    //         $this->logger?->error('Erreur lors de l\'envoi des notifications au service', [
    //             'courrier_id' => $courrier->getId(),
    //             'exception' => $e->getMessage()
    //         ]);

    //         if ($sendServiceTraitant && empty($results['emailMessage'])) {
    //             $results['emailMessage'] = 'Erreur lors de l\'envoi de l\'email';
    //         }
    //         if ($sendSmsServiceTraitant && empty($results['smsMessage'])) {
    //             $results['smsMessage'] = 'Erreur lors de l\'envoi du SMS';
    //         }
    //     }

    //     return $results;
    // }

    /**
     * Envoie un email de notification au service traitant
     */
    // private function sendEmailToService($courrier): void
    // {
    //     $subject = "Notification courrier - nÂ° {$courrier->getNumero()}";
        
    //     $htmlContent = "
    //         <h2>Notification de courrier</h2>
    //         <p>Bonjour,</p>
    //         <p>Voici une notification concernant le courrier suivant :</p>
    //         <ul>
    //             <li><strong>Numéro :</strong> {$courrier->getNumero()}</li>
    //             <li><strong>Référence :</strong> {$courrier->getReference()}</li>
    //             <li><strong>Objet :</strong> {$courrier->getObjet()}</li>
    //             <li><strong>Expéditeur :</strong> {$courrier->getCivilite()} {$courrier->getNom()}</li>
    //             <li><strong>Priorité :</strong> {$courrier->getPriorite()}</li>
    //             <li><strong>Statut :</strong> {$courrier->getStatut()}</li>
    //             <li><strong>Date d'arrivée :</strong> {$courrier->getDateArrivee()?->format('d/m/Y')}</li>
    //             <li><strong>Commentaire :</strong> {$courrier->getCommentaire()}</li>
    //         </ul>
    //         <p>Merci de prendre les mesures appropriées concernant ce courrier.</p>
    //         <p>Cordialement,<br>MINEPIA</p>
    //     ";

    //     $this->mailService->sendEmail(
    //         $courrier->getIdServiceTraitant()->getEmailService(),
    //         $subject,
    //         $htmlContent
    //     );
    // }

    /**
     * Envoie un SMS de notification au service traitant
     */
    // private function sendSmsToService($courrier): array
    // {
    //     $serviceName = $courrier->getIdServiceTraitant()?->getNom() ?? 'Service';
    //     $message = "MINEPIA: Notification courrier n°{$courrier->getNumero()} de {$courrier->getCivilite()} {$courrier->getNom()}. Objet: {$courrier->getObjet()}. Statut: {$courrier->getStatut()}.\nMINEPIA: Mail no {$courrier->getNumero()} from {$courrier->getCivilite()} {$courrier->getNom()}. Subject: {$courrier->getObjet()}. Status: {$courrier->getStatut()}.";
        
        
    //     return $this->smsService->sendSms(
    //         $courrier->getIdServiceTraitant()->getTelephone(),
    //         $message,
    //         true // normalize
    //     );
    // }


    private function sendNotificationsToUsers(
    $courrier,
    array $utilisateurs,
    bool $sendEmail,
    bool $sendSms
    ): array {
        $results = [
            'emailsSent'   => 0,
            'emailsFailed' => 0,
            'smsSent'      => 0,
            'smsFailed'    => 0,
            'details'      => []
        ];

        foreach ($utilisateurs as $user) {
            $userDetail = [
                'utilisateur' => $user->getLastName() . ' ' . $user->getFirstName(),
                'email'       => null,
                'sms'         => null,
            ];

            // EMAIL
            if ($sendEmail) {
                $emailUser = $user->getEmail();
                if (!empty($emailUser)) {
                    try {
                        $this->sendEmailToUser($courrier, $emailUser, $user);
                        $results['emailsSent']++;
                        $userDetail['email'] = 'Envoyé à ' . $emailUser;
                    } catch (\Exception $e) {
                        $results['emailsFailed']++;
                        $userDetail['email'] = 'Échec : ' . $e->getMessage();
                    }
                } else {
                    $results['emailsFailed']++;
                    $userDetail['email'] = 'Aucun email configuré pour cet utilisateur';
                }
            }

            // SMS
            if ($sendSms) {
                $phoneUser = $user->getPhone();
                if (!empty($phoneUser)) {
                    $smsResult = $this->sendSmsToUser($courrier, $phoneUser);
                    if ($smsResult['success']) {
                        $results['smsSent']++;
                        $userDetail['sms'] = 'Envoyé au ' . $phoneUser;
                    } else {
                        $results['smsFailed']++;
                        $userDetail['sms'] = 'Échec : ' . $smsResult['message'];
                    }
                } else {
                    $results['smsFailed']++;
                    $userDetail['sms'] = 'Aucun numéro configuré pour cet utilisateur';
                }
            }

            // $results['details'][] = $userDetail;
        }

        return $results;
    }

    private function sendEmailToUser($courrier, string $emailDestinataire, $user): void
    {
        $nomDestinataire = $user->getLastName() . ' ' . $user->getFirstName();
        $subject = "Notification courrier - n° {$courrier->getNumero()}";

        $htmlContent = "
            <h2>Notification de courrier</h2>
            <p>Bonjour {$nomDestinataire},</p>
            <p>Voici une notification concernant le courrier suivant adressé à votre service :</p>
            <ul>
                <li><strong>Numéro :</strong> {$courrier->getNumero()}</li>
                <li><strong>Référence :</strong> {$courrier->getReference()}</li>
                <li><strong>Objet :</strong> {$courrier->getObjet()}</li>
                <li><strong>Expéditeur :</strong> {$courrier->getCivilite()} {$courrier->getNom()}</li>
                <li><strong>Priorité :</strong> {$courrier->getPriorite()}</li>
                <li><strong>Statut :</strong> {$courrier->getStatut()}</li>
                <li><strong>Date d'arrivée :</strong> {$courrier->getDateArrivee()?->format('d/m/Y')}</li>
                <li><strong>Commentaire :</strong> {$courrier->getCommentaire()}</li>
            </ul>
            <p>Merci de prendre les mesures appropriées concernant ce courrier.</p>
            <p>Cordialement,<br>MINEPIA</p>
        ";

        $this->mailService->sendEmail($emailDestinataire, $subject, $htmlContent);
    }

    private function sendSmsToUser($courrier, string $phone): array
    {
        $message = "MINEPIA: Notification courrier n°{$courrier->getNumero()} de {$courrier->getCivilite()} {$courrier->getNom()}. Objet: {$courrier->getObjet()}. Statut: {$courrier->getStatut()}.";

        return $this->smsService->sendSms(
            $phone,
            $message,
            true
        );
    }
}
