<?php

namespace App\Controller\Core\Transmission;

use App\Entity\Cour\Courrier;
use App\Entity\Cour\Transmission;
use App\Entity\Cour\PieceJointe;
use App\Entity\Core\Notification;
use App\Entity\Core\Service;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Core\ServiceRepository;
use App\Repository\Core\UserRepository;
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
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;

#[OA\Tag(name: "Transmission")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FileService $fileService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private CourrierRepository $courrierRepository,
        private TransmissionRepository $transmissionRepository,
        private ServiceRepository $serviceRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private MailService $mailService,
        private SmsService $smsService,
        private Environment $twig,
        private SluggerInterface $slugger,
        private ParameterBagInterface $params,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/transmission', name: 'app_core_transmission_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/transmission',
        summary: 'Créer une nouvelle transmission',
        tags: ['Transmission'],
        description: "Transmet un courrier à un service destinataire avec notifications par email et SMS optionnelles. 
        
        règles DE VALIDATION :
        - Si l'utilisateur connecté a DÉJÀ crée une transmission pour CE courrier (où il est émetteur), la création est bloquée.
        - L'utilisateur peut transmettre d'autres courriers librement.
        - Un seul envoi par courrier par utilisateur.
        - si Le destinataire DOIT être un POSTE, pas un SERVICE. Vérifiez que le champ `typeService` du destinataire est 'poste'.
        - si Vous ne pouvez PAS transmettre un courrier à VOTRE PROPRE poste/service.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['idCourrier', 'idServiceDestinataire'],
                    properties: [
                        new OA\Property(property: 'idCourrier', type: 'integer', example: 5, description: 'ID du courrier'),
                        new OA\Property(property: 'idServiceDestinataire', type: 'integer', example: 7, description: 'ID du service destinataire'),
                        new OA\Property(property: 'idEmetteur', type: 'integer', example: 2, description: 'ID de l\'émetteur (optionnel)'),
                        new OA\Property(property: 'structuresCopie', type: 'string', example: '1,2,3', description: 'IDs des services en copie séparés par des virgules'),
                        new OA\Property(property: 'instruction', type: 'string', example: 'À traiter en urgence'),
                        new OA\Property(property: 'delaiTraitement', type: 'integer', example: 5, description: 'Délai en jours'),
                        new OA\Property(property: 'typeTransfert', type: 'string', example: 'Pour traitement'),
                        new OA\Property(property: 'statut', type: 'string', example: 'Transmis', description: 'Statut de la transmission (sera toujours défini à "Transmis" lors de la création, quelle que soit la valeur envoyée)'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 3, description: 'Nombre de pièces jointes (valeur libre saisie par l\'utilisateur)'),
                        new OA\Property(property: 'sendEmail', type: 'boolean', example: true, description: 'Envoyer un email de notification'),
                        new OA\Property(property: 'sendSms', type: 'boolean', example: true, description: 'Envoyer un SMS de notification'),
                        new OA\Property(
                            property: 'piecesJointes[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'Fichiers pièces jointes (optionnel) - Peut être un ou plusieurs fichiers (PDF, Word, Image, etc.)'
                        ),
                        new OA\Property(
                            property: 'intitulesPiecesJointes', 
                            type: 'string', 
                            example: '["Bordereau de transmission", "Copie décision", "Note de service"]', 
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
                description: 'Transmission créée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'message', type: 'string', example: 'Transmission créée avec succès'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'courrier', type: 'object', properties: [
                                    new OA\Property(property: 'numero', type: 'string', example: '2025-02-001'),
                                    new OA\Property(property: 'reference', type: 'string', example: 'CA-00045/2025'),
                                    new OA\Property(property: 'objet', type: 'string', example: 'Demande de subvention'),
                                ]),
                                new OA\Property(property: 'serviceDestinataire', type: 'object', properties: [
                                    new OA\Property(property: 'nom', type: 'string', example: 'Direction Administrative'),
                                    new OA\Property(property: 'sigle', type: 'string', example: 'DA'),
                                ]),
                                new OA\Property(property: 'emetteur', type: 'object', properties: [
                                    new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont'),
                                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                                ]),
                                new OA\Property(property: 'dateInstruction', type: 'string', format: 'date-time', example: '2025-12-18 10:30:00', description: 'Date et heure de la transmission'),
                                new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 3, description: 'Nombre de pièces jointes'),
                                new OA\Property(
                                    property: 'notifications',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'emailSentToService', type: 'boolean', example: true),
                                        new OA\Property(property: 'smsSentToService', type: 'boolean', example: true),
                                    ]
                                ),
                                new OA\Property(property: 'notificationsSent', type: 'integer', example: 8, description: 'Nombre total de notifications crées en base de données pour les utilisateurs des services concernés'),
                                new OA\Property(
                                    property: 'piecesJointes',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', nullable: true, example: 250),
                                            new OA\Property(property: 'nom', type: 'string', example: 'bordereau.pdf'),
                                            new OA\Property(property: 'intitule', type: 'string', nullable: true, example: 'Bordereau de transmission'),
                                            new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier/pieces/6942c641c1399.pdf'),
                                            new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                                        ]
                                    ),
                                    description: 'Liste des pièces jointes créées'
                                ),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide. Peut aussi survenir si le destinataire est un service au lieu d\'un poste, si vous tentez de transmettre à votre propre poste, ou si les intitulés des fichiers sont manquants/invalides.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.'),
            new OA\Response(response: 403, description: 'Action non autorisée. Peut survenir si : 1) Vous tentez de transmettre au nom d\'un émetteur sans autorisation, ou 2) Vous avez déjà accusé réception d\'une transmission pour ce courrier.'),
            new OA\Response(response: 404, description: 'Courrier, service destinataire ou émetteur introuvable.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PostTransmission');

        // RÃ©cupÃ©ration des donnÃ©es depuis le formulaire multipart
        $data = [
            'idCourrier' => $request->request->get('idCourrier'),
            'idServiceDestinataire' => $request->request->get('idServiceDestinataire'),
            'idEmetteur' => $request->request->get('idEmetteur'),
            'instruction' => $request->request->get('instruction'),
            'delaiTraitement' => $request->request->get('delaiTraitement'),
            'typeTransfert' => $request->request->get('typeTransfert'),
            'statut' => $request->request->get('statut'),
            'structuresCopie' => $request->request->get('structuresCopie'),
            'nombrePieceJointe' => $request->request->get('nombrePieceJointe'),
        ];

        // RÃ©cupÃ©ration du fichier piÃ¨ce jointe (optionnel) - peut Ãªtre un tableau de fichiers
        $pieceJointeFiles = $request->files->get('piecesJointes');
        
        // RÃ©cupÃ©ration des intitulÃ©s personnalisÃ©s pour les fichiers
        $intitulesValue = $request->request->get('intitulesPiecesJointes');
        
        // RÃ©cupÃ©ration des paramÃ¨tres d'envoi (non sauvegardÃ©s en base)
        $sendEmail = filter_var($request->request->get('sendEmail', false), FILTER_VALIDATE_BOOLEAN);
        $sendSms = filter_var($request->request->get('sendSms', false), FILTER_VALIDATE_BOOLEAN);

        // Conversion des structuresCopie (comma-separated string vers array)
        if (!empty($data['structuresCopie']) && is_string($data['structuresCopie'])) {
            $data['structuresCopie'] = array_map('intval', array_filter(explode(',', $data['structuresCopie'])));
        } else {
            $data['structuresCopie'] = [];
        }

        // Upload des fichiers piÃ¨ces jointes si prÃ©sents (gestion de plusieurs fichiers)
        // Ces piÃ¨ces jointes seront crÃ©Ã©es en base de donnÃ©es aprÃ¨s la crÃ©ation de la transmission
        $uploadedPiecesJointes = [];
        
        // Si c'est un seul fichier, le mettre dans un tableau pour traitement uniforme
        if ($pieceJointeFiles && !is_array($pieceJointeFiles)) {
            $pieceJointeFiles = [$pieceJointeFiles];
        }
        
        if (!empty($pieceJointeFiles) && is_array($pieceJointeFiles)) {
            // RÃ©cupÃ©ration et parsing des intitulÃ©s - Support de plusieurs formats
            $intitulesPiecesJointes = [];
            
            if (!empty($intitulesValue)) {
                if (is_string($intitulesValue)) {
                    // Format 1 : JSON array
                    $jsonDecoded = json_decode($intitulesValue, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
                        $intitulesPiecesJointes = array_map('trim', $jsonDecoded);
                    }
                    // Format 2 : Retour Ã  la ligne
                    elseif (strpos($intitulesValue, "\n") !== false) {
                        $intitulesPiecesJointes = array_map('trim', explode("\n", $intitulesValue));
                        $intitulesPiecesJointes = array_filter($intitulesPiecesJointes, fn($v) => $v !== '');
                    }
                    // Format 3 : SÃ©parateur |||
                    elseif (strpos($intitulesValue, '|||') !== false) {
                        $intitulesPiecesJointes = array_map('trim', explode('|||', $intitulesValue));
                    }
                    // Format 4 : SÃ©parateur ;
                    elseif (strpos($intitulesValue, ';') !== false) {
                        $intitulesPiecesJointes = array_map('trim', explode(';', $intitulesValue));
                    }
                    // Format 5 : Un seul intitulÃ©
                    else {
                        $intitulesPiecesJointes = [trim($intitulesValue)];
                    }
                } elseif (is_array($intitulesValue)) {
                    $intitulesPiecesJointes = array_map('trim', $intitulesValue);
                }
            }
            
            // Upload des fichiers
            foreach ($pieceJointeFiles as $index => $fichier) {
                if ($fichier) {
                    $filePath = $this->fileService->uploadFile(
                        $this->params->get('app_uploads_courrier_piece_directory'),
                        $fichier
                    );
                    
                    if ($filePath) {
                        // PrÃ©parer les donnÃ©es pour crÃ©er la PieceJointe plus tard
                        $uploadedPiecesJointes[] = [
                            'nom' => $fichier->getClientOriginalName(),
                            'intitule' => $intitulesPiecesJointes[$index] ?? null,
                            'chemin' => $this->params->get('app_uploads_courrier_piece') . $filePath,
                            'type' => $fichier->getClientMimeType(),
                        ];
                    }
                }
            }
        }

        try {
            // ðŸ”¹ RÃ©solution des relations
            if (empty($data['idCourrier'])) {
                return $this->json(['code' => 400, 'message' => 'Le courrier est requis.'], 400);
            }

            // $courrier = $this->courrierRepository->find($data['idCourrier']);
            $idCourriers = array_map(
                'intval',
                array_filter(
                    explode(',', (string)$data['idCourrier'])
                )
            );

            $courriers = [];

            foreach ($idCourriers as $idCourrier) {

                $courrier = $this->courrierRepository->find($idCourrier);

                if (!$courrier) {
                    return $this->json([
                        'code' => 404,
                        'message' => "Courrier {$idCourrier} introuvable."
                    ], 404);
                }

                $courriers[] = $courrier;
            }
            // $data['idCourrier'] = $courrier;

            if (empty($data['idServiceDestinataire'])) {
                return $this->json(['code' => 400, 'message' => 'Le service destinataire est requis.'], 400);
            }

            $serviceDestinataire = $this->serviceRepository->find($data['idServiceDestinataire']);
            if (!$serviceDestinataire) {
                return $this->json(['code' => 404, 'message' => 'Service destinataire introuvable.'], 404);
            }

            // âœ… VALIDATION : VÃ©rifier que le destinataire est un POSTE et non un SERVICE
            if ($serviceDestinataire->getTypeService() !== 'poste') {
                return $this->json([
                    'code' => 400,
                    'message' => 'Vous ne pouvez pas faire de transmission à un service. Veuillez choisir un poste spécifique au lieu du service.'
                ], 400);
            }

            $data['idServiceDestinataire'] = $serviceDestinataire;

            // ðŸ”¹ Ã‰metteur (optionnel, sinon l'utilisateur connectÃ©)
            $currentUser = $this->getUser();

            // âœ… VALIDATION : EmpÃªcher de transmettre Ã  son propre service/poste
            if ($currentUser instanceof \App\Entity\Core\User) {
                $userServiceId = $currentUser->getIdService()?->getId();
                if ($userServiceId && $userServiceId === $serviceDestinataire->getId()) {
                    return $this->json([
                        'code' => 400,
                        'message' => 'Vous ne pouvez pas transmettre un courrier à votre propre poste/service.'
                    ], 400);
                }
            }
            
            if (!empty($data['idEmetteur'])) {
                $emetteur = $this->userRepository->find($data['idEmetteur']);
                if (!$emetteur) {
                    return $this->json(['code' => 404, 'message' => 'Émetteur introuvable.'], 404);
                }
                
                // ðŸ”’ VALIDATION : Vérifier que l'utilisateur connecté est autorisé à transmettre au nom de cet émetteur
                if ($currentUser instanceof \App\Entity\Core\User) {
                    $currentUserId = $currentUser->getId();
                    
                    // Si l'Ã©metteur n'est pas l'utilisateur connectÃ© lui-mÃªme, vÃ©rifier les services additionnels
                    if ($emetteur->getId() !== $currentUserId) {
                        $servicesAdditionel = $emetteur->getServicesAdditionel();
                        $isAuthorized = false;
                        
                        // VÃ©rifier si l'utilisateur connectÃ© est dans les services additionnels de l'Ã©metteur
                        if (!empty($servicesAdditionel) && is_array($servicesAdditionel)) {
                            foreach ($servicesAdditionel as $serviceData) {
                                if (is_array($serviceData) && isset($serviceData['userId'])) {
                                    if ((int)$serviceData['userId'] === $currentUserId) {
                                        $isAuthorized = true;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Si l'utilisateur n'est pas autorisÃ©, refuser la transmission
                        // if (!$isAuthorized) {
                        //     return $this->json([
                        //         'code' => 403,
                        //         'message' => 'Vous n\'êtes pas autorisé à transmettre au nom de cet émetteur. L\'émetteur doit vous avoir ajouté dans ses services additionnels.'
                        //     ], 403);
                        // }
                    }
                }
                
                $data['idEmetteur'] = $emetteur;
            } else {
                $data['idEmetteur'] = $currentUser;
            }

            // âœ… VÃ‰RIFICATION : EmpÃªcher si l'utilisateur connectÃ© a DÃ‰JÃ€ transmis CE courrier
//             if ($currentUser instanceof \App\Entity\Core\User && $courrier) {
//                 // VÃ©rifier si l'utilisateur connectÃ© a dÃ©jÃ  une transmission pour CE courrier oÃ¹ il est Ã©metteur
//                 // (peu importe le service destinataire)
//                 foreach ($courriers as $courrier) {

//                     $transmissionExistante =
//                         $this->transmissionRepository->findOneBy([
//                             'idCourrier' => $courrier,
//                             'idEmetteur' => $currentUser,
//                         ]);

//                     if ($transmissionExistante) {
//                         return $this->json([
//                             'code' => 403,
//                             'message' => sprintf(
//                                 'Vous avez déjà transmis le courrier %s.',
//                                 $courrier->getNumero()
//                             )
//                         ], 403);
//                     }
// }
                
//                 // if ($transmissionExistante) {
//                 //     return $this->json([
//                 //         'code' => 403,
//                 //         'message' => 'Vous ne pouvez plus transmettre ce courrier car vous avez déjà transmis.'
//                 //     ], 403);
//                 // }
//             }

            // ðŸ”¹ Date d'instruction automatique
            $data['dateInstruction'] = new \DateTime();
            
            // ðŸ”¹ Forcer le statut Ã  "Transmis" (remplace "En cours" ou tout autre statut)
            $data['statut'] = 'Transmis';
            
            // ðŸ”¹ PrÃ©paration du champ traite_par avec l'utilisateur connectÃ©
            $traitePar = [];
            
            if ($currentUser instanceof \App\Entity\Core\User) {
                $dateTraitement = (new \DateTime())->format('Y-m-d H:i:s');
                $traitePar[] = [
                    'action' => 'transmission',
                    'transmis_par_id' => $currentUser->getId(),
                    'date_traitement' => $dateTraitement,
                ];
            }
            $data['traitePar'] = $traitePar;

            // ðŸ’¾ Sauvegarde de la transmission
            // $transmission = new Transmission();
            // $transmission = $this->crudService->postEntity($transmission, $data);

            $transmissions = [];

            foreach ($courriers as $courrier) {

                $dataTransmission = $data;
                $dataTransmission['idCourrier'] = $courrier;

                $transmission = new Transmission();

                $transmission = $this->crudService->postEntity(
                    $transmission,
                    $dataTransmission
                );

                $transmissions[] = $transmission;
            }

            // âœ… FLUSH POUR GARANTIR LA CRÃ‰ATION DE LA TRANSMISSION AVANT LES PIÃˆCES JOINTES
            $this->entityManager->flush();

            // ðŸ“Ž CrÃ©ation des piÃ¨ces jointes en base de donnÃ©es
            // foreach ($uploadedPiecesJointes as $pieceData) {
            //     $piece = new PieceJointe();
            //     $piece->setNom($pieceData['nom']);
            //     $piece->setIntitule($pieceData['intitule']);
            //     $piece->setChemin($pieceData['chemin']);
            //     $piece->setType($pieceData['type']);
            //     $piece->setIdParent($transmission->getId());
            //     $piece->setTypeParent('Transmission');
                
            //     $this->crudService->postEntity($piece, []);
            // }

            foreach ($transmissions as $transmission) {

                foreach ($uploadedPiecesJointes as $pieceData) {

                    $piece = new PieceJointe();
                    $piece->setNom($pieceData['nom']);
                    $piece->setIntitule($pieceData['intitule']);
                    $piece->setChemin($pieceData['chemin']);
                    $piece->setType($pieceData['type']);
                    $piece->setIdParent($transmission->getId());
                    $piece->setTypeParent('Transmission');

                    $this->crudService->postEntity($piece, []);
                }
            }

            // âœ… FLUSH POUR GARANTIR LA CRÃ‰ATION DES PIÃˆCES JOINTES AVANT LES NOTIFICATIONS
            $this->entityManager->flush();

            // ðŸ†• GESTION DES NOTIFICATIONS (EMAILS + SMS)
            // $notificationResults = $this->handleAllNotifications(
            //     $transmission,
            //     $serviceDestinataire,
            //     $courrier,
            //     $data['idEmetteur'],
            //     $sendEmail,
            //     $sendSms,
            //     $data['structuresCopie'] ?? null
            // );
            $notificationResults = [];

            foreach ($transmissions as $transmission) {

                $notificationResults[] = $this->handleAllNotifications(
                    $transmission,
                    $serviceDestinataire,
                    $transmission->getIdCourrier(),
                    $data['idEmetteur'],
                    $sendEmail,
                    $sendSms,
                    $data['structuresCopie'] ?? null
                );
            }

            // ðŸ”” CRÃ‰ATION DES NOTIFICATIONS EN BASE DE DONNÃ‰ES
            // Ne bloque pas la crÃ©ation de la transmission
            $notificationCount = 0;

            foreach ($transmissions as $transmission) {

                $notificationCount += $this->createTransmissionNotifications(
                    $transmission,
                    $serviceDestinataire,
                    $data['structuresCopie'] ?? null,
                    $transmission->getIdCourrier()
                );
            }

            $emetteurFirstName = '';
            $emetteurLastName = '';

            if (is_object($data['idEmetteur'])) {
                // Prefer common method names but fall back to French ones if needed
                if (method_exists($data['idEmetteur'], 'getFirstName')) {
                    $emetteurFirstName = $data['idEmetteur']->getFirstName();
                } elseif (method_exists($data['idEmetteur'], 'getPrenom')) {
                    $emetteurFirstName = $data['idEmetteur']->getPrenom();
                }

                if (method_exists($data['idEmetteur'], 'getLastName')) {
                    $emetteurLastName = $data['idEmetteur']->getLastName();
                } elseif (method_exists($data['idEmetteur'], 'getNom')) {
                    $emetteurLastName = $data['idEmetteur']->getNom();
                }
            }

            return $this->json([
                'id' => $transmission->getId(),
                'message' => 'Transmission créée avec succès',
                'data' => [
                    'nombreTransmissions' => count($transmissions),
                    'transmissions' => array_map(
                        fn($t) => [
                            'id' => $t->getId(),
                            'courrier' => [
                                'id' => $t->getIdCourrier()?->getId(),
                                'numero' => $t->getIdCourrier()?->getNumero(),
                                'reference' => $t->getIdCourrier()?->getReference(),
                                'objet' => $t->getIdCourrier()?->getObjet(),
                            ],
                        ],
                        $transmissions
                    ),
                    'serviceDestinataire' => [
                        'nom' => $serviceDestinataire->getNom(),
                        'sigle' => $serviceDestinataire->getSigle(),
                    ],
                    'emetteur' => [
                        'nom' => $emetteurLastName,
                        'prenom' => $emetteurFirstName,
                    ],
                    'dateInstruction' => $transmission->getDateInstruction()?->format('Y-m-d H:i:s'),
                    'nombrePieceJointe' => $transmission->getNombrePieceJointe(),
                    'notifications' => $notificationResults,
                    'notificationsSent' => $notificationCount,
                    'piecesJointes' => array_map(fn($p) => [
                        'id' => $p['id'] ?? null,
                        'nom' => $p['nom'],
                        'intitule' => $p['intitule'],
                        'chemin' => $p['chemin'],
                        'type' => $p['type'],
                    ], $uploadedPiecesJointes),
                ]
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GÃ¨re l'envoi de toutes les notifications (emails + SMS) lors de la crÃ©ation d'une transmission
     */
    private function handleAllNotifications(
        Transmission $transmission,
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
                // Email au service destinataire (si email service renseignÃ©)
                if ($serviceDestinataire && !empty($serviceDestinataire->getEmailService())) {
                    $this->sendEmailToService($transmission, $serviceDestinataire, $courrier, $emetteur);
                    $results['emailSentToService'] = true;
                }
            }

            // ðŸ“± SMS AU SERVICE DESTINATAIRE PRINCIPAL
            if ($sendSms) {
                // SMS au service destinataire (si numÃ©ro de tÃ©lÃ©phone service renseignÃ©)
                if ($serviceDestinataire && !empty($serviceDestinataire->getTelephone())) {
                    $smsResult = $this->sendSmsToService($transmission, $serviceDestinataire, $courrier, $emetteur);
                    $results['smsSentToService'] = $smsResult['success'];
                } else {
                    $this->logger?->warning('SMS service destinataire demandé mais numéro manquant lors de la transmission', [
                        'transmission_id' => $transmission->getId(),
                        'service_id' => $serviceDestinataire?->getId()
                    ]);
                }
            }

            // ðŸ†• ðŸ“§ EMAIL AU SERVICE PARENT DU DESTINATAIRE
            if ($sendEmail && $serviceDestinataire) {
                $serviceParent = $serviceDestinataire->getIdServiceParent();
                if ($serviceParent && !empty($serviceParent->getEmailService())) {
                    try {
                        $this->sendEmailToService($transmission, $serviceParent, $courrier, $emetteur);
                        $results['emailSentToParentService'] = true;
                        $this->logger?->info('Email envoyé au service parent', [
                            'transmission_id' => $transmission->getId(),
                            'service_parent' => $serviceParent->getNom()
                        ]);
                    } catch (\Exception $parentEmailException) {
                        $this->logger?->error('Erreur envoi email service parent', [
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
                        $this->logger?->info('SMS envoyÃ© au service parent', [
                            'transmission_id' => $transmission->getId(),
                            'service_parent' => $serviceParent->getNom(),
                            'success' => $smsResult['success']
                        ]);
                    } catch (\Exception $parentSmsException) {
                        $this->logger?->error('Erreur envoi SMS service parent', [
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
                                    $this->logger?->error('Erreur envoi email service copie', [
                                        'service_id' => $serviceId,
                                        'exception' => $emailException->getMessage()
                                    ]);
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
                                    $this->logger?->error('Erreur envoi SMS service copie', [
                                        'service_id' => $serviceId,
                                        'exception' => $smsException->getMessage()
                                    ]);
                                    $results['smsSentToServicesCopie'][] = [
                                        'service_id' => $serviceId,
                                        'service_nom' => $serviceCopie->getNom(),
                                        'success' => false
                                    ];
                                }
                            }
                        } else {
                            $this->logger?->warning('Service en copie introuvable pour notifications', [
                                'service_id' => $serviceId
                            ]);
                        }
                    } catch (\Exception $serviceException) {
                        $this->logger?->error('Erreur traitement service copie', [
                            'service_id' => $serviceId,
                            'exception' => $serviceException->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas Ã©chouer la crÃ©ation de la transmission
            $this->logger?->error('Erreur lors de l\'envoi des notifications pour transmission', [
                'transmission_id' => $transmission->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * Envoie un email de notification de transmission au service destinataire ET Ã  tous ses utilisateurs actifs
     */
    private function sendEmailToService(Transmission $transmission, $serviceDestinataire, $courrier, $emetteur): void
    {
        $subject = "Nouvelle transmission - Courrier nÂ° {$courrier->getNumero()}";
        
        $htmlContent = $this->twig->render('emails/transmission/notification_transmission.html.twig', [
            'transmission' => $transmission,
            'serviceDestinataire' => $serviceDestinataire,
            'courrier' => $courrier,
            'emetteur' => $emetteur,
        ]);

        // Chemin vers le logo MINEPIA
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/cropped-logo-minepia.png';

        // 1ï¸âƒ£ Envoyer Ã  l'adresse email du service
        try {
            $this->mailService->sendEmailWithLogo($serviceDestinataire->getEmailService(), $subject, $htmlContent, $logoPath);
            $this->logger?->info('Email envoyé au service', [
                'transmission_id' => $transmission->getId(),
                'service' => $serviceDestinataire->getNom(),
                'email' => $serviceDestinataire->getEmailService()
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de l\'envoi de l\'email au service', [
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
                        $this->logger?->info('Email envoyé à l\'utilisateur', [
                            'transmission_id' => $transmission->getId(),
                            'user_id' => $user->getId(),
                            'email' => $user->getEmail()
                        ]);
                    } catch (\Exception $userException) {
                        $this->logger?->error('Erreur lors de l\'envoi de l\'email à un utilisateur', [
                            'transmission_id' => $transmission->getId(),
                            'user_id' => $user->getId(),
                            'exception' => $userException->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la récupération des utilisateurs pour l\'envoi d\'emails', [
                'transmission_id' => $transmission->getId(),
                'service' => $serviceDestinataire->getNom(),
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * ðŸ“± Envoie un SMS de notification de transmission au service destinataire ET Ã  tous ses utilisateurs actifs
     */
    private function sendSmsToService(Transmission $transmission, $serviceDestinataire, $courrier, $emetteur): array
    {
        // Message optimisÃ© pour tenir dans 160 caractÃ¨res avec le lien complet
        $numero = $courrier->getNumero();
        $emetteurNom = "{$emetteur->getFirstName()} {$emetteur->getLastName()}";
        
        // Si le nom de l'Ã©metteur est trop long, on le tronque
        if (mb_strlen($emetteurNom, 'UTF-8') > 20) {
            $emetteurNom = mb_substr($emetteurNom, 0, 20, 'UTF-8') . '.';
        }
        
        $message = "le courrier n°{$numero} vous a été transmis par {$emetteurNom}. consultez: https://minepia.cm/site/consultez-vos-dossiers/\nMerci.\nMail no {$numero} has been sent to you by {$emetteurNom}. See: https://minepia.cm/site/consultez-vos-dossiers/\nThank you.";
        
        $result = ['success' => false, 'service' => false, 'users' => 0];
        
        // 1ï¸âƒ£ Envoyer au numÃ©ro de tÃ©lÃ©phone du service
        try {
            $serviceResult = $this->smsService->sendSms(
                $serviceDestinataire->getTelephone(),
                $message,
                true // normalize
            );
            $result['service'] = $serviceResult['success'] ?? false;
            $result['success'] = $result['service'];
            
            $this->logger?->info('SMS envoyé au service', [
                'transmission_id' => $transmission->getId(),
                'service' => $serviceDestinataire->getNom(),
                'telephone' => $serviceDestinataire->getTelephone(),
                'success' => $result['service']
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de l\'envoi du SMS au service', [
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
                            true // normalize
                        );
                        
                        if ($userResult['success'] ?? false) {
                            $result['users']++;
                            $result['success'] = true; // Au moins un SMS envoyÃ© avec succÃ¨s
                        }
                        
                        $this->logger?->info('SMS envoyé à l\'utilisateur', [
                            'transmission_id' => $transmission->getId(),
                            'user_id' => $user->getId(),
                            'telephone' => $user->getPhone(),
                            'success' => $userResult['success'] ?? false
                        ]);
                    } catch (\Exception $userException) {
                        $this->logger?->error('Erreur lors de l\'envoi du SMS à un utilisateur', [
                            'transmission_id' => $transmission->getId(),
                            'user_id' => $user->getId(),
                            'exception' => $userException->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la récupération des utilisateurs pour l\'envoi de SMS', [
                'transmission_id' => $transmission->getId(),
                'service' => $serviceDestinataire->getNom(),
                'exception' => $e->getMessage()
            ]);
        }
        
        return $result;
    }

    /**
     * ðŸ”” CrÃ©e des notifications en base de donnÃ©es pour les utilisateurs des services concernÃ©s
     * 
     * âš ï¸ IMPORTANT : Cette mÃ©thode est isolÃ©e avec son propre try-catch pour Ã©viter
     * que des erreurs de notification n'empÃªchent la crÃ©ation de la transmission.
     * 
     * @param Transmission $transmission La transmission crÃ©Ã©e
     * @param Service|null $serviceDestinataire Le service destinataire principal
     * @param array|null $structuresCopie Tableau des IDs des services en copie
     * @param Courrier $courrier Le courrier concernÃ©
     * @return int Nombre total de notifications crÃ©Ã©es (0 en cas d'erreur)
     */
    private function createTransmissionNotifications(
        Transmission $transmission,
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
                    false, // pas en copie
                    'transmission'
                );
                $totalCount += $count;

                $this->logger?->info('Notifications créées pour le service destinataire', [
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
                            false, // pas en copie (notification normale pour le service parent)
                            'transmission_add'
                        );
                        $totalCount += $count;

                        $this->logger?->info('Notifications créées pour le service parent du destinataire', [
                            'transmission_id' => $transmission->getId(),
                            'service_parent' => $serviceParent->getNom(),
                            'count' => $count
                        ]);
                    } catch (\Exception $parentException) {
                        $this->logger?->error('Erreur lors de la création des notifications pour le service parent', [
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
                        // RÃ©cupÃ©rer le service par ID
                        $serviceCopie = $this->serviceRepository->find($serviceId);
                        
                        if ($serviceCopie) {
                            $count = $this->createNotificationsForService(
                                $serviceCopie,
                                $transmission,
                                $courrier,
                                true, // en copie
                                'transmission_copie'
                            );
                            $totalCount += $count;

                            $this->logger?->info('Notifications créées pour un service en copie', [
                                'transmission_id' => $transmission->getId(),
                                'service' => $serviceCopie->getNom(),
                                'count' => $count
                            ]);
                        } else {
                            $this->logger?->warning('Service en copie introuvable', [
                                'transmission_id' => $transmission->getId(),
                                'service_id' => $serviceId
                            ]);
                        }
                    } catch (\Exception $serviceException) {
                        // Log l'erreur mais continue pour les autres services
                        $this->logger?->error('Erreur lors de la création des notifications pour un service en copie', [
                            'transmission_id' => $transmission->getId(),
                            'service_id' => $serviceId,
                            'exception' => $serviceException->getMessage()
                        ]);
                    }
                }
            }

            $this->logger?->info('Notifications de transmission créées avec succès', [
                'transmission_id' => $transmission->getId(),
                'total_notifications' => $totalCount
            ]);

        } catch (\Exception $e) {
            // Log l'erreur gÃ©nÃ©rale mais ne fait pas Ã©chouer la crÃ©ation
            $this->logger?->error('Erreur générale lors de la création des notifications de transmission', [
                'transmission_id' => $transmission->getId(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }

        return $totalCount;
    }

    /**
     * ðŸ”” CrÃ©e des notifications pour tous les utilisateurs actifs d'un service donnÃ©
     * 
     * @param Service $service Le service concernÃ©
     * @param Transmission $transmission La transmission
     * @param Courrier $courrier Le courrier associÃ©
     * @param bool $isCopie Indique si c'est une notification en copie
     * @return int Nombre de notifications crÃ©Ã©es pour ce service
     */
    private function createNotificationsForService(
        $service,
        Transmission $transmission,
        $courrier,
        bool $isCopie = false,
        string $notificationType = 'transmission'
    ): int {
        $count = 0;

        try {
            // RÃ©cupÃ©rer tous les utilisateurs actifs du service
            $users = $this->userRepository->findBy([
                'idService' => $service,
                'isActive' => true,
                'isDelete' => false
            ]);

            if (empty($users)) {
                $this->logger?->info('Aucun utilisateur actif dans le service', [
                    'transmission_id' => $transmission->getId(),
                    'service' => $service->getNom()
                ]);
                return 0;
            }

            // DÃ©terminer le titre et le message selon si c'est en copie ou non
            if ($isCopie) {
                $titre = "Transmission en copie - Courrier n°{$courrier->getNumero()}";
                $message = "Un courrier vous a été transmis en copie pour information. Objet : {$courrier->getObjet()}";
            } else {
                $titre = "Nouvelle transmission - Courrier n°{$courrier->getNumero()}";
                $message = "Un courrier vous a été transmis pour traitement. Objet : {$courrier->getObjet()}";
            }

            // CrÃ©er une notification pour chaque utilisateur du service
            foreach ($users as $user) {
                try {
                    $notification = new Notification();
                    $notification->setTitre($titre);
                    $notification->setMessage($message);
                    $notification->setType($notificationType);
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
                    ]);
                    $notification->setUser($user);
                    $notification->setService($service);

                    $this->entityManager->persist($notification);
                    $count++;
                } catch (\Exception $userException) {
                    // Log l'erreur pour cet utilisateur mais continue pour les autres
                    $this->logger?->error('Erreur lors de la création d\'une notification pour un utilisateur', [
                        'transmission_id' => $transmission->getId(),
                        'user_id' => $user->getId(),
                        'service' => $service->getNom(),
                        'exception' => $userException->getMessage()
                    ]);
                }
            }

            // Flush dans un try-catch sÃ©parÃ© pour isoler les erreurs de BDD
            if ($count > 0) {
                try {
                    $this->entityManager->flush();
                } catch (\Exception $flushException) {
                    $this->logger?->error('Erreur lors du flush des notifications', [
                        'transmission_id' => $transmission->getId(),
                        'service' => $service->getNom(),
                        'exception' => $flushException->getMessage()
                    ]);
                    return 0;
                }
            }

        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la création des notifications pour le service', [
                'transmission_id' => $transmission->getId(),
                'service' => $service->getNom(),
                'exception' => $e->getMessage()
            ]);
            return 0;
        }

        return $count;
    }
}
