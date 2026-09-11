<?php

namespace App\Controller\Core\CourrierDepart;

use App\Entity\Cour\CourrierDepart;
use App\Entity\Cour\PieceJointe;
use App\Repository\Cour\CourrierInterneRepository;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Core\UserRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FileService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\MailService;
use App\Service\SmsService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;
use Twig\Environment;

#[OA\Tag(name: "CourrierDepart")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FileService $fileService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private CourrierRepository $courrierRepository,
        private CourrierInterneRepository $courrierInterneRepository,
        private UserRepository $userRepository,
        private CorrespondantRepository $correspondantRepository,
        private MailService $mailService,
        private SmsService $smsService,
        private UserActionLoggerService $actionLogger,
        private Environment $twig,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/courrier-depart', name: 'app_core_courrier_depart_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/courrier-depart',
        summary: 'Créer un nouveau courrier de départ avec document et piéces jointes',
        description: 'Ajoute un courrier de départ dans le système avec son document principal, ses piéces jointes et notifications par email et SMS optionnelles.',
        tags: ['CourrierDepart'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'idCourrier', type: 'integer', example: 1, description: 'ID du courrier entrant (optionnel)'),
                        new OA\Property(property: 'idCourrierInterne', type: 'integer', example: 1, description: 'ID du courrier interne (optionnel)'),
                        new OA\Property(property: 'dateSignature', type: 'string', format: 'date', example: '2025-02-14'),
                        new OA\Property(property: 'typeCourrier', type: 'string', example: 'Lettre officielle'),
                        new OA\Property(property: 'commentaire', type: 'string', example: 'Courrier urgent'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent', description: 'Classe du courrier (ex: Urgent, Normal, Confidentiel)'),
                        new OA\Property(property: 'categorie', type: 'string', example: 'Administrative', description: 'Catégorie du courrier de départ'),
                        new OA\Property(property: 'idSignataire', type: 'integer', example: 2),
                        new OA\Property(property: 'destinataire', type: 'integer', example: 5, description: 'ID du correspondant destinataire'),
                        new OA\Property(property: 'numeroReference', type: 'string', example: 'CD-2025-001'),
                        new OA\Property(property: 'numeroActe', type: 'string', example: 'ACTE-2025-001', description: 'Numéro d\'acte du courrier de départ (optionnel)'),
                        new OA\Property(property: 'email', type: 'string', example: 'destinataire@example.com', description: 'Adresse email du destinataire (optionnel, stocké en BDD)'),
                        new OA\Property(property: 'numeroTelephone', type: 'string', example: '+237612345678', description: 'Numéro de téléphone du destinataire (optionnel, stocké en BDD)'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2, description: 'Nombre de pièces jointes (simple champ entré par l\'utilisateur, aucune vérification)'),
                        new OA\Property(
                            property: 'provenancesCopie',
                            type: 'string',
                            example: '1,2,3',
                            description: 'IDs des correspondants en copie séparés par virgules (optionnel)'
                        ),
                        
                        // ðŸ“§ PARAMÃˆTRES EMAIL
                        new OA\Property(property: 'sendMail', type: 'boolean', example: true, description: 'Envoyer un email au destinataire et au service traitant (si courrier lié)'),
                        
                        // ðŸ“± PARAMÃˆTRES SMS
                        new OA\Property(property: 'sendSms', type: 'boolean', example: false, description: 'Envoyer un SMS au destinataire et au service traitant du courrier lié'),
                        
                        // ðŸ“‚ FICHIERS
                        new OA\Property(property: 'document', type: 'string', format: 'binary', description: 'Document principal du courrier de départ (PDF, Word, etc.)'),
                        new OA\Property(
                            property: 'piecesJointes[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'Pièces jointes additionnelles'
                        ),
                        new OA\Property(
                            property: 'intitulesPiecesJointes',
                            type: 'string',
                            example: '["Rapport financier", "Justificatif", "Annexe"]',
                            description: 'Intitulés des pièces jointes. Formats acceptés: JSON array, séparation par retour à la ligne, par "|||" ou par ";"'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Courrier de départ crée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier de départ crée avec succès.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'numeroReference', type: 'string', example: 'CD-2025-001'),
                                new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                                new OA\Property(property: 'categorie', type: 'string', example: 'Administrative'),
                                new OA\Property(property: 'dateSignature', type: 'string', format: 'date', example: '2025-02-14'),
                                new OA\Property(property: 'document', type: 'string', example: '/uploads/courrier_depart/document/65ff44c4a8b1f.pdf'),
                                new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2),
                                new OA\Property(property: 'provenancesCopie', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                                new OA\Property(property: 'piecesJointesCount', type: 'integer', example: 2),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                                new OA\Property(
                                    property: 'notifications',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'emailSentToDestinataire', type: 'boolean', example: true, description: 'Email envoyé au destinataire (correspondant)'),
                                        new OA\Property(property: 'emailSentToServiceTraitant', type: 'boolean', example: true, description: 'Email envoyé au service traitant du courrier lié'),
                                        new OA\Property(property: 'emailSentToCourrierDepartEmail', type: 'boolean', example: true, description: 'Email envoyé à l\'adresse renseignée dans le courrier de départ'),
                                        new OA\Property(property: 'smsSentToDestinataire', type: 'boolean', example: true, description: 'SMS envoyé au destinataire (correspondant)'),
                                        new OA\Property(property: 'smsSentToServiceTraitant', type: 'boolean', example: false, description: 'SMS envoyé au service traitant du courrier lié'),
                                        new OA\Property(property: 'smsSentToCourrierDepartNumero', type: 'boolean', example: true, description: 'SMS envoyé au numéro renseigné dans le courrier de départ'),
                                    ]
                                ),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PostCourrierDepart');

        // RÃ©cupÃ©rer les donnÃ©es du formulaire et les fichiers
        $data = array_merge($request->request->all(), $request->files->all());

        // RÃ©cupÃ©ration des paramÃ¨tres d'envoi (non sauvegardÃ©s en base)
        $sendMail = filter_var($data['sendMail'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendSms = filter_var($data['sendSms'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $data = $this->functionService->excludeFields($data, [
            'createdAt', 'updatedAt', 'sendMail', 'sendSms'
        ]);

        try {
            $courrierDepart = new CourrierDepart();

            // Relations optionnelles
            $courrier = null;
            $courrierInterne = null;
            $destinataire = null;
            $signataire = null;

            if (!empty($data['idCourrier'])) {
                $courrier = $this->courrierRepository->find($data['idCourrier']);
                if ($courrier) {
                    $courrierDepart->setIdCourrier($courrier);
                }
            }

            if (!empty($data['idCourrierInterne'])) {
                $courrierInterne = $this->courrierInterneRepository->find($data['idCourrierInterne']);
                if ($courrierInterne) {
                    $courrierDepart->setIdCourrierInterne($courrierInterne);
                }
            }

            if (!empty($data['idSignataire'])) {
                $signataire = $this->userRepository->find($data['idSignataire']);
                if ($signataire) {
                    $courrierDepart->setIdSignataire($signataire);
                }
            }

            if (!empty($data['destinataire'])) {
                $destinataire = $this->correspondantRepository->find($data['destinataire']);
                if ($destinataire) {
                    $courrierDepart->setDestinataire($destinataire);
                }
            }

            // ðŸ“‚ Upload du document principal
            if ($request->files->get('document')) {
                $filePath = $this->fileService->uploadFile(
                    $this->getParameter('app_uploads_courrier_depart_directory'),
                    $request->files->get('document')
                );
                if ($filePath) {
                    $data['document'] = $this->getParameter('app_uploads_courrier_depart') . $filePath;
                }
            }

            // âœ… Gestion de provenancesCopie (format: "1,2,3" â†’ [1, 2, 3])
            $provenancesCopie = null;
            if (isset($data['provenancesCopie']) && !empty($data['provenancesCopie'])) {
                if (is_string($data['provenancesCopie'])) {
                    $ids = array_map('intval', array_filter(explode(',', $data['provenancesCopie'])));
                    $provenancesCopie = !empty($ids) ? $ids : null;
                }
            }

            // ðŸ’¾ Sauvegarde du courrier de dÃ©part
            $courrierDepart = $this->crudService->postEntity($courrierDepart, [
                'dateSignature' => $data['dateSignature'] ?? null,
                'typeCourrier' => $data['typeCourrier'] ?? null,
                'commentaire' => $data['commentaire'] ?? null,
                'classeCourrier' => $data['classeCourrier'] ?? null,
                'categorie' => $data['categorie'] ?? null,
                'document' => $data['document'] ?? null,
                'numeroReference' => !empty($data['numeroReference']) ? trim($data['numeroReference']) : null,
                'numeroActe' => $data['numeroActe'] ?? null,
                'email' => $data['email'] ?? null,
                'numeroTelephone' => $data['numeroTelephone'] ?? null,
                'nombrePieceJointe' => isset($data['nombrePieceJointe']) ? (int)$data['nombrePieceJointe'] : 0,
                'provenancesCopie' => $provenancesCopie, // âœ… JSON array
            ]);

            // ðŸ“Ž Gestion des piÃ¨ces jointes multiples
            $uploadedCount = 0;
            
            // RÃ©cupÃ©rer les intitulÃ©s (optionnel)
            $intitulesInput = $request->request->get('intitulesPiecesJointes', '');
            $intitules = $this->parseIntitules($intitulesInput);
            
            if (!empty($request->files->get('piecesJointes'))) {
                foreach ($request->files->get('piecesJointes') as $index => $file) {
                    $filePath = $this->fileService->uploadFile(
                        $this->getParameter('app_uploads_courrier_depart_piece_directory'),
                        $file
                    );

                    if ($filePath) {
                        $piece = new PieceJointe();
                        $piece->setNom($file->getClientOriginalName());
                        $piece->setIntitule($intitules[$index] ?? null);
                        $piece->setChemin($this->getParameter('app_uploads_courrier_depart_piece') . $filePath);
                        $piece->setType($file->getClientMimeType());
                        $piece->setIdParent($courrierDepart->getId());
                        $piece->setTypeParent('CourrierDepart');

                        $this->crudService->postEntity($piece, []);
                        $uploadedCount++;
                    }
                }
            }

            // ðŸ†• GESTION DES NOTIFICATIONS (EMAILS + SMS)
            $notificationResults = $this->handleAllNotifications(
                $courrierDepart, 
                $destinataire, 
                $courrier, 
                $sendMail, 
                $sendSms
            );

            $responseData = [
                'id' => $courrierDepart->getId(),
                'numeroReference' => $courrierDepart->getNumeroReference(),
                'numeroActe' => $courrierDepart->getNumeroActe(),
                'classeCourrier' => $courrierDepart->getClasseCourrier(),
                'categorie' => $courrierDepart->getCategorie(),
                'dateSignature' => $courrierDepart->getDateSignature()?->format('Y-m-d'),
                'document' => $courrierDepart->getDocument(),
                'email' => $courrierDepart->getEmail(),
                'numeroTelephone' => $courrierDepart->getNumeroTelephone(),
                'nombrePieceJointe' => $courrierDepart->getNombrePieceJointe(),
                'provenancesCopie' => $courrierDepart->getProvenancesCopie(), // âœ…
                'piecesJointesCount' => $uploadedCount,
                'createdAt' => $courrierDepart->getCreatedAt()?->format('Y-m-d H:i:s'),
                'notifications' => $notificationResults,
            ];

            // Logger la crÃ©ation avec TOUTES les donnÃ©es
            $this->actionLogger->logCreate(
                'CourrierDepart',
                $courrierDepart->getId(),
                'Création d\'un nouveau courrier de départ',
                [
                    'courrier' => $responseData,
                    'request_data' => $this->functionService->excludeFields($request->request->all(), ['document', 'piecesJointes']),
                    'uploaded_files' => [
                        'document' => $request->files->get('document')?->getClientOriginalName(),
                        'pieces_jointes_count' => $uploadedCount
                    ]
                ]
            );

            return $this->json([
                'code' => 201,
                'message' => 'Courrier de départ créé avec succès.',
                'data' => $responseData
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GÃ¨re l'envoi de toutes les notifications (emails + SMS) lors de la crÃ©ation d'un courrier de dÃ©part
     */
    private function handleAllNotifications(
        CourrierDepart $courrierDepart, 
        $destinataire, 
        $courrier,
        bool $sendMail,
        bool $sendSms
    ): array {
        $results = [
            'emailSentToDestinataire' => false,
            'emailSentToServiceTraitant' => false,
            'emailSentToCourrierDepartEmail' => false,
            'smsSentToDestinataire' => false,
            'smsSentToServiceTraitant' => false,
            'smsSentToCourrierDepartNumero' => false,
        ];

        try {
            // ðŸ“§ EMAILS
            if ($sendMail) {
                // 1. Email au destinataire (si email renseignÃ© dans le correspondant)
                if ($destinataire && !empty($destinataire->getEmail())) {
                    $this->sendEmailToDestinataire($courrierDepart, $destinataire, $courrier);
                    $results['emailSentToDestinataire'] = true;
                }

                // 2. Email renseignÃ© directement dans le courrier de dÃ©part (si diffÃ©rent du destinataire)
                if (!empty($courrierDepart->getEmail())) {
                    $emailCourrierDepart = $courrierDepart->getEmail();
                    // Ã‰viter l'envoi en double si c'est le mÃªme email que le destinataire
                    if (!$destinataire || $emailCourrierDepart !== $destinataire->getEmail()) {
                        $this->sendEmailToCourrierDepartEmail($courrierDepart, $emailCourrierDepart, $courrier);
                        $results['emailSentToCourrierDepartEmail'] = true;
                    }
                }

                // 3. Email au service traitant du courrier entrant (si courrier liÃ© et email service renseignÃ©)
                if ($courrier && $courrier->getIdServiceTraitant() && !empty($courrier->getIdServiceTraitant()->getEmailService())) {
                    $this->sendEmailToServiceTraitant($courrierDepart, $courrier);
                    $results['emailSentToServiceTraitant'] = true;
                }
            }

            // ðŸ“± SMS avec validation intÃ©grÃ©e (ne bloque pas la crÃ©ation)
            if ($sendSms) {
                // 1. SMS au destinataire (si numÃ©ro de tÃ©lÃ©phone renseignÃ© dans le correspondant)
                if ($destinataire && !empty($destinataire->getTelephone())) {
                    $smsResult = $this->sendSmsToDestinataire($courrierDepart, $destinataire, $courrier);
                    $results['smsSentToDestinataire'] = $smsResult['success'];
                } else {
                    $this->logger?->warning('SMS destinataire demandé mais numéro manquant pour courrier de départ', [
                        'courrier_depart_id' => $courrierDepart->getId(),
                        'destinataire_id' => $destinataire?->getId()
                    ]);
                }

                // 2. SMS au numÃ©ro renseignÃ© directement dans le courrier de dÃ©part (si diffÃ©rent du destinataire)
                if (!empty($courrierDepart->getNumeroTelephone())) {
                    $numeroCourrierDepart = $courrierDepart->getNumeroTelephone();
                    // Ã‰viter l'envoi en double si c'est le mÃªme numÃ©ro que le destinataire
                    if (!$destinataire || $numeroCourrierDepart !== $destinataire->getTelephone()) {
                        $smsResult = $this->sendSmsToCourrierDepartNumero($courrierDepart, $numeroCourrierDepart, $courrier);
                        $results['smsSentToCourrierDepartNumero'] = $smsResult['success'];
                    }
                }

                // 3. SMS au service traitant du courrier entrant (si courrier liÃ© et tÃ©lÃ©phone service renseignÃ©)
                if ($courrier && $courrier->getIdServiceTraitant() && !empty($courrier->getIdServiceTraitant()->getTelephone())) {
                    $smsResult = $this->sendSmsToServiceTraitant($courrierDepart, $courrier);
                    $results['smsSentToServiceTraitant'] = $smsResult['success'];
                } else {
                    $this->logger?->warning('SMS service demandé mais numéro manquant pour courrier de départ', [
                        'courrier_depart_id' => $courrierDepart->getId(),
                        'courrier_lie_id' => $courrier?->getId(),
                        'service_traitant' => $courrier?->getIdServiceTraitant()?->getNom()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas Ã©chouer la crÃ©ation du courrier de dÃ©part
            $this->logger?->error('Erreur lors de l\'envoi des notifications pour courrier de départ', [
                'courrier_depart_id' => $courrierDepart->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * ðŸ“± Envoie un SMS de notification au destinataire du courrier de dÃ©part
     */
    private function sendSmsToDestinataire(CourrierDepart $courrierDepart, $destinataire, $courrier = null): array
    {
        // Message optimisÃ© pour tenir dans 160 caractÃ¨res avec le lien complet
        $numero = $courrierDepart->getNumeroActe() ?? $courrierDepart->getNumeroReference() ?? 'N/A';
        
        // Si le courrier de dÃ©part est liÃ© Ã  un courrier entrant (c'est une rÃ©ponse)
        if ($courrier) {
            $message = "votre courrier n°{$courrier->getNumero()} a recu une reponse n°{$numero}. consultez: https://minepia.cm/site/consultez-vos-dossiers/\nMerci.\nYour mail no {$courrier->getNumero()} has received reply no {$numero}. See: https://minepia.cm/site/consultez-vos-dossiers/\nThank you.";
        } else {
            $message = "votre courrier a été signé. pour plus de détails visitez: https://minepia.cm/site/consultez-vos-dossiers/\nMerci.\nYour Mail has been signed. See: https://minepia.cm/site/consultez-vos-dossiers/\nThank you.";
        }
        
        return $this->smsService->sendSms(
            $destinataire->getTelephone(),
            $message,
            true // normalize
        );
    }

    /**
     * ðŸ“± Envoie un SMS de notification au service traitant du courrier entrant liÃ©
     */
    private function sendSmsToServiceTraitant(CourrierDepart $courrierDepart, $courrier): array
    {
        // Message optimisÃ© pour tenir dans 160 caractÃ¨res avec le lien complet
        $numeroDepart = $courrierDepart->getNumeroActe() ?? $courrierDepart->getNumeroReference() ?? 'N/A';
        $numeroCourrier = $courrier->getNumero();
        
        $message = "reponse n°{$numeroDepart} au courrier n°{$numeroCourrier} a ete signee. consultez: https://minepia.cm/site/consultez-vos-dossiers/\nMerci.\nReply no {$numeroDepart} to mail no {$numeroCourrier} has been signed. See: https://minepia.cm/site/consultez-vos-dossiers/\nThank you.";
        
        return $this->smsService->sendSms(
            $courrier->getIdServiceTraitant()->getTelephone(),
            $message,
            true // normalize
        );
    }

    /**
     * ðŸ“§ Envoie un email Ã  l'adresse email renseignÃ©e directement dans le courrier de dÃ©part
     */
    private function sendEmailToCourrierDepartEmail(CourrierDepart $courrierDepart, string $email, $courrier = null): void
    {
        $numeroAffichage = $courrierDepart->getNumeroActe() ?? $courrierDepart->getNumeroReference() ?? 'N/A';
        $subject = "Courrier de départ - {$numeroAffichage}";
        
        // Utilise le mÃªme template stylisÃ© que pour les destinataires
        $htmlContent = $this->twig->render('emails/courrier_depart/notification_destinataire.html.twig', [
            'courrierDepart' => $courrierDepart,
            'destinataire' => null, // Pas de destinataire associÃ©, juste une adresse email
            'courrier' => $courrier,
        ]);

        $this->mailService->sendEmail($email, $subject, $htmlContent);
    }

    /**
     * ðŸ“± Envoie un SMS au numÃ©ro renseignÃ© directement dans le courrier de dÃ©part
     */
    private function sendSmsToCourrierDepartNumero(CourrierDepart $courrierDepart, string $numeroTelephone, $courrier = null): array
    {
        // Message optimisÃ© pour tenir dans 160 caractÃ¨res avec le lien complet
        $numero = $courrierDepart->getNumeroActe() ?? $courrierDepart->getNumeroReference() ?? 'N/A';
        
        // Si le courrier de dÃ©part est liÃ© Ã  un courrier entrant (c'est une rÃ©ponse)
        if ($courrier) {
            $message = "votre courrier n°{$courrier->getNumero()} a recu une reponse n°{$numero}. consultez: https://minepia.cm/site/consultez-vos-dossiers/\nMerci.\nYour mail no {$courrier->getNumero()} has received reply no {$numero}. See: https://minepia.cm/site/consultez-vos-dossiers/\nThank you.";
        } else {
            $message = "courrier n°{$numero} vous concernant a ete signe. consultez: https://minepia.cm/site/consultez-vos-dossiers/\nMerci.\nMail no {$numero} concerning you has been signed. See: https://minepia.cm/site/consultez-vos-dossiers/\nThank you.";
        }
        
        return $this->smsService->sendSms(
            $numeroTelephone,
            $message,
            true // normalize
        );
    }

    /**
     * Envoie un email de notification au destinataire du courrier de dÃ©part
     */
    private function sendEmailToDestinataire(CourrierDepart $courrierDepart, $destinataire, $courrier = null): void
    {
        $numeroAffichage = $courrierDepart->getNumeroActe() ?? $courrierDepart->getNumeroReference() ?? 'N/A';
        $subject = "Courrier de départ - {$numeroAffichage}";
        
        $htmlContent = $this->twig->render('emails/courrier_depart/notification_destinataire.html.twig', [
            'courrierDepart' => $courrierDepart,
            'destinataire' => $destinataire,
            'courrier' => $courrier,
        ]);

        $this->mailService->sendEmail($destinataire->getEmail(), $subject, $htmlContent);
    }

    /**
     * Envoie un email de notification au service traitant du courrier entrant
     */
    private function sendEmailToServiceTraitant(CourrierDepart $courrierDepart, $courrier): void
    {
        $numeroAffichage = $courrierDepart->getNumeroActe() ?? $courrierDepart->getNumeroReference() ?? 'N/A';
        $subject = "Courrier de départ signé - {$numeroAffichage}";
        
        $htmlContent = $this->twig->render('emails/courrier_depart/notification_service_traitant.html.twig', [
            'courrierDepart' => $courrierDepart,
            'courrier' => $courrier,
        ]);

        $this->mailService->sendEmail($courrier->getIdServiceTraitant()->getEmailService(), $subject, $htmlContent);
    }

    /**
     * Analyse une chaÃ®ne d'intitulÃ©s dans diffÃ©rents formats supportÃ©s
     * 
     * Formats acceptÃ©s :
     * - JSON array: ["Titre1", "Titre2"]
     * - SÃ©parÃ©s par retour Ã  la ligne: "Titre1\nTitre2"
     * - SÃ©parÃ©s par |||: "Titre1|||Titre2"
     * - SÃ©parÃ©s par point-virgule: "Titre1;Titre2"
     */
    private function parseIntitules(string $input): array
    {
        if (empty(trim($input))) {
            return [];
        }

        // Tenter de dÃ©coder comme JSON
        $decoded = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_map('trim', $decoded);
        }

        // VÃ©rifier sÃ©parateur |||
        if (strpos($input, '|||') !== false) {
            return array_map('trim', explode('|||', $input));
        }

        // VÃ©rifier sÃ©parateur point-virgule
        if (strpos($input, ';') !== false) {
            return array_map('trim', explode(';', $input));
        }

        // Par dÃ©faut, sÃ©parer par retour Ã  la ligne
        return array_map('trim', preg_split('/\r\n|\r|\n/', $input));
    }
}
