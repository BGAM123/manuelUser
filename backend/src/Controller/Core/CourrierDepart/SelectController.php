<?php

namespace App\Controller\Core\CourrierDepart;

use App\Repository\Cour\CourrierDepartRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\MailService;
use App\Service\SmsService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: "CourrierDepart")]
class SelectController extends AbstractController
{
    public function __construct(
        private CourrierDepartRepository $courrierDepartRepository,
        private CorrespondantRepository $correspondantRepository,
        private AccessCheckerService $accessChecker,
        private MailService $mailService,
        private SmsService $smsService,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/courrier-depart/{id<\d+>}/select', name: 'app_core_courrier_depart_select', methods: ['POST'])]
    #[OA\Post(
        path: '/core/courrier-depart/{id}/select',
        summary: 'Sélectionner un courrier de départ avec notifications',
        description: 'Sélectionne un courrier de départ et envoie optionnellement des notifications par email et SMS au destinataire et au service traitant du courrier lié.',
        tags: ['CourrierDepart'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier de départ à sélectionner',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    // ðŸ“§ PARAMÃˆTRE EMAIL
                    new OA\Property(property: 'sendMail', type: 'boolean', example: true, description: 'Envoyer un email de sélection au destinataire et au service traitant (si courrier lié)'),
                    
                    // ðŸ“± PARAMÃˆTRE SMS
                    new OA\Property(property: 'sendSms', type: 'boolean', example: false, description: 'Envoyer un SMS de sélection au destinataire et au service traitant du courrier lié'),
                    
                    // OPTIONNEL : Message personnalisé
                    new OA\Property(property: 'message', type: 'string', example: 'Courrier sélectionné pour traitement prioritaire', description: 'Message personnalisé (optionnel)')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier de départ sélectionné avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier de départ sélectionné avec succès.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'numeroReference', type: 'string', example: 'CD-2025-001'),
                                new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                                new OA\Property(property: 'categorie', type: 'string', example: 'Administrative'),
                                new OA\Property(
                                    property: 'provenancesCopie',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'nom', type: 'string', example: 'Ministère de l\'Agriculture'),
                                        ]
                                    ),
                                    description: 'Liste des correspondants en copie avec leurs détails'
                                ),
                                new OA\Property(property: 'selectedAt', type: 'string', format: 'date-time', example: '2025-02-14 15:30:00'),
                                new OA\Property(
                                    property: 'notifications',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'emailSentToDestinataire', type: 'boolean', example: true),
                                        new OA\Property(property: 'emailSentToServiceTraitant', type: 'boolean', example: true),
                                        new OA\Property(property: 'smsSentToDestinataire', type: 'boolean', example: true),
                                        new OA\Property(property: 'smsSentToServiceTraitant', type: 'boolean', example: false),
                                    ]
                                ),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier de départ non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function select(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'SelectCourrierDepart');

        $courrierDepart = $this->courrierDepartRepository->createQueryBuilder('cd')
            ->leftJoin('cd.destinataire', 'd')
            ->leftJoin('cd.idSignataire', 's')
            ->leftJoin('cd.idCourrier', 'c')
            ->leftJoin('c.idServiceTraitant', 'st')
            ->addSelect('d', 's', 'c', 'st')
            ->where('cd.id = :id AND cd.isDelete = false')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$courrierDepart) {
            return $this->json(['code' => 404, 'message' => 'Courrier de départ non trouvé.'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Récupération des paramètres d'envoi
        $sendMail = filter_var($data['sendMail'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendSms = filter_var($data['sendSms'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $customMessage = $data['message'] ?? null;

        try {
            // ðŸ†• GESTION DES NOTIFICATIONS (EMAILS + SMS)
            $notificationResults = $this->handleAllNotifications(
                $courrierDepart,
                $sendMail,
                $sendSms,
                $customMessage
            );

            // âœ… Enrichir provenancesCopie avec id et nom des correspondants
            $provenancesCopieEnriched = [];
            if ($courrierDepart->getProvenancesCopie()) {
                $correspondants = $this->correspondantRepository->createQueryBuilder('c')
                    ->where('c.id IN (:ids)')
                    ->setParameter('ids', $courrierDepart->getProvenancesCopie())
                    ->getQuery()
                    ->getResult();

                foreach ($correspondants as $corr) {
                    $provenancesCopieEnriched[] = [
                        'id' => $corr->getId(),
                        'nom' => $corr->getNom(),
                    ];
                }
            }

            return $this->json([
                'code' => 200,
                'message' => 'Courrier de départ sélectionné avec succès.',
                'data' => [
                    'id' => $courrierDepart->getId(),
                    'numeroReference' => $courrierDepart->getNumeroReference(),
                    'classeCourrier' => $courrierDepart->getClasseCourrier(),
                    'categorie' => $courrierDepart->getCategorie(),
                    'dateSignature' => $courrierDepart->getDateSignature()?->format('Y-m-d'),
                    'provenancesCopie' => $provenancesCopieEnriched, 
                    'selectedAt' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'destinataire' => $courrierDepart->getDestinataire() ? [
                        'id' => $courrierDepart->getDestinataire()->getId(),
                        'nom' => $courrierDepart->getDestinataire()->getNom(),
                        'email' => $courrierDepart->getDestinataire()->getEmail(),
                        'telephone' => $courrierDepart->getDestinataire()->getTelephone(),
                    ] : null,
                    'courrier' => $courrierDepart->getIdCourrier() ? [
                        'id' => $courrierDepart->getIdCourrier()->getId(),
                        'numero' => $courrierDepart->getIdCourrier()->getNumero(),
                        'reference' => $courrierDepart->getIdCourrier()->getReference(),
                        'objet' => $courrierDepart->getIdCourrier()->getObjet(),
                        'serviceTraitant' => $courrierDepart->getIdCourrier()->getIdServiceTraitant()?->getNom(),
                    ] : null,
                    'notifications' => $notificationResults,
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GÃ¨re l'envoi de toutes les notifications (emails + SMS) lors de la sÃ©lection d'un courrier de dÃ©part
     */
    private function handleAllNotifications(
        $courrierDepart,
        bool $sendMail,
        bool $sendSms,
        ?string $customMessage = null
    ): array {
        $results = [
            'emailSentToDestinataire' => false,
            'emailSentToServiceTraitant' => false,
            'smsSentToDestinataire' => false,
            'smsSentToServiceTraitant' => false,
        ];

        try {
            $destinataire = $courrierDepart->getDestinataire();
            $courrier = $courrierDepart->getIdCourrier();

            // ðŸ“§ EMAILS
            if ($sendMail) {
                // 1. Email au destinataire (si email renseignÃ©)
                if ($destinataire && !empty($destinataire->getEmail())) {
                    $this->sendEmailToDestinataire($courrierDepart, $destinataire, $courrier, $customMessage);
                    $results['emailSentToDestinataire'] = true;
                }

                // 2. Email au service traitant du courrier entrant (si courrier liÃ© et email service renseignÃ©)
                if ($courrier && $courrier->getIdServiceTraitant() && !empty($courrier->getIdServiceTraitant()->getEmailService())) {
                    $this->sendEmailToServiceTraitant($courrierDepart, $courrier, $customMessage);
                    $results['emailSentToServiceTraitant'] = true;
                }
            }

            // ðŸ“± SMS avec validation intÃ©grÃ©e (ne bloque pas la sÃ©lection)
            if ($sendSms) {
                // 1. SMS au destinataire (si numÃ©ro de tÃ©lÃ©phone renseignÃ©)
                if ($destinataire && !empty($destinataire->getTelephone())) {
                    $smsResult = $this->sendSmsToDestinataire($courrierDepart, $destinataire, $courrier, $customMessage);
                    $results['smsSentToDestinataire'] = $smsResult['success'];
                } else {
                    $this->logger?->warning('SMS destinataire demandé mais numéro manquant lors de la sélection courrier de départ', [
                        'courrier_depart_id' => $courrierDepart->getId(),
                        'destinataire_id' => $destinataire?->getId()
                    ]);
                }

                // 2. SMS au service traitant du courrier entrant (si courrier liÃ© et tÃ©lÃ©phone service renseignÃ©)
                if ($courrier && $courrier->getIdServiceTraitant() && !empty($courrier->getIdServiceTraitant()->getTelephone())) {
                    $smsResult = $this->sendSmsToServiceTraitant($courrierDepart, $courrier, $customMessage);
                    $results['smsSentToServiceTraitant'] = $smsResult['success'];
                } else {
                    $this->logger?->warning('SMS service demandé mais numéro manquant lors de la sélection courrier de départ', [
                        'courrier_depart_id' => $courrierDepart->getId(),
                        'courrier_lie_id' => $courrier?->getId(),
                        'service_traitant' => $courrier?->getIdServiceTraitant()?->getNom()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas Ã©chouer la sÃ©lection du courrier de dÃ©part
            $this->logger?->error('Erreur lors de l\'envoi des notifications pour sélection courrier de départ', [
                'courrier_depart_id' => $courrierDepart->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * ðŸ“± Envoie un SMS de notification de sÃ©lection au destinataire du courrier de dÃ©part
     */
    private function sendSmsToDestinataire($courrierDepart, $destinataire, $courrier = null, ?string $customMessage = null): array
    {
        if ($customMessage && mb_strlen($customMessage, 'UTF-8') <= 100) {
            $message = "MINEPIA: {$customMessage} - Courrier depart n°{$courrierDepart->getNumeroReference()}.\nMINEPIA: Outgoing mail no {$courrierDepart->getNumeroReference()} has been selected.";
        } else {
            if ($courrier) {
                $message = "MINEPIA: Courrier depart n°{$courrierDepart->getNumeroReference()} selectionne en reponse a votre courrier n°{$courrier->getNumero()}.\nMINEPIA: Outgoing mail no {$courrierDepart->getNumeroReference()} selected in response to your mail no {$courrier->getNumero()}.";
            } else {
                $message = "MINEPIA: Courrier depart n°{$courrierDepart->getNumeroReference()} vous concernant a ete selectionne pour traitement.\nMINEPIA: Outgoing mail no {$courrierDepart->getNumeroReference()} concerning you has been selected for processing.";
            }
        }
        
        
        return $this->smsService->sendSms(
            $destinataire->getTelephone(),
            $message,
            true // normalize
        );
    }

    /**
     * ðŸ“± Envoie un SMS de notification de sÃ©lection au service traitant du courrier entrant liÃ©
     */
    private function sendSmsToServiceTraitant($courrierDepart, $courrier, ?string $customMessage = null): array
    {
        if ($customMessage && mb_strlen($customMessage, 'UTF-8') <= 80) {
            $message = "MINEPIA: {$customMessage} - Courrier depart n°{$courrierDepart->getNumeroReference()} du courrier n°{$courrier->getNumero()}.\nMINEPIA: Outgoing mail no {$courrierDepart->getNumeroReference()} selected for mail no {$courrier->getNumero()}.";
        } else {
            $destinataireNom = $courrierDepart->getDestinataire() ? 
                "{$courrierDepart->getDestinataire()->getCivilite()} {$courrierDepart->getDestinataire()->getNom()}" : 
                'Destinataire';
            
            $message = "MINEPIA: Courrier depart n°{$courrierDepart->getNumeroReference()} selectionne en reponse au courrier n°{$courrier->getNumero()}. Destinataire: {$destinataireNom}.\nMINEPIA: Outgoing mail no {$courrierDepart->getNumeroReference()} selected in response to mail no {$courrier->getNumero()}. Recipient: {$destinataireNom}.";
        }
        
        
        return $this->smsService->sendSms(
            $courrier->getIdServiceTraitant()->getTelephone(),
            $message,
            true // normalize
        );
    }

    /**
     * Envoie un email de notification de sÃ©lection au destinataire
     */
    private function sendEmailToDestinataire($courrierDepart, $destinataire, $courrier = null, ?string $customMessage = null): void
    {
        $subject = "Courrier de départ sélectionné - {$courrierDepart->getNumeroReference()}";
        
        $htmlContent = "
            <h2>Notification de sélection de courrier de départ</h2>
            <p>Bonjour {$destinataire->getCivilite()} {$destinataire->getNom()},</p>
            <p>Un courrier de départ vous concernant a été sélectionné pour traitement :</p>
            <ul>
                <li><strong>Numéro de référence :</strong> {$courrierDepart->getNumeroReference()}</li>
                <li><strong>Type de courrier :</strong> {$courrierDepart->getTypeCourrier()}</li>
                <li><strong>Classe :</strong> {$courrierDepart->getClasseCourrier()}</li>";

        if ($courrierDepart->getCategorie()) {
            $htmlContent .= "
                <li><strong>CatÃ©gorie :</strong> {$courrierDepart->getCategorie()}</li>";
        }

        $htmlContent .= "
                <li><strong>Date de signature :</strong> {$courrierDepart->getDateSignature()?->format('d/m/Y')}</li>";

        if ($courrierDepart->getIdSignataire()) {
            $htmlContent .= "
                <li><strong>Signataire :</strong> {$courrierDepart->getIdSignataire()->getFirstname()} {$courrierDepart->getIdSignataire()->getLastname()}</li>";
        }

        if ($courrierDepart->getCommentaire()) {
            $htmlContent .= "
                <li><strong>Commentaire :</strong> {$courrierDepart->getCommentaire()}</li>";
        }

        // Si le courrier de dÃ©part est liÃ© Ã  un courrier entrant
        if ($courrier) {
            $htmlContent .= "
                <li><strong>En réponse au courrier :</strong> {$courrier->getReference()} - {$courrier->getObjet()}</li>";
        }

        $htmlContent .= "
            </ul>";

        // Message personnalisé si fourni
        if ($customMessage) {
            $htmlContent .= "
                <p><strong> Message :</strong> {$customMessage}</p>";
        }

        $htmlContent .= "
            <p><strong>Information :</strong> Ce courrier a été sélectionné et sera traité en priorité.</p>
            <p>Vous serez informé(e) de l'évolution du traitement de ce courrier.</p>
            <p>Pour toute information complémentaire, n'hésitez pas à nous contacter.</p>
            <p>Cordialement,<br>
            <strong>MINEPIA</strong><br>
            Ministère de l'Élevage, des Pêches et des Industries Animales</p>
        ";

        $this->mailService->sendEmail($destinataire->getEmail(), $subject, $htmlContent);
    }

    /**
     * Envoie un email de notification de sÃ©lection au service traitant
     */
    private function sendEmailToServiceTraitant($courrierDepart, $courrier, ?string $customMessage = null): void
    {
        $subject = "Sélection courrier de départ - {$courrierDepart->getNumeroReference()}";
        
        $htmlContent = "
            <h2>Notification de sélection de courrier de départ</h2>
            <p>Bonjour,</p>
            <p>Un courrier de départ liée à un courrier que vous traitez a été sélectionné :</p>
            
            <h3>Courrier de départ sélectionné :</h3>
            <ul>
                <li><strong>Numéro de référence :</strong> {$courrierDepart->getNumeroReference()}</li>
                <li><strong>Type de courrier :</strong> {$courrierDepart->getTypeCourrier()}</li>
                <li><strong>Classe :</strong> {$courrierDepart->getClasseCourrier()}</li>";

        if ($courrierDepart->getCategorie()) {
            $htmlContent .= "
                <li><strong>Catégorie :</strong> {$courrierDepart->getCategorie()}</li>";
        }

        $htmlContent .= "
                <li><strong>Date de signature :</strong> {$courrierDepart->getDateSignature()?->format('d/m/Y')}</li>";

        if ($courrierDepart->getDestinataire()) {
            $htmlContent .= "
                <li><strong>Destinataire :</strong> {$courrierDepart->getDestinataire()->getCivilite()} {$courrierDepart->getDestinataire()->getNom()}</li>";
        }

        if ($courrierDepart->getIdSignataire()) {
            $htmlContent .= "
                <li><strong>Signataire :</strong> {$courrierDepart->getIdSignataire()->getFirstname()} {$courrierDepart->getIdSignataire()->getLastname()}</li>";
        }

        if ($courrierDepart->getCommentaire()) {
            $htmlContent .= "
                <li><strong>Commentaire :</strong> {$courrierDepart->getCommentaire()}</li>";
        }

        $htmlContent .= "
            </ul>
            
            <h3>ðŸ“¥ Courrier entrant associÃ© :</h3>
            <ul>
                <li><strong>Numéro :</strong> {$courrier->getNumero()}</li>
                <li><strong>Référence :</strong> {$courrier->getReference()}</li>
                <li><strong>Objet :</strong> {$courrier->getObjet()}</li>
                <li><strong>Expéditeur :</strong> {$courrier->getCivilite()} {$courrier->getNom()}</li>
                <li><strong>Statut :</strong> {$courrier->getStatut()}</li>
                <li><strong>Date d'arrivée :</strong> {$courrier->getDateArrivee()?->format('d/m/Y')}</li>
            </ul>";

        // Message personnalisé si fourni
        if ($customMessage) {
            $htmlContent .= "
                <p><strong>Message :</strong> {$customMessage}</p>";
        }

        $htmlContent .= "
            <p><strong>Information :</strong> Cette réponse a été sélectionnée pour traitement prioritaire concernant le courrier en cours dans votre service.</p>
            <p>Le courrier de départ sera traité en priorité et vous serez informé de son évolution.</p>
            <p>Cordialement,<br>
            <strong>MINEPIA</strong><br>
            Service de Gestion du Courrier</p>
        ";

        $this->mailService->sendEmail($courrier->getIdServiceTraitant()->getEmailService(), $subject, $htmlContent);
    }
}
