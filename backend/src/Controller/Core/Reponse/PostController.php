<?php

namespace App\Controller\Core\Reponse;

use App\Entity\Cour\Reponse;
use App\Entity\Cour\PieceJointe;
use App\Entity\Cour\Transmission;
use App\Entity\Cour\TransmissionReponse;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Core\ServiceRepository;
use App\Repository\Core\TypeReponseRepository;
use App\Repository\Core\UserRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FileService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\Core\NotificationService;
use App\Service\UserActionLoggerService;
use App\Service\MailService;
use App\Service\SmsService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;
use Twig\Environment;

#[OA\Tag(name: "Reponse")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FileService $fileService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private NotificationService $notificationService,
        private CourrierRepository $courrierRepository,
        private TransmissionRepository $transmissionRepository,
        private ServiceRepository $serviceRepository,
        private TypeReponseRepository $typeReponseRepository,
        private UserRepository $userRepository,
        private MailService $mailService,
        private SmsService $smsService,
        private UserActionLoggerService $actionLogger,
        private Environment $twig,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/reponse', name: 'app_core_reponse_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/reponse',
        summary: 'Créer une nouvelle réponse avec pièces jointes',
        tags: ['Reponse'],
        description: "Crée une réponse et upload les fichiers joints en une seule requête avec notifications par email et SMS optionnelles.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'courrierIds',
                            type: 'array',
                            items: new OA\Items(type: 'integer'),
                            example: [5, 7, 9],
                            description: 'Tableau des IDs des courriers associés'
                        ),
                        new OA\Property(property: 'idTypeReponse', type: 'integer', example: 1, description: 'ID du type de réponse'),
                        new OA\Property(
                            property: 'typesCourrierIds',
                            type: 'string',
                            example: '1,2,3',
                            description: 'IDs des types de courrier séparés par des virgules'
                        ),
                        new OA\Property(
                            property: 'idTransmission',
                            type: 'string',
                            example: '1,2,3',
                            description: 'IDs des transmissions associées séparées par des virgules (optionnel)'
                        ),
                        new OA\Property(
                            property: 'idReponses',
                            type: 'string',
                            example: '4,5,6',
                            description: 'IDs des réponses liées séparées par des virgules (optionnel)'
                        ),
                        new OA\Property(
                            property: 'idCourrierInternes',
                            type: 'string',
                            example: '4,5,6',
                            description: 'IDs des courriers internes liés séparées par des virgules (optionnel)'
                        ),
                        new OA\Property(property: 'objet', type: 'string', example: 'RE: Demande de budget'),
                        new OA\Property(property: 'commentairePublic', type: 'string', example: 'Voici notre réponse'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent', description: 'Classe du courrier (ex: Urgent, Normal, Confidentiel)'),
                        new OA\Property(property: 'typeTransmission', type: 'string', example: 'Electronique', description: 'Type de transmission (ex: Electronique, Physique, Courrier)'),
                        new OA\Property(property: 'priorite', type: 'string', example: 'Haute', description: 'Priorite de la reponse (ex: Basse, Moyenne, Haute)'),
                        new OA\Property(property: 'idServiceDestinataire', type: 'integer', example: 3),
                        new OA\Property(property: 'idRedacteur', type: 'integer', example: 2, description: 'Optionnel, sinon utilisateur connecté'),
                        new OA\Property(property: 'dateReponse', type: 'string', format: 'date', example: '2025-02-14'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2, description: 'Nombre de piéces jointes (simple champ entré par l\'utilisateur, aucune vérification)'),
                        
                        // PARAMÈTRE EMAIL
                        new OA\Property(property: 'sendMail', type: 'boolean', example: true, description: 'Envoyer un email aux utilisateurs du service destinataire'),
                        
                        // PARAMÈTRE SMS (NOUVEAU)
                        new OA\Property(property: 'sendSms', type: 'boolean', example: false, description: 'Envoyer un SMS aux utilisateurs du service destinataire'),
                        
                        new OA\Property(
                            property: 'piecesJointes[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'Fichiers Ã  joindre (optionnel)'
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
                description: 'Réponse créée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'Réponse créée avec succès'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'objet', type: 'string', example: 'RE: Demande de budget'),
                                new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                                new OA\Property(property: 'typeTransmission', type: 'string', example: 'Electronique'),
                                new OA\Property(property: 'typesCourrierIds', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                                new OA\Property(property: 'idTransmission', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                                new OA\Property(property: 'idReponses', type: 'array', items: new OA\Items(type: 'integer'), example: [4, 5, 6]),
                                new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2),
                                new OA\Property(property: 'priorite', type: 'string', example: 'Haute'),
                                new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                                new OA\Property(property: 'piecesJointesCount', type: 'integer', example: 2),
                                new OA\Property(
                                    property: 'notifications',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'emailSentToServiceUsers', type: 'boolean', example: true),
                                        new OA\Property(property: 'smsSentToServiceUsers', type: 'boolean', example: true),
                                    ]
                                ),
                                new OA\Property(property: 'idCourrierInternes', type: 'array', items: new OA\Items(type: 'integer'), example: [4, 5, 6]),
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
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PostReponse');

        $data = $request->request->all();
        
        // Recuperation des parametres d'envoi (non sauvegardes en base)
        $sendMail = filter_var($data['sendMail'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendSms = filter_var($data['sendSms'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (isset($data['id_reponses']) && !isset($data['idReponses'])) {
            $data['idReponses'] = $data['id_reponses'];
            unset($data['id_reponses']);
        }
        if (isset($data['id_courrier_internes']) && !isset($data['idCourrierInternes'])) {
            $data['idCourrierInternes'] = $data['id_courrier_internes'];
            unset($data['id_courrier_internes']);
        }
        
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'id', 'sendMail', 'sendSms', 'statut']);

        try {
            $reponse = new Reponse();

            // Variables pour l'envoi d'emails et SMS
            $courriers = [];
            $serviceDestinataire = null;
            $redacteur = null;

            // GESTION DES COURRIERS (plusieurs courriers possibles)
            $courrierIds = $data['courrierIds'] ?? null;
            if (is_string($courrierIds)) {
                $courrierIds = array_map('intval', array_filter(array_map('trim', explode(',', $courrierIds))));
            } elseif (is_array($courrierIds)) {
                $courrierIds = array_map('intval', array_filter($courrierIds));
            }
            if (!empty($courrierIds) && is_array($courrierIds)) {
                foreach ($courrierIds as $courrierId) {
                    $courrier = $this->courrierRepository->find($courrierId);
                    if (!$courrier) {
                        return $this->json([
                            'code' => 404, 
                            'message' => "Courrier avec l'ID {$courrierId} introuvable."
                        ], 404);
                    }
                    $reponse->addCourrier($courrier);
                    $courriers[] = $courrier;
                }
                unset($data['courrierIds']); // Retirer pour eviter qu'il soit traite par crudService
            }

            if (!empty($data['idTypeReponse'])) {
                $typeReponse = $this->typeReponseRepository->find($data['idTypeReponse']);
                if (!$typeReponse) {
                    return $this->json(['code' => 404, 'message' => 'Type de reponse introuvable.'], 404);
                }
                $reponse->setTypeReponse($typeReponse);
            }

            if (!empty($data['idServiceDestinataire'])) {
                $serviceDestinataire = $this->serviceRepository->find($data['idServiceDestinataire']);
                if (!$serviceDestinataire) {
                    return $this->json(['code' => 404, 'message' => 'Service destinataire introuvable.'], 404);
                }
                $currentUserService = $this->getUser()?->getIdService();
                if ($currentUserService && $currentUserService->getId() === $serviceDestinataire->getId()) {
                    return $this->json([
                        'code' => 400,
                        'message' => 'Vous ne pouvez pas creer un courrier interne a votre propre service.'
                    ], 400);
                }
                $reponse->setIdServiceDestinataire($serviceDestinataire);
            }

            if (!empty($data['idRedacteur'])) {
                $redacteur = $this->userRepository->find($data['idRedacteur']);
                if (!$redacteur) {
                    return $this->json(['code' => 404, 'message' => 'Redacteur introuvable.'], 404);
                }
                $reponse->setIdRedacteur($redacteur);
            } else {
                $redacteur = $this->getUser();
                $reponse->setIdRedacteur($redacteur);
            }

            // Gestion du tableau des types de courrier (format string "1,2,3")
            $typesCourrierIds = null;
            if (!empty($data['typesCourrierIds']) && is_string($data['typesCourrierIds'])) {
                $ids = array_map('intval', array_filter(explode(',', $data['typesCourrierIds'])));
                $typesCourrierIds = !empty($ids) ? $ids : null;
            }

            // Gestion du tableau des IDs de transmission (format string "1,2,3")
            $idTransmission = null;
            if (!empty($data['idTransmission']) && is_string($data['idTransmission'])) {
                $ids = array_map('intval', array_filter(explode(',', $data['idTransmission'])));
                $idTransmission = !empty($ids) ? $ids : null;
            }

            // Gestion du tableau des IDs de reponses (format string "1,2,3")
            $idReponses = null;
            if (!empty($data['idReponses'])) {
                if (is_string($data['idReponses'])) {
                    $ids = array_map('intval', array_filter(explode(',', $data['idReponses'])));
                    $idReponses = !empty($ids) ? $ids : null;
                } elseif (is_array($data['idReponses'])) {
                    $ids = array_map('intval', array_filter($data['idReponses']));
                    $idReponses = !empty($ids) ? $ids : null;
                }
            }

            // Gestion du tableau des IDs de courriers internes (format string "1,2,3")
            $idCourrierInternes = null;
            if (!empty($data['idCourrierInternes'])) {
                if (is_string($data['idCourrierInternes'])) {
                    $ids = array_map('intval', array_filter(explode(',', $data['idCourrierInternes'])));
                    $idCourrierInternes = !empty($ids) ? $ids : null;
                } elseif (is_array($data['idCourrierInternes'])) {
                    $ids = array_map('intval', array_filter($data['idCourrierInternes']));
                    $idCourrierInternes = !empty($ids) ? $ids : null;
                }
            }

            // Date de reponse automatique si non fournie
            $dateReponse = new \DateTime();
            if (!empty($data['dateReponse']) && is_string($data['dateReponse'])) {
                $dateReponse = new \DateTime($data['dateReponse']);
            }

            // Creer la reponse (seulement champs scalaires)
            $reponse = $this->crudService->postEntity($reponse, [
                'objet' => $data['objet'] ?? null,
                'commentairePublic' => $data['commentairePublic'] ?? null,
                'commentaireInterne' => $data['commentaireInterne'] ?? null,
                'classeCourrier' => $data['classeCourrier'] ?? null,
                'typeTransmission' => $data['typeTransmission'] ?? null,
                'priorite' => $data['priorite'] ?? null,
                'dateReponse' => $dateReponse,
                'typesCourrierIds' => $typesCourrierIds,
                'idTransmission' => $idTransmission,
                'idReponses' => $idReponses,
                'idCourrierInternes' => $idCourrierInternes,
                'nombrePieceJointe' => isset($data['nombrePieceJointe']) ? (int) $data['nombrePieceJointe'] : 0,
            ]);

            // Creation automatique de transmissions si des courriers sont fournis
            $createdTransmissionIds = [];
            $createdTransmissions = [];
            $currentUserService = $this->getUser()?->getIdService();
            $serviceDestinataireForTransmission = $reponse->getIdServiceDestinataire();
            $skipAutoTransmission = !$serviceDestinataireForTransmission
                || ($currentUserService && $serviceDestinataireForTransmission->getId() === $currentUserService->getId());
            if ($skipAutoTransmission) {
                $this->logger?->warning('Creation auto de transmission ignoree (service destinataire manquant ou identique au service utilisateur)', [
                    'reponse_id' => $reponse->getId(),
                    'service_destinataire_id' => $serviceDestinataireForTransmission?->getId(),
                    'service_utilisateur_id' => $currentUserService?->getId(),
                ]);
            }

            if (!empty($courriers) && !$skipAutoTransmission) {
                $traitePar = [];
                if ($redacteur) {
                    $traitePar[] = [
                        'action' => 'reponse',
                        'repondu_par_id' => $redacteur->getId(),
                        'date_traitement' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ];
                }

                foreach ($courriers as $courrierItem) {
                    try {
                        $instruction = $reponse->getCommentairePublic() ?? $reponse->getCommentaireInterne();

                        $transmissionData = [
                            'idCourrier' => $courrierItem,
                            'idServiceDestinataire' => $serviceDestinataireForTransmission,
                            'idEmetteur' => $redacteur,
                            'instruction' => $instruction,
                            'delaiTraitement' => null,
                            'typeTransfert' => $reponse->getTypeTransmission(),
                            'statut' => 'Transmis',
                            'structuresCopie' => [],
                            'nombrePieceJointe' => $reponse->getNombrePieceJointe(),
                            'dateInstruction' => new \DateTime(),
                            'traitePar' => $traitePar,
                        ];

                        $transmission = new Transmission();
                        $transmission = $this->crudService->postEntity($transmission, $transmissionData);
                        $createdTransmissionIds[] = $transmission->getId();
                        $createdTransmissions[] = $transmission;
                    } catch (\Exception $e) {
                        $this->logger?->error('Erreur lors de la creation automatique de transmission pour reponse', [
                            'reponse_id' => $reponse->getId(),
                            'courrier_id' => $courrierItem?->getId(),
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            }

            if (!empty($createdTransmissionIds)) {
                $allTransmissionIds = array_values(array_unique(array_merge($idTransmission ?? [], $createdTransmissionIds)));
                try {
                    $reponse = $this->crudService->patchEntity($reponse, [
                        'idTransmission' => $allTransmissionIds,
                    ]);
                } catch (\Exception $e) {
                    $this->logger?->error('Erreur lors de la mise a jour des transmissions de la reponse', [
                        'reponse_id' => $reponse->getId(),
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            // Creation automatique des transmissions reponse pour les courriers internes fournis
            $createdTransmissionReponseIds = [];
            $createdTransmissionReponses = [];
            if (!empty($idCourrierInternes) && is_array($idCourrierInternes)) {
                $autoReponseIds = array_values(array_unique(array_merge($idReponses ?? [], [$reponse->getId()])));

                foreach ($idCourrierInternes as $courrierInterneId) {
                    if (!is_numeric($courrierInterneId)) {
                        continue;
                    }
                    $transmissionReponse = new TransmissionReponse();
                    try {
                        $transmissionReponse = $this->crudService->postEntity($transmissionReponse, [
                            'typeReponse' => $reponse->getTypeReponse(),
                            'typesCourrierIds' => $reponse->getTypesCourrierIds(),
                            'idTransmission' => $reponse->getIdTransmission(),
                            'idReponses' => $autoReponseIds,
                            'idCourrierInternes' => [(int) $courrierInterneId],
                            'objet' => $reponse->getObjet(),
                            'commentairePublic' => $reponse->getCommentairePublic(),
                            'commentaireInterne' => $reponse->getCommentaireInterne(),
                            'classeCourrier' => $reponse->getClasseCourrier(),
                            'typeTransmission' => $reponse->getTypeTransmission(),
                            'priorite' => $reponse->getPriorite(),
                            'idServiceDestinataire' => $reponse->getIdServiceDestinataire(),
                            'idRedacteur' => $reponse->getIdRedacteur(),
                            'dateReponse' => $reponse->getDateReponse(),
                            'nombrePieceJointe' => $reponse->getNombrePieceJointe(),
                        ]);
                        $createdTransmissionReponseIds[] = $transmissionReponse->getId();
                        $createdTransmissionReponses[] = $transmissionReponse;
                    } catch (\Exception $e) {
                        $this->logger?->error('Erreur lors de la creation automatique de transmission reponse pour courrier interne', [
                            'reponse_id' => $reponse->getId(),
                            'courrier_interne_id' => $courrierInterneId,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            }
            $createdTransmissionReponseId = $createdTransmissionReponseIds[0] ?? null;

            //  MISE Ã€ JOUR DU STATUT DES TRANSMISSIONS
            // Si des IDs de transmission sont fournis, on met Ã  jour leur statut Ã  "TraitÃ©"
            if (!empty($idTransmission) && is_array($idTransmission)) {
                foreach ($idTransmission as $transmissionId) {
                    $transmission = $this->transmissionRepository->find($transmissionId);
                    if ($transmission) {
                        // Mise Ã  jour du statut
                        $transmission->setStatut('Traité');
                        
                        // Ajout d'une entrÃ©e dans traite_par si le rÃ©dacteur est dÃ©fini
                        if ($redacteur) {
                            $traitePar = $transmission->getTraitePar() ?? [];
                            $traitePar[] = [
                                'action' => 'reponse',
                                'repondu_par_id' => $redacteur->getId(),
                                'date_traitement' => (new \DateTime())->format('Y-m-d H:i:s'),
                            ];
                            $transmission->setTraitePar($traitePar);
                        }
                        
                        $this->crudService->postEntity($transmission, []);
                    }
                }
            }

            //  AJOUT DES PIECES JOINTES
            $uploadedCount = 0;
            $uploadedPieces = [];
            
            // RÃ©cupÃ©rer les intitulÃ©s (optionnel)
            $intitulesInput = $request->request->get('intitulesPiecesJointes', '');
            $intitules = $this->parseIntitules($intitulesInput);
            
            if (!empty($request->files->get('piecesJointes'))) {
                $files = $request->files->get('piecesJointes');
                
                foreach ($files as $index => $file) {
                    $filePath = $this->fileService->uploadFile(
                        $this->getParameter('app_uploads_courrier_piece_directory'),
                        $file
                    );

                    if ($filePath) {
                        $pieceData = [
                            'nom' => $file->getClientOriginalName(),
                            'intitule' => $intitules[$index] ?? null,
                            'chemin' => $this->getParameter('app_uploads_courrier_piece') . $filePath,
                            'type' => $file->getClientMimeType(),
                        ];

                        $piece = new PieceJointe();
                        $piece->setNom($pieceData['nom']);
                        $piece->setIntitule($pieceData['intitule']);
                        $piece->setChemin($pieceData['chemin']);
                        $piece->setType($pieceData['type']);
                        $piece->setIdParent($reponse->getId());
                        $piece->setTypeParent('Reponse');

                        $this->crudService->postEntity($piece, []);
                        $uploadedCount++;
                        $uploadedPieces[] = $pieceData;
                    }
                }
            }

            // Dupliquer les pieces jointes de la reponse vers chaque transmission reponse creee automatiquement
            if (!empty($uploadedPieces) && !empty($createdTransmissionReponses)) {
                foreach ($createdTransmissionReponses as $transmissionReponse) {
                    foreach ($uploadedPieces as $pieceData) {
                        $piece = new PieceJointe();
                        $piece->setNom($pieceData['nom'] ?? null);
                        $piece->setIntitule($pieceData['intitule'] ?? null);
                        $piece->setChemin($pieceData['chemin'] ?? null);
                        $piece->setType($pieceData['type'] ?? null);
                        $piece->setIdParent($transmissionReponse->getId());
                        $piece->setTypeParent('TransmissionReponse');

                        $this->crudService->postEntity($piece, []);
                    }
                }
            }

            // Dupliquer les pieces jointes de la reponse vers chaque transmission creee automatiquement
            if (!empty($uploadedPieces) && !empty($createdTransmissions)) {
                foreach ($createdTransmissions as $transmission) {
                    foreach ($uploadedPieces as $pieceData) {
                        $piece = new PieceJointe();
                        $piece->setNom($pieceData['nom'] ?? null);
                        $piece->setIntitule($pieceData['intitule'] ?? null);
                        $piece->setChemin($pieceData['chemin'] ?? null);
                        $piece->setType($pieceData['type'] ?? null);
                        $piece->setIdParent($transmission->getId());
                        $piece->setTypeParent('Transmission');

                        $this->crudService->postEntity($piece, []);
                    }
                }
            }

            //  NOTIFICATIONS EN BD POUR LE SERVICE DESTINATAIRE
            $dbNotificationCount = 0;
            try {
                if ($serviceDestinataire) {
                    $dbNotificationCount = $this->notificationService->createNotificationForService(
                        service: $serviceDestinataire,
                        titre: 'Réponse pour votre service',
                        message: 'vous avez reçu une nouvelle réponse - ' . ($reponse->getObjet() ?? ''),
                        type: 'reponse',
                        data: [
                            'reponse_id' => $reponse->getId(),
                            'transmission_reponse_id' => $createdTransmissionReponseId,
                        ]
                    );
                } else {
                    $this->logger?->warning('Notification BD demandee mais service destinataire manquant pour reponse', [
                        'reponse_id' => $reponse->getId(),
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger?->error('Erreur lors de la creation des notifications BD pour reponse', [
                    'reponse_id' => $reponse->getId(),
                    'exception' => $e->getMessage()
                ]);
            }

            //  GESTION DES NOTIFICATIONS (EMAILS + SMS)
            $notificationResults = $this->handleAllNotifications(
                $reponse, 
                $serviceDestinataire, 
                $redacteur, 
                $courriers, // Passage d'un tableau de courriers
                $sendMail, 
                $sendSms
            );

            // Logger la crÃ©ation avec toutes les donnÃ©es
            $this->actionLogger->logCreate(
                'Reponse',
                $reponse->getId(),
                'Création d\'une nouvelle réponse',
                [
                    'reponse' => [
                        'id' => $reponse->getId(),
                        'objet' => $reponse->getObjet(),
                        'commentairePublic' => $reponse->getCommentairePublic(),
                        'classeCourrier' => $reponse->getClasseCourrier(),
                        'typeTransmission' => $reponse->getTypeTransmission(),
                        'priorite' => $reponse->getPriorite(),
                        'statut' => $reponse->getStatut(),
                        'typesCourrierIds' => $reponse->getTypesCourrierIds(),
                        'idReponses' => $reponse->getIdReponses(),
                        'idCourrierInternes' => $reponse->getIdCourrierInternes(),
                        'courrierIds' => $reponse->getCourrierIds(),
                        'typeReponse' => $reponse->getTypeReponse()?->getNom(),
                        'serviceDestinataire' => $reponse->getIdServiceDestinataire()?->getNom(),
                        'redacteur' => $redacteur->getUserIdentifier(),
                        'dateReponse' => $reponse->getDateReponse()?->format('Y-m-d'),
                    ],
                    'piecesJointesCount' => $uploadedCount,
                    'notifications' => $notificationResults,
                    'dbNotificationsCount' => $dbNotificationCount,
                    'transmissionReponseId' => $createdTransmissionReponseId,
                ]
            );

            return $this->json([
                'code' => 201,
                'message' => 'courrier interne crée avec succès',
                'data' => [
                    'id' => $reponse->getId(),
                    'objet' => $reponse->getObjet(),
                    'classeCourrier' => $reponse->getClasseCourrier(),
                    'typeTransmission' => $reponse->getTypeTransmission(),
                    'priorite' => $reponse->getPriorite(),
                    'statut' => $reponse->getStatut(),
                    'typesCourrierIds' => $reponse->getTypesCourrierIds(),
                    'idTransmission' => $reponse->getIdTransmission(),
                    'idReponses' => $reponse->getIdReponses(),
                    'idCourrierInternes' => $reponse->getIdCourrierInternes(),
                    'courrierIds' => $reponse->getCourrierIds(),
                    'nombrePieceJointe' => $reponse->getNombrePieceJointe(),
                    'transmissionReponseId' => $createdTransmissionReponseId,
                    'piecesJointesCount' => $uploadedCount,
                    'notifications' => $notificationResults,
                ]
            ], 201);

        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GÃƒÂ¨re l'envoi de toutes les notifications (emails + SMS) lors de la crÃƒÂ©ation d'une rÃƒÂ©ponse
     */
    private function handleAllNotifications(
        Reponse $reponse,
        $serviceDestinataire,
        $redacteur,
        array $courriers = [],
        bool $sendMail = false,
        bool $sendSms = false
    ): array {
        $results = [
            'emailSentToServiceUsers' => false,
            'smsSentToServiceUsers' => false,
        ];

        try {
            if ($sendMail) {
                if ($serviceDestinataire) {
                    $results['emailSentToServiceUsers'] = $this->sendEmailToServiceUsers($reponse, $serviceDestinataire, $courriers);
                } else {
                    $this->logger?->warning('Email service destinataire demande mais service manquant pour reponse', [
                        'reponse_id' => $reponse->getId(),
                    ]);
                }
            }

            if ($sendSms) {
                if ($serviceDestinataire) {
                    $results['smsSentToServiceUsers'] = $this->sendSmsToServiceUsers($reponse, $serviceDestinataire, $courriers);
                } else {
                    $this->logger?->warning('SMS service destinataire demande mais service manquant pour reponse', [
                        'reponse_id' => $reponse->getId(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas échouer la création de la réponse
            $this->logger?->error('Erreur lors de l\'envoi des notifications pour réponse', [
                'reponse_id' => $reponse->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * Envoie un SMS de notification aux utilisateurs actifs du service destinataire
     */
    private function sendSmsToServiceUsers(Reponse $reponse, $serviceDestinataire, $courrier = null): bool
    {
        $courrierItem = is_array($courrier) ? ($courrier[0] ?? null) : $courrier;

        if ($courrierItem) {
            $message = "MINEPIA: Merci de prendre connaissance du courrier n°{$courrierItem->getNumero()}. Objet: {$reponse->getObjet()}. qui vous a été transmis par: {$reponse->getIdRedacteur()?->getFirstName()} {$reponse->getIdRedacteur()?->getLastName()}.\n MINEPIA: kindly review the letter n°{$courrierItem->getNumero()}. Subject: {$reponse->getObjet()}. which has been sent to you by: {$reponse->getIdRedacteur()?->getFirstName()} {$reponse->getIdRedacteur()?->getLastName()}";
        } else {
            $message = "MINEPIA: Merci de prendre connaissance du courrier n°{$courrierItem->getNumero()}. Objet: {$reponse->getObjet()}. qui vous a été transmis par: {$reponse->getIdRedacteur()?->getFirstName()} {$reponse->getIdRedacteur()?->getLastName()}.\n MINEPIA: kindly review the internal letter. Subject: {$reponse->getObjet()}. which has been sent to you by: {$reponse->getIdRedacteur()?->getFirstName()} {$reponse->getIdRedacteur()?->getLastName()}";
        }

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

                        $this->logger?->info('SMS envoye a l\'utilisateur', [
                            'reponse_id' => $reponse->getId(),
                            'user_id' => $user->getId(),
                            'telephone' => $user->getPhone(),
                            'success' => $userResult['success'] ?? false
                        ]);
                    } catch (\Exception $userException) {
                        $this->logger?->error('Erreur lors de l\'envoi du SMS a un utilisateur', [
                            'reponse_id' => $reponse->getId(),
                            'user_id' => $user->getId(),
                            'exception' => $userException->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la recuperation des utilisateurs pour l\'envoi de SMS', [
                'reponse_id' => $reponse->getId(),
                'service_id' => $serviceDestinataire?->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $sentCount > 0;
    }

    /**
     * Envoie un email de notification aux utilisateurs actifs du service destinataire
     */
    private function sendEmailToServiceUsers(Reponse $reponse, $serviceDestinataire, $courrier = null): bool
    {
        $courrierItem = is_array($courrier) ? ($courrier[0] ?? null) : $courrier;
        $subject = "Nouveau courrier interne - {$reponse->getObjet()}";

        $htmlContent = $this->twig->render('emails/reponse/notification_service_destinataire.html.twig', [
            'reponse' => $reponse,
            'serviceDestinataire' => $serviceDestinataire,
            'courrier' => $courrierItem,
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
                        $this->logger?->info('Email envoye a l\'utilisateur', [
                            'reponse_id' => $reponse->getId(),
                            'user_id' => $user->getId(),
                            'email' => $user->getEmail()
                        ]);
                    } catch (\Exception $userException) {
                        $this->logger?->error('Erreur lors de l\'envoi de l\'email a un utilisateur', [
                            'reponse_id' => $reponse->getId(),
                            'user_id' => $user->getId(),
                            'exception' => $userException->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la recuperation des utilisateurs pour l\'envoi d\'emails', [
                'reponse_id' => $reponse->getId(),
                'service_id' => $serviceDestinataire?->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $sentCount > 0;
    }

    /**
     * Envoie un email de confirmation au rÃƒÂ©dacteur
     */
    private function sendEmailToRedacteur(Reponse $reponse, $redacteur, $courrier = null): void
    {
        $subject = "Confirmation de création de courrier interne - {$reponse->getObjet()}";
        
        $htmlContent = $this->twig->render('emails/reponse/confirmation_redacteur.html.twig', [
            'reponse' => $reponse,
            'redacteur' => $redacteur,
            'courrier' => $courrier,
        ]);

        $this->mailService->sendEmail($redacteur->getEmail(), $subject, $htmlContent);
    }


    private function parseIntitules(string $input): array
    {
        if (empty(trim($input))) {
            return [];
        }

        // Tenter de d'encoder comme JSON
        $decoded = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_map('trim', $decoded);
        }

        // VÃ©rifier le sÃ©parateur |||
        if (strpos($input, '|||') !== false) {
            return array_map('trim', explode('|||', $input));
        }

        // VÃ©rifier le sÃ©parateur point-virgule
        if (strpos($input, ';') !== false) {
            return array_map('trim', explode(';', $input));
        }

        // Par dÃ©faut, sÃ©parer par retour Ã  la ligne
        return array_map('trim', preg_split('/\r\n|\r|\n/', $input));
    }
}



