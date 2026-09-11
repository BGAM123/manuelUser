<?php

namespace App\Controller\Core\Courrier;

use App\Entity\Core\Service;
use App\Entity\Core\Notification;
use App\Entity\Cour\Courrier;
use App\Entity\Cour\Transmission;
use App\Entity\Cour\PieceJointe;
use App\Repository\Core\TypeCourrierRepository;
use App\Repository\Core\UserRepository;
use App\Repository\Core\ServiceRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Repository\Cour\CourrierRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FileService;
use App\Service\Core\FunctionService;
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
use Twig\Environment;

#[OA\Tag(name: "CourrierArrive")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FileService $fileService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private CourrierRepository $courrierRepository,
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private CorrespondantRepository $correspondantRepository,
        private TypeCourrierRepository $typeCourrierRepository,
        private MailService $mailService,
        private SmsService $smsService,
        private UserActionLoggerService $actionLogger,
        private EntityManagerInterface $entityManager,
        private Environment $twig,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/courrier', name: 'app_core_courrier_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/courrier',
        summary: 'Créer un courrier entrant et sa première transmission',
        tags: ['CourrierArrive'],
        description: "Crée un courrier entrant avec son document principal, ses pièces jointes et la première transmission automatique avec notifications par email et SMS.
        
        ⚠️ RÈGLES IMPORTANTES : 
        - Le service traitant (`idServiceTraitant`) DOIT être un POSTE et non un SERVICE. Vérifiez que le champ `typeService` est 'poste'.
        - Vous ne pouvez PAS transmettre un courrier à VOTRE PROPRE poste/service.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'reference', type: 'string', example: 'CA-00045/2025'),
                        new OA\Property(property: 'objet', type: 'string', example: 'Demande de subvention'),
                        new OA\Property(property: 'commentaire', type: 'string', example: 'Lettre prioritaire'),
                        new OA\Property(property: 'priorite', type: 'string', example: 'Haute'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent', description: 'Classe du courrier'),
                        new OA\Property(property: 'categorie', type: 'string', example: 'Administrative', description: 'Catégorie du courrier (ex: Administrative, Technique, Financière, etc.)'),
                        new OA\Property(property: 'isConfidentiel', type: 'boolean', example: false, description: 'Marquer le courrier comme confidentiel'),
                        new OA\Property(property: 'dateArrivee', type: 'string', format: 'date', example: '2025-02-14'),
                        new OA\Property(property: 'dateEnregistrement', type: 'string', format: 'date', example: '2025-02-14'),
                        new OA\Property(property: 'idProvenance', type: 'integer', example: 5, description: 'ID du correspondant (provenance)'),
                        new OA\Property(property: 'idServiceTraitant', type: 'integer', example: 3, description: 'ID du service traitant'),
                        new OA\Property(property: 'idCreateur', type: 'integer', example: 2, description: 'ID du créateur (utilisateur connecté)'),
                        new OA\Property(property: 'typeCourrier', type: 'integer', example: 1, description: 'ID du type de courrier a enregistrer'),
                        new OA\Property(property: 'typeTransfert', type: 'string', example: 'Direct', description: 'Type de transfert initial'),
                        new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont', description: 'Nom du correspondant'),
                        new OA\Property(property: 'civilite', type: 'string', example: 'M.', description: 'Civilité (M., Mme, Mlle, Dr, Pr, etc.)'),
                        new OA\Property(property: 'matricule', type: 'string', example: 'EMP-00123', description: 'Matricule du correspondant'),
                        new OA\Property(property: 'telephone', type: 'string', example: '+237 6XX XXX XXX', description: 'Numéro de téléphone'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com', description: 'Adresse email'),
                        new OA\Property(property: 'adresse', type: 'string', example: '123 Rue de la Paix, Yaoundé', description: 'Adresse postale'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 3, description: 'Nombre de pièces jointes (valeur libre saisie par l\'utilisateur)'),
                        
                        // ðŸ“§ PARAMÃˆTRES EMAIL
                        new OA\Property(property: 'sendMail', type: 'boolean', example: false, description: 'Envoyer un email au correspondant'),
                        new OA\Property(property: 'sendServiceTraitant', type: 'boolean', example: false, description: 'Envoyer un email au service traitant'),
                        
                        // ðŸ“± PARAMÃˆTRES SMS (NOUVEAUX)
                        new OA\Property(property: 'sendSms', type: 'boolean', example: false, description: 'Envoyer un SMS au numéro de téléphone du correspondant'),
                        new OA\Property(property: 'sendSmsServiceTraitant', type: 'boolean', example: false, description: 'Envoyer un SMS au numéro du service traitant'),
                        
                        new OA\Property(property: 'document', type: 'string', format: 'binary', description: 'Document principal du courrier'),
                        new OA\Property(property: 'piecesJointes[]', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), description: 'Autres pièces jointes'),
                        new OA\Property(
                            property: 'intitulesPiecesJointes', 
                            type: 'string', 
                            example: '["Justificatif de domicile", "Copie carte identitÃ©", "CV"]', 
                            description: 'Intitulés des pièces jointes. Formats acceptés : 
1) Format JSON (recommandé) : ["Intitulé 1", "Intitulé 2", "Intitulé 3"]
2) Une ligne par intitulé : Intitulé 1\nIntitulé 2\nIntitulé 3
3) Séparé par ||| : "Intitulé 1|||Intitulé 2|||Intitulé 3"
L\'ordre doit correspondre à celui des piecesJointes[]'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Courrier crée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'reference', type: 'string', example: 'CA-00045/2025'),
                        new OA\Property(property: 'objet', type: 'string', example: 'Demande de subvention'),
                        new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                        new OA\Property(property: 'categorie', type: 'string', example: 'Administrative'),
                        new OA\Property(property: 'isConfidentiel', type: 'boolean', example: false),
                        new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont'),
                        new OA\Property(property: 'civilite', type: 'string', example: 'M.'),
                        new OA\Property(property: 'matricule', type: 'string', example: 'EMP-00123'),
                        new OA\Property(property: 'telephone', type: 'string', example: '+237 6XX XXX XXX'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                        new OA\Property(property: 'adresse', type: 'string', example: '123 Rue de la Paix, YaoundÃ©'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 3),
                        new OA\Property(property: 'document', type: 'string', example: '/uploads/courrier/document/65ff44c4a8b1f.pdf'),
                        new OA\Property(property: 'piecesJointes', type: 'array', items: new OA\Items(type: 'string', example: '/uploads/courrier/pieces/65ff44c4b6f2a.pdf')),
                        new OA\Property(
                            property: 'notifications',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'emailSentToCorrespondant', type: 'boolean', example: true),
                                new OA\Property(property: 'emailSentToService', type: 'boolean', example: true),
                                new OA\Property(property: 'smsSentToCorrespondant', type: 'boolean', example: true),
                                new OA\Property(property: 'smsSentToService', type: 'boolean', example: false),
                            ]
                        ),
                        new OA\Property(property: 'notificationsSent', type: 'integer', example: 5, description: 'Nombre de notifications créées pour les utilisateurs du service'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function data(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostCourrier');

        $data = array_merge($request->request->all(), $request->files->all());
        $this->functionService->validate($data);
        
        // ðŸ†• RÃ©cupÃ©ration des paramÃ¨tres d'envoi (non sauvegardÃ©s en base)
        // Par dÃ©faut, on envoie TOUJOURS les emails s'ils sont renseignÃ©s (changement de comportement)
        // L'utilisateur peut explicitement mettre Ã  false s'il ne veut pas envoyer
        $sendMail = filter_var($data['sendMail'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $sendServiceTraitant = filter_var($data['sendServiceTraitant'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $sendSms = filter_var($data['sendSms'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendSmsServiceTraitant = filter_var($data['sendSmsServiceTraitant'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        // ðŸ†• RÃ©cupÃ©ration du champ isConfidentiel
        $isConfidentiel = filter_var($data['isConfidentiel'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        // ðŸš« SUPPRIMER CES VALIDATIONS QUI BLOQUENT LA CRÃ‰ATION
        // if ($sendSms && empty($data['telephone'])) {
        //     return $this->json([
        //         'code' => 400, 
        //         'message' => 'Le numÃ©ro de tÃ©lÃ©phone du correspondant est requis pour l\'envoi de SMS'
        //     ], 400);
        // }

        $data = $this->functionService->excludeFields($data, [
            'createdAt', 'updatedAt', 
            'sendMail', 'sendServiceTraitant', 
            'sendSms', 'sendSmsServiceTraitant'
        ]);

        try {
            // 1ï¸âƒ£ CrÃ©ation du courrier
            $courrier = new Courrier();

            $data['statut'] = 'Transmis';
            $data['isConfidentiel'] = $isConfidentiel; // ðŸ†• Ajout du champ isConfidentiel
            // ðŸ”¹ VÃ©rification d'existence des entitÃ©s liÃ©es avant d'appeler le service
            $idCreateur = !empty($data['idCreateur']) ? $this->userRepository->find($data['idCreateur']) : null;
            $typeCourrier = !empty($data['typeCourrier']) ? $this->typeCourrierRepository->find($data['typeCourrier']) : null;
            $idProvenance = !empty($data['idProvenance']) ? $this->correspondantRepository->find($data['idProvenance']) : null;
            $idServiceTraitant = !empty($data['idServiceTraitant']) ? $this->serviceRepository->find($data['idServiceTraitant']) : null;
            
            // âœ… Validation : VÃ©rifier que le service traitant existe
            if (!empty($data['idServiceTraitant']) && !$idServiceTraitant) {
                return $this->json([
                    'code' => 404,
                    'message' => "Le service avec l'ID {$data['idServiceTraitant']} n'existe pas."
                ], 404);
            }

            // âœ… VALIDATION : VÃ©rifier que le service traitant est un POSTE et non un SERVICE
            if ($idServiceTraitant && $idServiceTraitant->getTypeService() !== 'poste') {
                return $this->json([
                    'code' => 400,
                    'message' => 'Vous ne pouvez pas assigner un courrier à un service. Veuillez choisir un poste spécifique au lieu du service.'
                ], 400);
            }

            // âœ… VALIDATION : Empêcher la transmission à son propre poste/service
            if ($idCreateur && $idServiceTraitant) {
                $userServiceId = $idCreateur->getIdService()?->getId();
                if ($userServiceId && $userServiceId === $idServiceTraitant->getId()) {
                    return $this->json([
                        'code' => 400,
                        'message' => 'Vous ne pouvez pas transmettre un courrier à votre propre poste/service.'
                    ], 400);
                }
            }
            
            // âœ… Validation : VÃ©rifier que le crÃ©ateur existe
            if (!empty($data['idCreateur']) && !$idCreateur) {
                return $this->json([
                    'code' => 404,
                    'message' => "L'utilisateur créateur avec l'ID {$data['idCreateur']} n'existe pas."
                ], 404);
            }
            
            // âœ… Validation : VÃ©rifier que la provenance existe
            if (!empty($data['idProvenance']) && !$idProvenance) {
                return $this->json([
                    'code' => 404,
                    'message' => "Le correspondant avec l'ID {$data['idProvenance']} n'existe pas."
                ], 404);
            }
            
            // âœ… Validation : VÃ©rifier que le type de courrier existe
            if (!empty($data['typeCourrier']) && !$typeCourrier) {
                return $this->json([
                    'code' => 404,
                    'message' => "Le type de courrier avec l'ID {$data['typeCourrier']} n'existe pas."
                ], 404);
            }
            
            // ðŸš« SUPPRIMER AUSSI CETTE VALIDATION
            // if ($sendSmsServiceTraitant && (!$idServiceTraitant || empty($idServiceTraitant->getTelephone()))) {
            //     return $this->json([
            //         'code' => 400, 
            //         'message' => 'Le numÃ©ro de tÃ©lÃ©phone du service traitant est requis pour l\'envoi de SMS au service'
            //     ], 400);
            // }
            
            // ðŸ†• GÃ‰NÃ‰RATION DU NUMÃ‰RO AUTOMATIQUE
            $numero = $this->genererNumeroCourrier();
            $data['numero'] = $numero;

            // âš™ï¸ Affectation aprÃ¨s validation
            $data['idCreateur'] = $idCreateur;
            $data['typeCourrier'] = $typeCourrier;
            $data['idProvenance'] = $idProvenance;
            $data['idServiceTraitant'] = $idServiceTraitant;

            // ðŸ“‚ Document principal
            if ($request->files->get('document')) {
                $filePath = $this->fileService->uploadFile(
                    $this->getParameter('app_uploads_courrier_directory'),
                    $request->files->get('document')
                );
                if ($filePath) {
                    $data['document'] = $this->getParameter('app_uploads_courrier') . $filePath;
                }
            }
            // ðŸ”¹ Conversion manuelle des dates
            if (!empty($data['dateArrivee']) && is_string($data['dateArrivee'])) {
                $data['dateArrivee'] = new \DateTimeImmutable($data['dateArrivee']);
            }

            if (!empty($data['dateEnregistrement']) && is_string($data['dateEnregistrement'])) {
                $data['dateEnregistrement'] = new \DateTimeImmutable($data['dateEnregistrement']);
            }

            // ðŸ’¾ Sauvegarde du courrier
            $courrier = $this->crudService->postEntity($courrier, $data);

            // 2ï¸âƒ£ Transmission initiale
            $transmission = new Transmission();
            $transmission->setIdCourrier($courrier);
            $transmission->setIdEmetteur($data['idCreateur']);
            $transmission->setIdServiceDestinataire($data['idServiceTraitant']);
            $transmission->setTypeTransfert($data['typeTransfert'] ?? 'Direct');
            $transmission->setDateInstruction($data['dateEnregistrement']);
            $transmission->setStatut('Transmis');

            $this->crudService->postEntity($transmission, []);

            // 3ï¸âƒ£ Gestion des piÃ¨ces jointes multiples
            if (!empty($request->files->get('piecesJointes'))) {
                // RÃ©cupÃ©ration des intitulÃ©s - Support de plusieurs formats
                $intitulesPiecesJointes = [];
                
                // RÃ©cupÃ©ration de la valeur brute
                $intitulesValue = $request->request->get('intitulesPiecesJointes');
                
                if (!empty($intitulesValue)) {
                    if (is_string($intitulesValue)) {
                        // Format 1 : JSON array
                        $jsonDecoded = json_decode($intitulesValue, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
                            $intitulesPiecesJointes = array_map('trim', $jsonDecoded);
                        }
                        // Format 2 : Retour Ã  la ligne (recommandÃ©)
                        elseif (strpos($intitulesValue, "\n") !== false) {
                            $intitulesPiecesJointes = array_map('trim', explode("\n", $intitulesValue));
                            // Filtrer les lignes vides
                            $intitulesPiecesJointes = array_filter($intitulesPiecesJointes, fn($v) => $v !== '');
                        }
                        // Format 3 : SÃ©parateur ||| (trois pipes)
                        elseif (strpos($intitulesValue, '|||') !== false) {
                            $intitulesPiecesJointes = array_map('trim', explode('|||', $intitulesValue));
                        }
                        // Format 4 : SÃ©parateur ; (point-virgule)
                        elseif (strpos($intitulesValue, ';') !== false) {
                            $intitulesPiecesJointes = array_map('trim', explode(';', $intitulesValue));
                        }
                        // Format 5 : Un seul intitulÃ©
                        else {
                            $intitulesPiecesJointes = [trim($intitulesValue)];
                        }
                    } elseif (is_array($intitulesValue)) {
                        // Format tableau classique (cURL avec intitulesPiecesJointes[])
                        $intitulesPiecesJointes = array_map('trim', $intitulesValue);
                    }
                }
                
                $index = 0;
                
                foreach ($request->files->get('piecesJointes') as $file) {
                    $filePath = $this->fileService->uploadFile(
                        $this->getParameter('app_uploads_courrier_piece_directory'),
                        $file
                    );

                    if ($filePath) {
                        $piece = new PieceJointe();
                        $piece->setNom($file->getClientOriginalName());
                        
                        // Ajout de l'intitulÃ© si fourni
                        if (isset($intitulesPiecesJointes[$index]) && !empty($intitulesPiecesJointes[$index])) {
                            $piece->setIntitule($intitulesPiecesJointes[$index]);
                        }
                        
                        $piece->setChemin($this->getParameter('app_uploads_courrier_piece') . $filePath);
                        $piece->setType($file->getClientMimeType());
                        $piece->setIdParent($courrier->getId());
                        $piece->setTypeParent('Courrier');

                        $this->crudService->postEntity($piece, []);
                    }
                    
                    $index++;
                }
            }

            // âœ… FLUSH POUR GARANTIR LA CRÃ‰ATION DU COURRIER AVANT LES NOTIFICATIONS
            $this->entityManager->flush();

            // 4ï¸âƒ£ Envoi des notifications selon les conditions (ne bloque pas la crÃ©ation)
            $notificationResults = $this->handleAllNotifications(
                $courrier, 
                $sendMail, 
                $sendServiceTraitant, 
                $sendSms, 
                $sendSmsServiceTraitant
            );

            // 5ï¸âƒ£ CrÃ©ation des notifications pour tous les utilisateurs du service traitant
            // Cette opÃ©ration est isolÃ©e et ne peut pas faire Ã©chouer la crÃ©ation du courrier
            // On passe la transmission initiale pour lier la notification à la transmission
            $notificationCount = $this->createServiceNotifications($courrier, $transmission);

            // 6ï¸âƒ£ ðŸ†• Envoi d'emails Ã  tous les utilisateurs du service traitant
            $emailsSentToUsers = $this->sendEmailsToServiceUsers($courrier, $sendServiceTraitant);

            return $this->json(array_merge(
                json_decode($this->json($courrier, 200, [], ['groups' => 'Get:Courrier'])->getContent(), true),
                [
                    'notifications' => $notificationResults,
                    'notificationsSent' => $notificationCount,
                    'emailsSentToServiceUsers' => $emailsSentToUsers
                ]
            ), 201);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GÃ¨re l'envoi de toutes les notifications (emails + SMS)
     */
    private function handleAllNotifications(
        Courrier $courrier, 
        bool $sendMail, 
        bool $sendServiceTraitant, 
        bool $sendSms, 
        bool $sendSmsServiceTraitant
    ): array {
        $results = [
            'emailSentToCorrespondant' => false,
            'emailSentToService' => false,
            'smsSentToCorrespondant' => false,
            'smsSentToService' => false,
        ];

        try {
            // ðŸ“§ EMAILS
            if ($sendMail && !empty($courrier->getEmail())) {
                $this->logger?->info('Préparation envoi email au correspondant', [
                    'courrier_id' => $courrier->getId(),
                    'email' => $courrier->getEmail(),
                    'sendMail' => $sendMail
                ]);
                
                $this->sendEmailToCorrespondant($courrier);
                $results['emailSentToCorrespondant'] = true;
                
                $this->logger?->info('Email au correspondant marqué comme envoyé', [
                    'courrier_id' => $courrier->getId()
                ]);
            } else {
                $this->logger?->warning('Email au correspondant non envoyé', [
                    'courrier_id' => $courrier->getId(),
                    'sendMail' => $sendMail,
                    'email_vide' => empty($courrier->getEmail()),
                    'email' => $courrier->getEmail()
                ]);
            }

            // âœ… VÃ©rification pour Ã©viter d'envoyer le mÃªme email deux fois si l'email du service = email du correspondant
            $emailService = $courrier->getIdServiceTraitant()?->getEmailService();
            $emailCorrespondant = $courrier->getEmail();
            
            if ($sendServiceTraitant && $courrier->getIdServiceTraitant() && !empty($emailService)) {
                // N'envoyer au service que si l'email est diffÃ©rent de celui du correspondant
                if ($emailService !== $emailCorrespondant || !$sendMail) {
                    $this->sendEmailToService($courrier);
                    $results['emailSentToService'] = true;
                } else {
                    $this->logger?->info('Email service ignoré car identique à l\'email correspondant', [
                        'courrier_id' => $courrier->getId(),
                        'email' => $emailService
                    ]);
                }
            }

            // ðŸ“± SMS avec validation intÃ©grÃ©e (ne bloque pas la crÃ©ation)
            if ($sendSms) {
                if (!empty($courrier->getTelephone())) {
                    $smsResult = $this->sendSmsToCorrespondant($courrier);
                    $results['smsSentToCorrespondant'] = $smsResult['success'];
                } else {
                    $this->logger?->warning('SMS correspondant demandé mais numéro manquant', [
                        'courrier_id' => $courrier->getId()
                    ]);
                }
            }

            if ($sendSmsServiceTraitant) {
                if ($courrier->getIdServiceTraitant() && !empty($courrier->getIdServiceTraitant()->getTelephone())) {
                    $smsResult = $this->sendSmsToService($courrier);
                    $results['smsSentToService'] = $smsResult['success'];
                } else {
                    $this->logger?->warning('SMS service demandé mais numéro manquant', [
                        'courrier_id' => $courrier->getId(),
                        'service' => $courrier->getIdServiceTraitant()?->getNom()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas Ã©chouer la crÃ©ation du courrier
            $this->logger?->error('Erreur lors de l\'envoi des notifications', [
                'courrier_id' => $courrier->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * Envoie un email de notification au correspondant
     */
    private function sendEmailToCorrespondant(Courrier $courrier): void
    {
        $email = $courrier->getEmail();
        
        $this->logger?->info('Tentative d\'envoi d\'email au correspondant', [
            'courrier_id' => $courrier->getId(),
            'email' => $email,
            'numero' => $courrier->getNumero()
        ]);
        
        $subject = "Accusé de réception - Courrier n° {$courrier->getNumero()}";
        
        $htmlContent = $this->twig->render('emails/courrier/accusé_reception_correspondant.html.twig', [
            'courrier' => $courrier,
        ]);

        try {
            $this->mailService->sendEmail($email, $subject, $htmlContent);
            
            $this->logger?->info('Email envoyé avec succès au correspondant', [
                'courrier_id' => $courrier->getId(),
                'email' => $email
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Échec de l\'envoi d\'email au correspondant', [
                'courrier_id' => $courrier->getId(),
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            // Re-lancer l'exception pour qu'elle soit gÃ©rÃ©e par handleAllNotifications
            throw $e;
        }
    }

    /**
     * Envoie un email de notification au service traitant
     */
    private function sendEmailToService(Courrier $courrier): void
    {
        $subject = "Nouveau courrier à traiter - n° {$courrier->getNumero()}";
        
        $htmlContent = $this->twig->render('emails/courrier/notification_service.html.twig', [
            'courrier' => $courrier,
        ]);

        $this->mailService->sendEmail($courrier->getIdServiceTraitant()->getEmailService(), $subject, $htmlContent);
    }

    /**
     * ðŸ“± Envoie un SMS de notification au correspondant
     */
    private function sendSmsToCorrespondant(Courrier $courrier): array
    {
        // Message optimisé pour tenir dans 260 caractères avec le lien complet
        $message = "votre courrier n°{$courrier->getNumero()} a été enregistré. suivez le sur: https://minepia.cm/site/consultez-vos-dossiers/\nMerci.\nYour mail no {$courrier->getNumero()} has been registered. Track it at: https://minepia.cm/site/consultez-vos-dossiers/\nThank you.";

        return $this->smsService->sendSms(
            $courrier->getTelephone(),
            $message,
            true // normalize
        );
    }

    /**
     * ðŸ“± Envoie un SMS de notification au service traitant
     */
    private function sendSmsToService(Courrier $courrier): array
    {
        // Message optimisé pour tenir dans 260 caractères avec le lien complet
        $numero = $courrier->getNumero();
        $nom = $courrier->getNom();
        
        // Si le nom est trop long, on le tronque
        if (mb_strlen($nom, 'UTF-8') > 20) {
            $nom = mb_substr($nom, 0, 20, 'UTF-8') . '.';
        }
        
        $message = "votre courrier n°{$numero} de {$nom}. a été enregistré. suivez le sur: https://minepia.cm/site/consultez-vos-dossiers/\nMerci.\nYour mail no {$numero} from {$nom} has been registered. Track it at: https://minepia.cm/site/consultez-vos-dossiers/\nThank you.";
        
        return $this->smsService->sendSms(
            $courrier->getIdServiceTraitant()->getTelephone(),
            $message,
            true // normalize
        );
    }

    /**
     * GÃ©nÃ¨re un numÃ©ro automatique au format AAAA-MM-XXX (rÃ©initialisÃ© chaque mois)
     * Compte tous les courriers du mois sans distinction de service
     */
    private function genererNumeroCourrier(): string
    {
        $annee = date('Y');
        $mois = date('m');
        
        // Compter le nombre de courriers crÃ©Ã©s ce mois
        $count = $this->courrierRepository->countCourriersForYearMonth($annee, (int)$mois);
        $numeroSequence = $count + 1;
        
        // Format: 2025-12-001 (rÃ©initialisÃ© chaque mois)
        return sprintf('%s-%s-%03d', $annee, $mois, $numeroSequence);
    }

    /**
     * ðŸ”” CrÃ©e des notifications pour tous les utilisateurs du service traitant
     * 
     * âš ï¸ IMPORTANT : Cette mÃ©thode est isolÃ©e avec son propre try-catch pour Ã©viter
     * que des erreurs de notification n'empÃªchent la crÃ©ation du courrier.
     * 
     * @param Courrier $courrier Le courrier crÃ©Ã©
     * @return int Nombre de notifications crÃ©Ã©es (0 en cas d'erreur)
     */
    private function createServiceNotifications(Courrier $courrier, ?Transmission $transmission = null): int
    {
        $service = $courrier->getIdServiceTraitant();
        
        // Si pas de service traitant, on ne crÃ©e pas de notifications
        if (!$service) {
            $this->logger?->info('Aucune notification crée : pas de service traitant', [
                'courrier_id' => $courrier->getId()
            ]);
            return 0;
        }

        $count = 0;
        
        try {
            // RÃ©cupÃ©rer tous les utilisateurs actifs du service
            $users = $this->userRepository->findBy([
                'idService' => $service,
                'isActive' => true,
                'isDelete' => false
            ]);
            
            if (empty($users)) {
                $this->logger?->info('Aucune notification créée : aucun utilisateur actif dans le service', [
                    'courrier_id' => $courrier->getId(),
                    'service' => $service->getNom()
                ]);
                return 0;
            }
            
            $titre = "Nouveau courrier n°{$courrier->getNumero()}";
            $message = "Un nouveau courrier vous a été transmis pour traitement. Objet : {$courrier->getObjet()}";
            
            // CrÃ©er une notification pour chaque utilisateur du service
            foreach ($users as $user) {
                try {
                    $notification = new Notification();
                    $notification->setTitre($titre);
                    $notification->setMessage($message);
                    // Si une transmission est fournie, marquer la notification comme type transmission
                    $notification->setType($transmission ? 'transmission' : 'courrier');

                    $notificationData = [
                        'courrier_id' => $courrier->getId(),
                        'numero' => $courrier->getNumero(),
                        'reference' => $courrier->getReference(),
                        'objet' => $courrier->getObjet(),
                        'priorite' => $courrier->getPriorite(),
                        'expediteur' => $courrier->getNom(),
                        'date_arrivee' => $courrier->getDateArrivee()?->format('Y-m-d'),
                    ];

                    if ($transmission && $transmission->getId()) {
                        $notificationData['transmission_id'] = $transmission->getId();
                    }

                    $notification->setData($notificationData);
                    $notification->setUser($user);
                    $notification->setService($service);

                    $this->entityManager->persist($notification);
                    $count++;
                } catch (\Exception $userException) {
                    // Log l'erreur pour cet utilisateur mais continue pour les autres
                    $this->logger?->error('Erreur lors de la création d\'une notification pour un utilisateur', [
                        'courrier_id' => $courrier->getId(),
                        'user_id' => $user->getId(),
                        'exception' => $userException->getMessage()
                    ]);
                }
            }

            // Flush dans un try-catch sÃ©parÃ© pour isoler les erreurs de BDD
            if ($count > 0) {
                try {
                    $this->entityManager->flush();
                    
                    $this->logger?->info('Notifications crées avec succès', [
                        'courrier_id' => $courrier->getId(),
                        'service' => $service->getNom(),
                        'count' => $count
                    ]);
                } catch (\Exception $flushException) {
                    // Erreur lors du flush : on log mais on ne fait pas Ã©chouer la crÃ©ation du courrier
                    $this->logger?->error('Erreur lors de la sauvegarde des notifications (flush)', [
                        'courrier_id' => $courrier->getId(),
                        'exception' => $flushException->getMessage()
                    ]);
                    return 0; // Aucune notification n'a Ã©tÃ© sauvegardÃ©e
                }
            }
            
        } catch (\Exception $e) {
            // Erreur gÃ©nÃ©rale : on log mais on ne fait pas Ã©chouer la crÃ©ation du courrier
            $this->logger?->error('Erreur lors de la création des notifications', [
                'courrier_id' => $courrier->getId(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }

        return $count;
    }

    /**
     * ðŸ“§ Envoie des emails Ã  tous les utilisateurs du service traitant
     * 
     * @param Courrier $courrier Le courrier crÃ©Ã©
     * @param bool $sendServiceTraitant ParamÃ¨tre pour activer/dÃ©sactiver l'envoi
     * @return int Nombre d'emails envoyÃ©s aux utilisateurs
     */
    private function sendEmailsToServiceUsers(Courrier $courrier, bool $sendServiceTraitant): int
    {
        // Si l'envoi est dÃ©sactivÃ©, on ne fait rien
        if (!$sendServiceTraitant) {
            return 0;
        }

        $service = $courrier->getIdServiceTraitant();
        
        // Si pas de service traitant, on ne peut pas envoyer d'emails
        if (!$service) {
            $this->logger?->info('Aucun email envoyé aux utilisateurs : pas de service traitant', [
                'courrier_id' => $courrier->getId()
            ]);
            return 0;
        }

        $count = 0;
        
        try {
            // RÃ©cupÃ©rer tous les utilisateurs actifs du service avec email
            $users = $this->userRepository->findBy([
                'idService' => $service,
                'isActive' => true,
                'isDelete' => false
            ]);
            
            if (empty($users)) {
                $this->logger?->info('Aucun email envoyé : aucun utilisateur actif dans le service', [
                    'courrier_id' => $courrier->getId(),
                    'service' => $service->getNom()
                ]);
                return 0;
            }
            
            // PrÃ©parer le contenu de l'email une seule fois
            // Utilise le template de notification de transmission pour informer les utilisateurs
            $subject = "Nouveau courrier à traiter - n° {$courrier->getNumero()}";
            
            $htmlContent = $this->twig->render('emails/courrier/notification_utilisateurs_service.html.twig', [
                'courrier' => $courrier,
            ]);
            
            $emailCorrespondant = $courrier->getEmail();
            
            // Envoyer un email Ã  chaque utilisateur ayant une adresse email
            foreach ($users as $user) {
                try {
                    $userEmail = $user->getEmail();
                    
                    // VÃ©rifier que l'utilisateur a un email et qu'il est diffÃ©rent de celui du correspondant
                    if (!empty($userEmail) && $userEmail !== $emailCorrespondant) {
                        $this->mailService->sendEmail($userEmail, $subject, $htmlContent);
                        $count++;
                        
                        $this->logger?->info('Email envoyé à un utilisateur du service', [
                            'courrier_id' => $courrier->getId(),
                            'user_id' => $user->getId(),
                            'user_email' => $userEmail
                        ]);
                    } else {
                        $this->logger?->debug('Email ignoré pour utilisateur', [
                            'courrier_id' => $courrier->getId(),
                            'user_id' => $user->getId(),
                            'raison' => empty($userEmail) ? 'pas d\'email' : 'email identique au correspondant'
                        ]);
                    }
                } catch (\Exception $userException) {
                    // Log l'erreur pour cet utilisateur mais continue pour les autres
                    $this->logger?->error('Erreur lors de l\'envoi d\'email à un utilisateur', [
                        'courrier_id' => $courrier->getId(),
                        'user_id' => $user->getId(),
                        'exception' => $userException->getMessage()
                    ]);
                }
            }
            
            $this->logger?->info('Emails envoyés aux utilisateurs du service', [
                'courrier_id' => $courrier->getId(),
                'service' => $service->getNom(),
                'count' => $count
            ]);
            
        } catch (\Exception $e) {
            // Erreur générale : on log mais on ne fait pas échouer la création du courrier
            $this->logger?->error('Erreur lors de l\'envoi des emails aux utilisateurs du service', [
                'courrier_id' => $courrier->getId(),
                'exception' => $e->getMessage()
            ]);
            return 0;
        }

        return $count;
    }
}
