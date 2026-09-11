<?php

namespace App\Controller\Core\CourrierInterne;

use App\Entity\Cour\CourrierInterne;
use App\Entity\Cour\PieceJointe;
use App\Repository\Cour\CourrierInterneRepository;
use App\Repository\Cour\PieceJointeRepository;
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

#[OA\Tag(name: "CourrierInterne")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FileService $fileService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private NotificationService $notificationService,
        private CourrierInterneRepository $courrierInterneRepository,
        private PieceJointeRepository $pieceJointeRepository,
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

    #[Route('/core/courrier-interne/{id}', name: 'app_core_courrier_interne_patch', methods: ['POST'])]
    #[OA\Post(
        path: '/core/courrier-interne/{id}',
        summary: 'Met a jour un courrier interne existant',
        tags: ['CourrierInterne'],
        description: "Met a jour les informations d'un courrier interne et gere les pieces jointes avec notifications par email et SMS optionnelles.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'idTypeReponse', type: 'integer', example: 1),
                        new OA\Property(property: 'typesCourrierIds', type: 'string', example: '1,2,3', description: 'IDs séparés par virgules'),
                        new OA\Property(property: 'idTransmission', type: 'string', example: '1,2,3', description: 'IDs des transmissions associées séparés par des virgules (optionnel)'),
                        new OA\Property(property: 'idReponses', type: 'string', example: '4,5,6', description: 'IDs des réponses liées séparés par des virgules (optionnel)'),
                        new OA\Property(property: 'objet', type: 'string', example: 'RE: Demande de budget'),
                        new OA\Property(property: 'commentairePublic', type: 'string', example: 'Voici notre réponse'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent', description: 'Classe du courrier (ex: Urgent, Normal, Confidentiel)'),
                        new OA\Property(property: 'typeTransmission', type: 'string', example: 'Electronique', description: 'Type de transmission (ex: Electronique, Physique, Courrier)'),
                        new OA\Property(property: 'priorite', type: 'string', example: 'Haute', description: 'Priorite du courrier interne (ex: Basse, Normal, Haute)'),
                        new OA\Property(property: 'idServiceDestinataire', type: 'integer', example: 3),
                        new OA\Property(property: 'idRedacteur', type: 'integer', example: 2),
                        new OA\Property(property: 'dateReponse', type: 'string', format: 'date', example: '2025-02-14'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2, description: 'Nombre de pièces jointes (simple champ entré par l\'utilisateur, aucune vérification)'),
                        
                        new OA\Property(property: 'sendMail', type: 'boolean', example: true, description: 'Envoyer un email de mise à jour au service destinataire et au rédacteur'),
                        
                        new OA\Property(property: 'sendSms', type: 'boolean', example: false, description: 'Envoyer un SMS de mise à jour au service destinataire et au rédacteur'),
                        
                        new OA\Property(
                            property: 'piecesJointes[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'Nouveaux fichiers à ajouter'
                        ),
                        new OA\Property(
                            property: 'intitulesPiecesJointes',
                            type: 'string',
                            example: '["Rapport financier", "Justificatif", "Annexe"]',
                            description: 'Intitulés des nouvelles pièces jointes. Formats acceptés: JSON array, séparation par retour à la ligne, par "|||" ou par ";"'
                        ),
                        new OA\Property(
                            property: 'keepPiecesJointes',
                            type: 'string',
                            example: '1,2,5',
                            description: 'IDs des PJ à garder (les autres seront supprimées)'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier interne mis à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier interne mis à jour avec succès'),
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
                                new OA\Property(property: 'piecesJointesAdded', type: 'integer', example: 2),
                                new OA\Property(property: 'piecesJointesDeleted', type: 'integer', example: 1),
                                new OA\Property(
                                    property: 'notifications',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'emailSentToService', type: 'boolean', example: true),
                                        new OA\Property(property: 'emailSentToRedacteur', type: 'boolean', example: true),
                                        new OA\Property(property: 'smsSentToService', type: 'boolean', example: true),
                                        new OA\Property(property: 'smsSentToRedacteur', type: 'boolean', example: false),
                                    ]
                                ),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier interne non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PatchReponse');

        $courrierInterne = $this->courrierInterneRepository->find($id);

        if (!$courrierInterne) {
            return $this->json(['code' => 404, 'message' => 'Courrier interne non trouvé.'], 404);
        }

        // Sauvegarder les anciennes valeurs pour le log
        $oldData = [
            'id' => $courrierInterne->getId(),
            'objet' => $courrierInterne->getObjet(),
            'commentairePublic' => $courrierInterne->getCommentairePublic(),
            'classeCourrier' => $courrierInterne->getClasseCourrier(),
            'typeTransmission' => $courrierInterne->getTypeTransmission(),
            'priorite' => $courrierInterne->getPriorite(),
            'statut' => $courrierInterne->getStatut(),
            'typesCourrierIds' => $courrierInterne->getTypesCourrierIds(),
            'idReponses' => $courrierInterne->getIdReponses(),
            'typeReponse' => $courrierInterne->getTypeReponse()?->getNom(),
            'serviceDestinataire' => $courrierInterne->getIdServiceDestinataire()?->getNom(),
            'redacteur' => $courrierInterne->getIdRedacteur()?->getUserIdentifier(),
            'dateReponse' => $courrierInterne->getDateReponse()?->format('Y-m-d'),
        ];


        // Récupérer les données du formulaire (sans les fichiers pour l'instant)
        $data = $request->request->all();
        
        if (empty($data) && !$request->files->count()) {
            return $this->json(['code' => 400, 'message' => 'Aucune donnée fournie.'], 400);
        }

        // Récupération des paramètres d'envoi (non sauvegardés en base)
        $sendMail = filter_var($data['sendMail'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendSms = filter_var($data['sendSms'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (isset($data['id_reponses']) && !isset($data['idReponses'])) {
            $data['idReponses'] = $data['id_reponses'];
            unset($data['id_reponses']);
        }

        // Sauvegarde des anciennes valeurs pour dÃ©tecter les changements
        $oldServiceDestinataire = $courrierInterne->getIdServiceDestinataire();
        $oldRedacteur = $courrierInterne->getIdRedacteur();

        // Exclure les champs non modifiables
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'idCourrier', 'id', 'sendMail', 'sendSms', 'statut']);

        try {
            // Variables pour stocker les nouvelles relations
            $newServiceDestinataire = $oldServiceDestinataire;
            $newRedacteur = $oldRedacteur;

            // âœ… GESTION DES RELATIONS - Setter directement sur l'entitÃ©
            if (isset($data['idTypeReponse'])) {
                if (!empty($data['idTypeReponse'])) {
                    $typeReponse = $this->typeReponseRepository->find($data['idTypeReponse']);
                    if (!$typeReponse) {
                        return $this->json(['code' => 404, 'message' => 'Type de réponse introuvable.'], 404);
                    }
                    $courrierInterne->setTypeReponse($typeReponse);
                } else {
                    $courrierInterne->setTypeReponse(null);
                }
                unset($data['idTypeReponse']);
            }

            if (isset($data['idServiceDestinataire'])) {
                if (!empty($data['idServiceDestinataire'])) {
                    $serviceDestinataire = $this->serviceRepository->find($data['idServiceDestinataire']);
                    if (!$serviceDestinataire) {
                        return $this->json(['code' => 404, 'message' => 'Service destinataire introuvable.'], 404);
                    }
                    $courrierInterne->setIdServiceDestinataire($serviceDestinataire);
                    $newServiceDestinataire = $serviceDestinataire;
                } else {
                    $courrierInterne->setIdServiceDestinataire(null);
                    $newServiceDestinataire = null;
                }
                unset($data['idServiceDestinataire']);
            }

            if (isset($data['idRedacteur'])) {
                if (!empty($data['idRedacteur'])) {
                    $redacteur = $this->userRepository->find($data['idRedacteur']);
                    if (!$redacteur) {
                        return $this->json(['code' => 404, 'message' => 'Rédacteur introuvable.'], 404);
                    }
                    $courrierInterne->setIdRedacteur($redacteur);
                    $newRedacteur = $redacteur;
                } else {
                    $courrierInterne->setIdRedacteur(null);
                    $newRedacteur = null;
                }
                unset($data['idRedacteur']);
            }

            // Gestion du tableau des types de courrier (format string "1,2,3")
            if (isset($data['typesCourrierIds'])) {
                if (!empty($data['typesCourrierIds']) && is_string($data['typesCourrierIds'])) {
                    $ids = array_map('intval', array_filter(explode(',', $data['typesCourrierIds'])));
                    $data['typesCourrierIds'] = !empty($ids) ? $ids : null;
                } else {
                    $data['typesCourrierIds'] = null;
                }
            }

            // Gestion du tableau des IDs de transmission (format string "1,2,3")
            $idTransmission = null;
            if (isset($data['idTransmission'])) {
                if (!empty($data['idTransmission']) && is_string($data['idTransmission'])) {
                    $ids = array_map('intval', array_filter(explode(',', $data['idTransmission'])));
                    $idTransmission = !empty($ids) ? $ids : null;
                }
                $data['idTransmission'] = $idTransmission;
            }

            // Gestion du tableau des IDs de réponses (format string "1,2,3")
            $idReponses = null;
            if (isset($data['idReponses'])) {
                if (!empty($data['idReponses']) && is_string($data['idReponses'])) {
                    $ids = array_map('intval', array_filter(explode(',', $data['idReponses'])));
                    $idReponses = !empty($ids) ? $ids : null;
                } elseif (is_array($data['idReponses'])) {
                    $ids = array_map('intval', array_filter($data['idReponses']));
                    $idReponses = !empty($ids) ? $ids : null;
                }
                $data['idReponses'] = $idReponses;
            }

            // âœ… Conversion de la date si fournie
            if (isset($data['dateReponse']) && !empty($data['dateReponse'])) {
                $data['dateReponse'] = new \DateTime($data['dateReponse']);
            }

            // âœ… Conversion du nombrePieceJointe en entier
            if (isset($data['nombrePieceJointe'])) {
                $data['nombrePieceJointe'] = (int)$data['nombrePieceJointe'];
            }

            // Supprimer keepPiecesJointes avant patchEntity
            $keepPiecesJointes = $data['keepPiecesJointes'] ?? null;
            unset($data['keepPiecesJointes']);

            //  Mise à  jour de la réponse (seulement champs scalaires)
            $this->crudService->patchEntity($courrierInterne, $data);

            $deletedCount = 0;

            // ï¿½ MISE Ã€ JOUR DU STATUT DES TRANSMISSIONS (NOUVEAU)
            // Si des IDs de transmission sont fournis, on met Ã  jour leur statut Ã  "TraitÃ©"
            if (!empty($idTransmission) && is_array($idTransmission)) {
                foreach ($idTransmission as $transmissionId) {
                    $transmission = $this->transmissionRepository->find($transmissionId);
                    if ($transmission) {
                        // Mise Ã  jour du statut
                        $transmission->setStatut('Traité');
                        
                        // Ajout d'une entrÃ©e dans traite_par si le rÃ©dacteur est dÃ©fini
                        if ($newRedacteur) {
                            $traitePar = $transmission->getTraitePar() ?? [];
                            $traitePar[] = [
                                'action' => 'reponse',
                                'accuse_par_id' => $newRedacteur->getId(),
                                'date_traitement' => (new \DateTime())->format('Y-m-d H:i:s'),
                            ];
                            $transmission->setTraitePar($traitePar);
                        }
                        
                        $this->crudService->postEntity($transmission, []);
                    }
                }
            }

            // ï¿½ðŸ—‘ï¸ GESTION DES PIÃˆCES JOINTES EXISTANTES
            $deletedCount = 0;
            if ($keepPiecesJointes !== null) {
                // Le front envoie les IDs Ã  garder
                $idsToKeep = !empty($keepPiecesJointes) ? array_map('intval', explode(',', $keepPiecesJointes)) : [];
                
                // RÃ©cupÃ©rer toutes les PJ actuelles
                $allPieces = $this->pieceJointeRepository->findBy([
                    'idParent' => $courrierInterne->getId(),
                    'typeParent' => 'CourrierInterne',
                    'isDelete' => false
                ]);

                // Supprimer celles qui ne sont pas dans la liste Ã  garder
                foreach ($allPieces as $piece) {
                    if (!in_array($piece->getId(), $idsToKeep)) {
                        $piece->setDelete(true); // Soft delete
                        $deletedCount++;
                    }
                }
            }

            // ðŸ“Ž AJOUT DE NOUVELLES PIÃˆCES JOINTES
            $uploadedCount = 0;
            
            // RÃ©cupÃ©rer les intitulÃ©s (optionnel)
            $intitulesInput = $request->request->get('intitulesPiecesJointes', '');
            $intitules = $this->parseIntitules($intitulesInput);
            
            if (!empty($request->files->get('piecesJointes'))) {
                foreach ($request->files->get('piecesJointes') as $index => $file) {
                    $filePath = $this->fileService->uploadFile(
                        $this->getParameter('app_uploads_courrier_piece_directory'),
                        $file
                    );

                    if ($filePath) {
                        $piece = new PieceJointe();
                        $piece->setNom($file->getClientOriginalName());
                        $piece->setIntitule($intitules[$index] ?? null);
                        $piece->setChemin($this->getParameter('app_uploads_courrier_piece') . $filePath);
                        $piece->setType($file->getClientMimeType());
                        $piece->setIdParent($courrierInterne->getId());
                        $piece->setTypeParent('CourrierInterne');

                        $this->crudService->postEntity($piece, []);
                        $uploadedCount++;
                    }
                }
            }

            // ðŸ†• GESTION DES NOTIFICATIONS (EMAILS + SMS)
            //  NOTIFICATIONS EN BD POUR LE SERVICE DESTINATAIRE
            $dbNotificationCount = 0;
            try {
                if ($newServiceDestinataire) {
                    $dbNotificationCount = $this->notificationService->createNotificationForService(
                        service: $newServiceDestinataire,
                        titre: 'courrier interne pour votre service',
                        message: 'courrier interne mis a jour - ' . ($courrierInterne->getObjet() ?? ''),
                        type: 'CourrierInterne',
                        data: [
                            'reponse_id' => $courrierInterne->getId(),
                            'transmission_reponse_id' => null,
                        ]
                    );
                } else {
                    $this->logger?->warning('Notification BD demandee mais service destinataire manquant pour courrier interne (mise a jour)', [
                        'reponse_id' => $courrierInterne->getId(),
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger?->error('Erreur lors de la creation des notifications BD pour courrier interne (mise a jour)', [
                    'reponse_id' => $courrierInterne->getId(),
                    'exception' => $e->getMessage()
                ]);
            }

            $notificationResults = $this->handleAllNotifications(
                $courrierInterne,
                $newServiceDestinataire,
                $newRedacteur,
                $sendMail,
                $sendSms,
                $oldServiceDestinataire,
                $oldRedacteur
            );

            // Logger la mise Ã  jour avec anciennes et nouvelles valeurs
            $this->actionLogger->logUpdate(
                'CourrierInterne',
                $courrierInterne->getId(),
                'Mise a jour d\'un courrier interne',
                [
                    'before' => $oldData,
                    'after' => [
                        'id' => $courrierInterne->getId(),
                        'objet' => $courrierInterne->getObjet(),
                        'commentairePublic' => $courrierInterne->getCommentairePublic(),
                        'classeCourrier' => $courrierInterne->getClasseCourrier(),
                        'typeTransmission' => $courrierInterne->getTypeTransmission(),
                        'priorite' => $courrierInterne->getPriorite(),
                        'statut' => $courrierInterne->getStatut(),
                        'typesCourrierIds' => $courrierInterne->getTypesCourrierIds(),
                        'idReponses' => $courrierInterne->getIdReponses(),
                        'typeReponse' => $courrierInterne->getTypeReponse()?->getNom(),
                        'serviceDestinataire' => $courrierInterne->getIdServiceDestinataire()?->getNom(),
                        'redacteur' => $courrierInterne->getIdRedacteur()?->getUserIdentifier(),
                        'dateReponse' => $courrierInterne->getDateReponse()?->format('Y-m-d'),
                    ],
                    'changes' => [
                        'piecesJointesAdded' => $uploadedCount,
                        'piecesJointesDeleted' => $deletedCount,
                    ],
                    'notifications' => $notificationResults,
                    'dbNotificationsCount' => $dbNotificationCount,
                ]
            );

            return $this->json([
                'code' => 200,
                'message' => 'Courrier interne mis a jour avec succes',
                'data' => [
                    'id' => $courrierInterne->getId(),
                    'objet' => $courrierInterne->getObjet(),
                    'classeCourrier' => $courrierInterne->getClasseCourrier(),
                    'typeTransmission' => $courrierInterne->getTypeTransmission(),
                    'priorite' => $courrierInterne->getPriorite(),
                    'statut' => $courrierInterne->getStatut(),
                    'typesCourrierIds' => $courrierInterne->getTypesCourrierIds(),
                    'idTransmission' => $courrierInterne->getIdTransmission(),
                    'idReponses' => $courrierInterne->getIdReponses(),
                    'nombrePieceJointe' => $courrierInterne->getNombrePieceJointe(),
                    'piecesJointesAdded' => $uploadedCount,
                    'piecesJointesDeleted' => $deletedCount,
                    'notifications' => $notificationResults,
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GÃ¨re l'envoi de toutes les notifications (emails + SMS) lors de la mise Ã  jour d'une rÃ©ponse
     */
    private function handleAllNotifications(
        CourrierInterne $courrierInterne,
        $newServiceDestinataire,
        $newRedacteur,
        bool $sendMail,
        bool $sendSms,
        $oldServiceDestinataire = null,
        $oldRedacteur = null
    ): array {
        $results = [
            'emailSentToService' => false,
            'emailSentToRedacteur' => false,
            'smsSentToService' => false,
            'smsSentToRedacteur' => false,
        ];

        try {
            // ðŸ“§ EMAILS
            if ($sendMail) {
                // 1. Email au service destinataire (si email service renseignÃ©)
                if ($newServiceDestinataire && !empty($newServiceDestinataire->getEmailService())) {
                    $this->sendUpdateEmailToService($courrierInterne, $newServiceDestinataire, $oldServiceDestinataire);
                    $results['emailSentToService'] = true;
                }

                // 2. Email au rÃ©dacteur (si email utilisateur renseignÃ©)
                if ($newRedacteur && !empty($newRedacteur->getEmail())) {
                    $this->sendUpdateEmailToRedacteur($courrierInterne, $newRedacteur, $oldRedacteur);
                    $results['emailSentToRedacteur'] = true;
                }
            }

            // ðŸ“± SMS avec validation intÃ©grÃ©e (ne bloque pas la mise Ã  jour)
            if ($sendSms) {
                // 1. SMS au service destinataire (si numÃ©ro de tÃ©lÃ©phone service renseignÃ©)
                if ($newServiceDestinataire && !empty($newServiceDestinataire->getTelephone())) {
                    $smsResult = $this->sendSmsToService($courrierInterne, $newServiceDestinataire, $oldServiceDestinataire);
                    $results['smsSentToService'] = $smsResult['success'];
                } else {
                    $this->logger?->warning('SMS service destinataire demandé mais numéro manquant lors de la mise à jour du courrier interne', [
                        'reponse_id' => $courrierInterne->getId(),
                        'service_id' => $newServiceDestinataire?->getId()
                    ]);
                }

                // 2. SMS au rÃ©dacteur (si numÃ©ro de tÃ©lÃ©phone utilisateur renseignÃ©)
                if ($newRedacteur && !empty($newRedacteur->getPhone())) {
                    $smsResult = $this->sendSmsToRedacteur($courrierInterne, $newRedacteur, $oldRedacteur);
                    $results['smsSentToRedacteur'] = $smsResult['success'];
                } else {
                    $this->logger?->warning('SMS rédacteur demandé mais numéro manquant lors de la mise à jour du courrier interne', [
                        'reponse_id' => $courrierInterne->getId(),
                        'redacteur_id' => $newRedacteur?->getId()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas Ã©chouer la mise Ã  jour de la rÃ©ponse
            $this->logger?->error('Erreur lors de l\'envoi des notifications pour mise à jour du courrier interne', [
                'reponse_id' => $courrierInterne->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * ðŸ“± Envoie un SMS de mise Ã  jour au service destinataire
     */
    private function sendSmsToService(CourrierInterne $courrierInterne, $newServiceDestinataire, $oldServiceDestinataire = null): array
    {
        $isServiceChanged = $oldServiceDestinataire && ($oldServiceDestinataire->getId() !== $newServiceDestinataire->getId());
        
        if ($isServiceChanged) {
            // Service transfÃ©rÃ©
            $message = "MINEPIA: Courrier interne n°{$courrierInterne->getId()} vous a ete transfere. Objet: {$courrierInterne->getObjet()}.\nMINEPIA: Internal mail no {$courrierInterne->getId()} has been transferred to you. Subject: {$courrierInterne->getObjet()}.";
        } else {
            // MÃªme service - mise Ã  jour
            $message = "MINEPIA: Mise a jour courrier interne n°{$courrierInterne->getId()} destinee a votre service. Objet: {$courrierInterne->getObjet()}.\nMINEPIA: Update to internal mail no {$courrierInterne->getId()} for your service. Subject: {$courrierInterne->getObjet()}.";
        }
        
        return $this->smsService->sendSms(
            $newServiceDestinataire->getTelephone(),
            $message,
            true // normalize
        );
    }

    /**
     * ðŸ“± Envoie un SMS de mise Ã  jour au rÃ©dacteur
     */
    private function sendSmsToRedacteur(CourrierInterne $courrierInterne, $newRedacteur, $oldRedacteur = null): array
    {
        $isRedacteurChanged = $oldRedacteur && ($oldRedacteur->getId() !== $newRedacteur->getId());
        
        if ($isRedacteurChanged) {
            // Nouveau rÃ©dacteur
            $message = "MINEPIA: Courrier interne n°{$courrierInterne->getId()} vous a ete transfere pour redaction. Objet: {$courrierInterne->getObjet()}.\nMINEPIA: Internal mail no {$courrierInterne->getId()} has been transferred to you for drafting. Subject: {$courrierInterne->getObjet()}.";
        } else {
            // MÃªme rÃ©dacteur - mise Ã  jour
            $message = "MINEPIA: Votre courrier interne n°{$courrierInterne->getId()} a ete mis a jour. Objet: {$courrierInterne->getObjet()}.\nMINEPIA: Your internal mail no {$courrierInterne->getId()} has been updated. Subject: {$courrierInterne->getObjet()}.";
        }
        
        return $this->smsService->sendSms(
            $newRedacteur->getPhone(),
            $message,
            true // normalize
        );
    }

    /**
     * Envoie un email de notification de mise Ã  jour au service destinataire
     */
    private function sendUpdateEmailToService(CourrierInterne $courrierInterne, $newServiceDestinataire, $oldServiceDestinataire = null): void
    {
        $isServiceChanged = $oldServiceDestinataire && ($oldServiceDestinataire->getId() !== $newServiceDestinataire->getId());
        
        if ($isServiceChanged) {
            $subject = "Nouveau courrier interne transféré - {$courrierInterne->getObjet()}";
        } else {
            $subject = "Mise à jour de courrier interne - {$courrierInterne->getObjet()}";
        }
        
        // Utiliser le même template que pour la création
        $htmlContent = $this->twig->render('emails/reponse/notification_service_destinataire.html.twig', [
            'reponse' => $courrierInterne,
            'serviceDestinataire' => $newServiceDestinataire,
            'courrier' => null,
        ]);

        $this->mailService->sendEmail($newServiceDestinataire->getEmailService(), $subject, $htmlContent);
    }

    /**
     * Envoie un email de notification de mise Ã  jour au rÃ©dacteur
     */
    private function sendUpdateEmailToRedacteur(CourrierInterne $courrierInterne, $newRedacteur, $oldRedacteur = null): void
    {
        $isRedacteurChanged = $oldRedacteur && ($oldRedacteur->getId() !== $newRedacteur->getId());
        
        if ($isRedacteurChanged) {
            $subject = "Courrier interne transféré pour rédaction - {$courrierInterne->getObjet()}";
        } else {
            $subject = "Mise à jour de votre courrier interne - {$courrierInterne->getObjet()}";
        }
        
        // Utiliser le même template que pour la création
        $htmlContent = $this->twig->render('emails/reponse/confirmation_redacteur.html.twig', [
            'reponse' => $courrierInterne,
            'redacteur' => $newRedacteur,
            'courrier' => null,
        ]);

        $this->mailService->sendEmail($newRedacteur->getEmail(), $subject, $htmlContent);
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
