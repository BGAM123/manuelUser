<?php

namespace App\Controller\Core\Courrier;

use App\Entity\Cour\Courrier;
use App\Entity\Cour\Transmission;
use App\Entity\Cour\PieceJointe;
use App\Repository\Core\TypeCourrierRepository;
use App\Repository\Core\UserRepository;
use App\Repository\Core\ServiceRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FileService;
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

#[OA\Tag(name: "CourrierArrive")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FileService $fileService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private CorrespondantRepository $correspondantRepository,
        private CourrierRepository $courrierRepository,
        private TransmissionRepository $transmissionRepository,
        private TypeCourrierRepository $typeCourrierRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private EntityManagerInterface $em,
        private MailService $mailService,
        private SmsService $smsService,
        private Environment $twig,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/courrier/{id<([1-9][0-9]*)>}', name: 'app_core_courrier_patch', methods: ['POST'])]
    #[OA\Post(
        path: '/core/courrier/{id}',
        summary: 'Met à  jour un courrier entrant existant',
        tags: ['CourrierArrive'],
        description: "Met à jour un courrier entrant et remplace le document et/ou les piéces jointes si fournis.
        
        ⚠️ RÈGLES IMPORTANTES : 
        - Si vous modifiez `idServiceTraitant`, celui-ci DOIT être un POSTE et non un SERVICE. Vérifiez que le champ `typeService` est 'poste'.
        - Vous ne pouvez PAS transmettre un courrier Ã  VOTRE PROPRE poste/service.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant du courrier', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'reference', type: 'string', example: 'CA-00045/2025'),
                        new OA\Property(property: 'objet', type: 'string', example: 'Demande de subvention'),
                        new OA\Property(property: 'commentaire', type: 'string', example: 'Mise à jour du courrier'),
                        new OA\Property(property: 'priorite', type: 'string', example: 'Haute'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                        new OA\Property(property: 'categorie', type: 'string', example: 'Administrative', description: 'Catégorie du courrier (ex: Administrative, Technique, Financière, etc.)'),
                        new OA\Property(property: 'isConfidentiel', type: 'boolean', example: false, description: 'Marquer le courrier comme confidentiel'),
                        new OA\Property(property: 'dateArrivee', type: 'string', format: 'date', example: '2025-02-14'),
                        new OA\Property(property: 'idProvenance', type: 'integer', example: 1),
                        new OA\Property(property: 'idServiceTraitant', type: 'integer', example: 3),
                        new OA\Property(property: 'idCreateur', type: 'integer', example: 2),
                        new OA\Property(property: 'typeCourrier', type: 'integer', example: 1),
                        new OA\Property(property: 'typeTransfert', type: 'string', example: 'Direct'),
                        new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont'),
                        new OA\Property(property: 'civilite', type: 'string', example: 'M.'),
                        new OA\Property(property: 'matricule', type: 'string', example: 'EMP-00123'),
                        new OA\Property(property: 'telephone', type: 'string', example: '+237 6XX XXX XXX'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                        new OA\Property(property: 'adresse', type: 'string', example: '123 Rue de la Paix'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 3, description: 'Nombre de piéces jointes (valeur libre saisie par l\'utilisateur)'),
                        
                        // 📧 PARAMÈTRES EMAIL
                        new OA\Property(property: 'sendMail', type: 'boolean', example: true, description: 'Envoyer un email de mise à jour au correspondant'),
                        new OA\Property(property: 'sendServiceTraitant', type: 'boolean', example: true, description: 'Envoyer un email de notification au service traitant'),
                        
                        // 📱 PARAMÈTRES SMS (NOUVEAUX)
                        new OA\Property(property: 'sendSms', type: 'boolean', example: false, description: 'Envoyer un SMS au numéro de téléphone du correspondant'),
                        new OA\Property(property: 'sendSmsServiceTraitant', type: 'boolean', example: false, description: 'Envoyer un SMS au numéro du service traitant'),
                        
                        new OA\Property(property: 'document', type: 'string', format: 'binary', description: 'Nouveau document (remplace l\'ancien si fourni)'),
                        new OA\Property(
                            property: 'piecesJointes[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'Nouvelles piéces jointes à ajouter. Les anciennes piéces jointes sont conservées.'
                        ),
                        new OA\Property(
                            property: 'intitulesPiecesJointes', 
                            type: 'string', 
                            example: '["Justificatif de domicile", "Copie carte identité", "CV"]', 
                            description: 'Intitulés des piéces jointes. Formats acceptés : 
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
                response: 200,
                description: 'Courrier mis à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier mis à jour avec succès'),
                        new OA\Property(
                            property: 'courrier',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'reference', type: 'string', example: 'CA-00045/2025'),
                                new OA\Property(property: 'objet', type: 'string', example: 'Demande de subvention'),
                                new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                                new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                                new OA\Property(property: 'categorie', type: 'string', example: 'Administrative'),
                                new OA\Property(property: 'isConfidentiel', type: 'boolean', example: false),
                                new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 3),
                                new OA\Property(property: 'document', type: 'string', example: '/uploads/courrier/document/65ff44c4a8b1f.pdf'),
                                new OA\Property(property: 'serviceTraitant', type: 'string', example: 'Service Administratif'),
                            ]
                        ),
                        new OA\Property(property: 'documentUpdated', type: 'boolean', example: true),
                        new OA\Property(property: 'piecesJointesAdded', type: 'integer', example: 2),
                        new OA\Property(property: 'piecesJointesDeleted', type: 'integer', example: 3),
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
                        new OA\Property(
                            property: 'piecesJointes',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 10),
                                    new OA\Property(property: 'nom', type: 'string', example: 'fichier.pdf'),
                                    new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier/piece/xxx.pdf'),
                                    new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PatchCourrier');

        $courrier = $this->courrierRepository->find($id);
        if (!$courrier) {
            return $this->json(['code' => 404, 'message' => 'Courrier non trouvÃ©.'], 404);
        }

        // RÃ©cupÃ©ration des paramÃ¨tres d'envoi d'emails et SMS (non sauvegardÃ©s en base)
        $sendMail = filter_var($request->request->get('sendMail', false), FILTER_VALIDATE_BOOLEAN);
        $sendServiceTraitant = filter_var($request->request->get('sendServiceTraitant', false), FILTER_VALIDATE_BOOLEAN);
        $sendSms = filter_var($request->request->get('sendSms', false), FILTER_VALIDATE_BOOLEAN);
        $sendSmsServiceTraitant = filter_var($request->request->get('sendSmsServiceTraitant', false), FILTER_VALIDATE_BOOLEAN);

        // ðŸ†• RÃ©cupÃ©ration du champ isConfidentiel pour la mise Ã  jour
        $isConfidentiel = filter_var($request->request->get('isConfidentiel'), FILTER_VALIDATE_BOOLEAN);
        if ($request->request->has('isConfidentiel')) {
            $data['isConfidentiel'] = $isConfidentiel;
        }

        // Sauvegarde des anciennes valeurs pour dÃ©tecter les changements
        $oldServiceTraitant = $courrier->getIdServiceTraitant();
        $oldEmail = $courrier->getEmail();

        $data = $request->request->all();
        $this->functionService->validate($data);
        $data = $this->functionService->excludeFields($data, [
            'createdAt', 'updatedAt', 'id', 
            'sendMail', 'sendServiceTraitant', 
            'sendSms', 'sendSmsServiceTraitant'
        ]);

        try {
            $documentUpdated = false;

            // DOCUMENT : remplacer seulement si un nouveau fichier est envoyÃ©
            if ($request->files->get('document')) {
                if ($courrier->getDocument()) {
                    $oldFilePath = $this->getParameter('kernel.project_dir') . '/public' . $courrier->getDocument();
                    if (file_exists($oldFilePath)) { @unlink($oldFilePath); }
                }
                $filePath = $this->fileService->uploadFile(
                    $this->getParameter('app_uploads_courrier_directory'),
                    $request->files->get('document')
                );
                if ($filePath) {
                    $data['document'] = $this->getParameter('app_uploads_courrier_public_path') . $filePath;
                    $documentUpdated = true;
                }
            }

            // RELATIONS
            $idCreateur = null;
            $idServiceTraitant = null;
            $typeTransfert = $data['typeTransfert'] ?? 'Direct';

            if (isset($data['idProvenance'])) {
                $courrier->setIdProvenance(
                    !empty($data['idProvenance']) ? $this->correspondantRepository->find($data['idProvenance']) : null
                );
                unset($data['idProvenance']);
            }
            if (isset($data['typeCourrier'])) {
                $courrier->setTypeCourrier(
                    !empty($data['typeCourrier']) ? $this->typeCourrierRepository->find($data['typeCourrier']) : null
                );
                unset($data['typeCourrier']);
            }
            if (isset($data['idServiceTraitant'])) {
                $serviceTraitant = !empty($data['idServiceTraitant'])
                    ? $this->serviceRepository->find($data['idServiceTraitant'])
                    : null;

                // âœ… VALIDATION : VÃ©rifier que le service traitant est un POSTE et non un SERVICE
                if ($serviceTraitant && $serviceTraitant->getTypeService() !== 'poste') {
                    return $this->json([
                        'code' => 400,
                        'message' => 'Vous ne pouvez pas assigner un courrier à  un service. Veuillez choisir un poste spécifique au lieu du service.'
                    ], 400);
                }

                // âœ… VALIDATION : EmpÃªcher la transmission Ã  son propre poste/service
                $currentUser = $this->userRepository->findOneBy(['username' => $this->getUser()->getUserIdentifier()]);
                if ($currentUser && $serviceTraitant) {
                    $userServiceId = $currentUser->getIdService()?->getId();
                    if ($userServiceId && $userServiceId === $serviceTraitant->getId()) {
                        return $this->json([
                            'code' => 400,
                            'message' => 'Vous ne pouvez pas transmettre un courrier à votre propre poste/service.'
                        ], 400);
                    }
                }

                $courrier->setIdServiceTraitant($serviceTraitant);
                $idServiceTraitant = $serviceTraitant;
                unset($data['idServiceTraitant']);
            }
            if (isset($data['idCreateur'])) {
                $createur = !empty($data['idCreateur'])
                    ? $this->userRepository->find($data['idCreateur'])
                    : null;
                $courrier->setIdCreateur($createur);
                $idCreateur = $createur;
                unset($data['idCreateur']);
            }

            unset($data['typeTransfert']);

            if (!empty($data['dateArrivee'])) {
                $data['dateArrivee'] = new \DateTime($data['dateArrivee']);
            }

            $data['statut'] = 'Transmis';
            $this->crudService->patchEntity($courrier, $data);

            // TRANSMISSION
            $existingTransmission = $this->transmissionRepository->findOneBy(['idCourrier' => $courrier]);
            if ($existingTransmission) {
                $this->em->remove($existingTransmission);
                $this->em->flush();
            }
            if ($idCreateur && $idServiceTraitant) {
                $newTransmission = new Transmission();
                $newTransmission->setIdCourrier($courrier)
                    ->setIdEmetteur($idCreateur)
                    ->setIdServiceDestinataire($idServiceTraitant)
                    ->setTypeTransfert($typeTransfert)
                    ->setDateInstruction($courrier->getDateEnregistrement() ?? new \DateTime())
                    ->setInstruction('Transmission automatique suite à la mise à jour.')
                    ->setAccuseReception(false);
                $this->em->persist($newTransmission);
                $this->em->flush();
            }

            // PIÃˆCES JOINTES : ajouter les nouvelles piÃ¨ces jointes sans supprimer les anciennes
            $uploadedCount = 0;

            $addNewPieces = $request->files->has('piecesJointes');
            $uploadedFiles = $request->files->get('piecesJointes');

            if ($addNewPieces) {
                // Normaliser la liste des fichiers uploadÃ©s (un seul ou plusieurs)
                $files = [];
                if ($uploadedFiles instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                    $files = [$uploadedFiles];
                } elseif (is_array($uploadedFiles)) {
                    // Symfony renvoie dÃ©jÃ  un tableau d'UploadedFile pour piecesJointes[]
                    $files = array_filter($uploadedFiles, fn($f) => $f instanceof \Symfony\Component\HttpFoundation\File\UploadedFile);
                }

                // Ajouter les nouvelles PJ (si fournies)
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
                
                foreach ($files as $file) {
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
                        $uploadedCount++;
                    }
                    
                    $index++;
                }
            }

            // PiÃ¨ces jointes restantes (si pas remplacÃ©es => anciennes ; si remplacÃ©es => nouvelles)
            $remainingPieces = $this->pieceJointeRepository->findBy([
                'idParent' => $courrier->getId(),
                'typeParent' => 'Courrier',
                'isDelete' => false
            ]);

            // ðŸ†• GESTION DES NOTIFICATIONS (EMAILS + SMS) - MÃŠME LOGIQUE QUE POSTCREATE
            $notificationResults = $this->handleAllNotifications(
                $courrier, 
                $sendMail, 
                $sendServiceTraitant, 
                $sendSms, 
                $sendSmsServiceTraitant,
                $oldServiceTraitant, 
                $oldEmail
            );

        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }

        return $this->json([
            'message' => 'Courrier mis à jour avec succès',
            'courrier' => [
                'id' => $courrier->getId(),
                'reference' => $courrier->getReference(),
                'objet' => $courrier->getObjet(),
                'statut' => $courrier->getStatut(),
                'classeCourrier' => $courrier->getClasseCourrier(),
                'categorie' => $courrier->getCategorie(),
                'isConfidentiel' => $courrier->isConfidentiel(),
                'nombrePieceJointe' => $courrier->getNombrePieceJointe(),
                'document' => $courrier->getDocument(),
                'serviceTraitant' => $courrier->getIdServiceTraitant()?->getNom(),
            ],
            'documentUpdated' => $documentUpdated,
            'piecesJointesAdded' => $uploadedCount,
            'notifications' => $notificationResults,
            'piecesJointes' => array_map(fn($p) => [
                'id' => $p->getId(),
                'nom' => $p->getNom(),
                'chemin' => $p->getChemin(),
                'type' => $p->getType(),
                'createdAt' => $p->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $remainingPieces)
        ], 200);
    }

    /**
     * GÃ¨re l'envoi de toutes les notifications (emails + SMS) - MÃŠME LOGIQUE QUE POSTCREATE
     */
    private function handleAllNotifications(
        Courrier $courrier, 
        bool $sendMail, 
        bool $sendServiceTraitant, 
        bool $sendSms, 
        bool $sendSmsServiceTraitant,
        $oldServiceTraitant = null,
        $oldEmail = null
    ): array {
        $results = [
            'emailSentToCorrespondant' => false,
            'emailSentToService' => false,
            'emailsSentToServiceUsers' => 0,
            'smsSentToCorrespondant' => false,
            'smsSentToService' => false,
        ];

        try {
            // ðŸ“§ EMAILS
            if ($sendMail && !empty($courrier->getEmail())) {
                $this->sendUpdateEmailToCorrespondant($courrier, $oldEmail);
                $results['emailSentToCorrespondant'] = true;
            }

            // âœ… VÃ©rification pour Ã©viter d'envoyer le mÃªme email deux fois si l'email du service = email du correspondant
            $emailService = $courrier->getIdServiceTraitant()?->getEmailService();
            $emailCorrespondant = $courrier->getEmail();
            
            if ($sendServiceTraitant && $courrier->getIdServiceTraitant() && !empty($emailService)) {
                // N'envoyer au service que si l'email est diffÃ©rent de celui du correspondant
                if ($emailService !== $emailCorrespondant || !$sendMail) {
                    $this->sendUpdateEmailToService($courrier, $oldServiceTraitant);
                    $results['emailSentToService'] = true;
                } else {
                    $this->logger?->info('Email service ignoré car identique à l\'email correspondant', [
                        'courrier_id' => $courrier->getId(),
                        'email' => $emailService
                    ]);
                }
            }

            // ðŸ†• Envoyer des emails Ã  tous les utilisateurs du service
            $emailsSentToUsers = $this->sendEmailsToServiceUsers($courrier, $sendServiceTraitant);
            $results['emailsSentToServiceUsers'] = $emailsSentToUsers;

            // ðŸ“± SMS avec validation intÃ©grÃ©e (ne bloque pas la mise Ã  jour)
            if ($sendSms) {
                if (!empty($courrier->getTelephone())) {
                    $smsResult = $this->sendSmsToCorrespondant($courrier);
                    $results['smsSentToCorrespondant'] = $smsResult['success'];
                } else {
                    $this->logger?->warning('SMS correspondant demandé mais numéro manquant lors de la mise à jour', [
                        'courrier_id' => $courrier->getId()
                    ]);
                }
            }

            if ($sendSmsServiceTraitant) {
                if ($courrier->getIdServiceTraitant() && !empty($courrier->getIdServiceTraitant()->getTelephone())) {
                    $smsResult = $this->sendSmsToService($courrier, $oldServiceTraitant);
                    $results['smsSentToService'] = $smsResult['success'];
                } else {
                    $this->logger?->warning('SMS service demandé mais numéro manquant lors de la mise à jour', [
                        'courrier_id' => $courrier->getId(),
                        'service' => $courrier->getIdServiceTraitant()?->getNom()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas Ã©chouer la mise Ã  jour du courrier
            $this->logger?->error('Erreur lors de l\'envoi des notifications lors de la mise à jour', [
                'courrier_id' => $courrier->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * ðŸ“± Envoie un SMS de notification de mise Ã  jour au correspondant
     */
    private function sendSmsToCorrespondant(Courrier $courrier): array
    {
        $message = "MINEPIA: Votre courrier n°{$courrier->getNumero()} (Ref: {$courrier->getReference()}) a ete mis a jour. Service traitant: {$courrier->getIdServiceTraitant()?->getNom()}.\nMINEPIA: Your mail no {$courrier->getNumero()} (Ref: {$courrier->getReference()}) has been updated. Handling service: {$courrier->getIdServiceTraitant()?->getNom()}.";
        
        return $this->smsService->sendSms(
            $courrier->getTelephone(),
            $message,
            true // normalize
        );
    }

    /**
     * ðŸ“± Envoie un SMS de notification de mise Ã  jour au service traitant
     */
    private function sendSmsToService(Courrier $courrier, $oldServiceTraitant = null): array
    {
        $isServiceChanged = $oldServiceTraitant && $oldServiceTraitant->getId() !== $courrier->getIdServiceTraitant()?->getId();
        
        if ($isServiceChanged) {
            $message = "MINEPIA: Courrier n°{$courrier->getNumero()} de {$courrier->getCivilite()} {$courrier->getNom()} transfere a votre service. Objet: {$courrier->getObjet()}.\nMINEPIA: Mail no {$courrier->getNumero()} from {$courrier->getCivilite()} {$courrier->getNom()} has been transferred to your service. Subject: {$courrier->getObjet()}.";
        } else {
            $message = "MINEPIA: Mise a jour courrier n°{$courrier->getNumero()} de {$courrier->getCivilite()} {$courrier->getNom()}. Objet: {$courrier->getObjet()}.\nMINEPIA: Update for mail no {$courrier->getNumero()} from {$courrier->getCivilite()} {$courrier->getNom()}. Subject: {$courrier->getObjet()}.";
        }
        
        return $this->smsService->sendSms(
            $courrier->getIdServiceTraitant()->getTelephone(),
            $message,
            true // normalize
        );
    }

    /**
     * Envoie un email de notification de mise Ã  jour au correspondant
     */
    private function sendUpdateEmailToCorrespondant(Courrier $courrier, $oldEmail = null): void
    {
        $subject = "Mise Ã  jour de votre courrier nÂ° {$courrier->getNumero()}";
        
        // Utiliser le mÃªme template que lors de la crÃ©ation
        $htmlContent = $this->twig->render('emails/courrier/accusé_reception_correspondant.html.twig', [
            'courrier' => $courrier,
        ]);

        $this->mailService->sendEmail($courrier->getEmail(), $subject, $htmlContent);
    }

    /**
     * Envoie un email de notification de mise Ã  jour au service traitant
     */
    private function sendUpdateEmailToService(Courrier $courrier, $oldServiceTraitant = null): void
    {
        $isServiceChanged = $oldServiceTraitant && $oldServiceTraitant->getId() !== $courrier->getIdServiceTraitant()?->getId();
        
        if ($isServiceChanged) {
            $subject = "Nouveau courrier transféré - n° {$courrier->getNumero()}";
        } else {
            $subject = "Mise à jour du courrier n° {$courrier->getNumero()}";
        }
        
        // Utiliser le même template que lors de la création
        $htmlContent = $this->twig->render('emails/courrier/notification_service.html.twig', [
            'courrier' => $courrier,
        ]);

        $this->mailService->sendEmail($courrier->getIdServiceTraitant()->getEmailService(), $subject, $htmlContent);
    }

    /**
     * ðŸ“§ Envoie des emails Ã  tous les utilisateurs du service traitant (lors de la mise Ã  jour)
     * 
     * @param Courrier $courrier Le courrier mis Ã  jour
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
            $subject = "Mise à jour du courrier n° {$courrier->getNumero()}";
            
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
                        
                        $this->logger?->info('Email de mise à jour envoyé à un utilisateur du service', [
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
            
            $this->logger?->info('Emails de mise à jour envoyés aux utilisateurs du service', [
                'courrier_id' => $courrier->getId(),
                'service' => $service->getNom(),
                'count' => $count
            ]);
            
        } catch (\Exception $e) {
            // Erreur gÃ©nÃ©rale : on log mais on ne fait pas Ã©chouer la mise Ã  jour du courrier
            $this->logger?->error('Erreur lors de l\'envoi des emails aux utilisateurs du service', [
                'courrier_id' => $courrier->getId(),
                'exception' => $e->getMessage()
            ]);
            return 0;
        }

        return $count;
    }
}
