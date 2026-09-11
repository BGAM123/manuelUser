<?php

namespace App\Controller\Core\TransmissionReponse;

use App\Entity\Cour\PieceJointe;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Repository\Cour\PieceJointeRepository;
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

#[OA\Tag(name: "TransmissionReponse")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FileService $fileService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private NotificationService $notificationService,
        private TransmissionReponseRepository $transmissionReponseRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private ServiceRepository $serviceRepository,
        private TypeReponseRepository $typeReponseRepository,
        private UserRepository $userRepository,
        private MailService $mailService,
        private SmsService $smsService,
        private UserActionLoggerService $actionLogger,
        private Environment $twig,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/transmission-reponse/{id}', name: 'app_core_transmission_reponse_patch', methods: ['POST'])]
    #[OA\Post(
        path: '/core/transmission-reponse/{id}',
        summary: 'Met à jour une transmission reponse existante',
        tags: ['TransmissionReponse'],
        description: "Met à jour les informations d'une transmission reponse et gère les pièces jointes avec notifications par email et SMS optionnelles.",
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
                        new OA\Property(property: 'idTransmission', type: 'string', example: '1,2,3', description: 'IDs des transmissions associées séparées par des virgules (optionnel)'),
                        new OA\Property(property: 'idCourrierInternes', type: 'string', example: '4,5,6', description: 'IDs des courriers internes liés séparés par des virgules (optionnel)'),
                        new OA\Property(property: 'objet', type: 'string', example: 'RE: Demande de budget'),
                        new OA\Property(property: 'commentairePublic', type: 'string', example: 'Voici notre réponse'),
                        new OA\Property(property: 'commentaireInterne', type: 'string', example: 'Note interne'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent', description: 'Classe du courrier (ex: Urgent, Normal, Confidentiel)'),
                        new OA\Property(property: 'typeTransmission', type: 'string', example: 'Electronique', description: 'Type de transmission (ex: Electronique, Physique, Courrier)'),
                        new OA\Property(property: 'priorite', type: 'string', example: 'Haute', description: 'Priorite de la reponse (ex: Basse, Normal, Haute)'),
                        new OA\Property(property: 'delaiTraitement', type: 'integer', example: 5, description: 'Delai de traitement en jours (optionnel)'),
                        new OA\Property(property: 'structuresCopie', type: 'string', example: '1,2,3', description: 'IDs des services en copie separes par des virgules (optionnel)'),
                        new OA\Property(property: 'idServiceDestinataire', type: 'integer', example: 3),
                        new OA\Property(property: 'idRedacteur', type: 'integer', example: 2),
                        new OA\Property(property: 'dateReponse', type: 'string', format: 'date', example: '2025-02-14'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2, description: 'Nombre de pièces jointes (simple champ entré par l\'utilisateur, aucune vérification)'),
                        new OA\Property(property: 'sendMail', type: 'boolean', example: true, description: 'Envoyer un email de mise à jour au service destinataire'),
                        new OA\Property(property: 'sendSms', type: 'boolean', example: false, description: 'Envoyer un SMS de mise à jour au service destinataire'),
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
                description: 'Transmission reponse mise à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Transmission reponse mise à jour avec succès'),
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
                                new OA\Property(property: 'idCourrierInternes', type: 'array', items: new OA\Items(type: 'integer'), example: [4, 5, 6]),
                                new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2),
                                new OA\Property(property: 'priorite', type: 'string', example: 'Haute'),
                                new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                                new OA\Property(property: 'delaiTraitement', type: 'integer', example: 5),
                                new OA\Property(property: 'structuresCopie', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                                new OA\Property(property: 'piecesJointesAdded', type: 'integer', example: 2),
                                new OA\Property(property: 'piecesJointesDeleted', type: 'integer', example: 1),
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
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Transmission reponse non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PatchTransmissionReponse');

        $reponse = $this->transmissionReponseRepository->find($id);

        if (!$reponse) {
            return $this->json(['code' => 404, 'message' => 'Transmission reponse non trouvée.'], 404);
        }

        $oldData = [
            'id' => $reponse->getId(),
            'objet' => $reponse->getObjet(),
            'commentairePublic' => $reponse->getCommentairePublic(),
            'commentaireInterne' => $reponse->getCommentaireInterne(),
            'classeCourrier' => $reponse->getClasseCourrier(),
            'typeTransmission' => $reponse->getTypeTransmission(),
            'priorite' => $reponse->getPriorite(),
            'statut' => $reponse->getStatut(),
            'typesCourrierIds' => $reponse->getTypesCourrierIds(),
            'idCourrierInternes' => $reponse->getIdCourrierInternes(),
            'delaiTraitement' => $reponse->getDelaiTraitement(),
            'structuresCopie' => $reponse->getStructuresCopie(),
            'typeReponse' => $reponse->getTypeReponse()?->getNom(),
            'serviceDestinataire' => $reponse->getIdServiceDestinataire()?->getNom(),
            'redacteur' => $reponse->getIdRedacteur()?->getUserIdentifier(),
            'dateReponse' => $reponse->getDateReponse()?->format('Y-m-d'),
        ];

        $data = $request->request->all();

        if (empty($data) && !$request->files->count()) {
            return $this->json(['code' => 400, 'message' => 'Aucune donnÃ©e fournie.'], 400);
        }

        $sendMail = filter_var($data['sendMail'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendSms = filter_var($data['sendSms'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (isset($data['id_courrier_internes']) && !isset($data['idCourrierInternes'])) {
            $data['idCourrierInternes'] = $data['id_courrier_internes'];
            unset($data['id_courrier_internes']);
        }
        if (isset($data['id_reponses']) && !isset($data['idCourrierInternes'])) {
            $data['idCourrierInternes'] = $data['id_reponses'];
            unset($data['id_reponses']);
        }
        if (isset($data['idReponses']) && !isset($data['idCourrierInternes'])) {
            $data['idCourrierInternes'] = $data['idReponses'];
            unset($data['idReponses']);
        }
        if (isset($data['structures_copie']) && !isset($data['structuresCopie'])) {
            $data['structuresCopie'] = $data['structures_copie'];
            unset($data['structures_copie']);
        }

        $oldServiceDestinataire = $reponse->getIdServiceDestinataire();
        $oldRedacteur = $reponse->getIdRedacteur();

        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'id', 'sendMail', 'sendSms', 'statut']);

        try {
            $newServiceDestinataire = $oldServiceDestinataire;
            $newRedacteur = $oldRedacteur;

            if (isset($data['idTypeReponse'])) {
                if (!empty($data['idTypeReponse'])) {
                    $typeReponse = $this->typeReponseRepository->find($data['idTypeReponse']);
                    if (!$typeReponse) {
                        return $this->json(['code' => 404, 'message' => 'Type de réponse introuvable.'], 404);
                    }
                    $reponse->setTypeReponse($typeReponse);
                } else {
                    $reponse->setTypeReponse(null);
                }
                unset($data['idTypeReponse']);
            }

            if (isset($data['idServiceDestinataire'])) {
                if (!empty($data['idServiceDestinataire'])) {
                    $serviceDestinataire = $this->serviceRepository->find($data['idServiceDestinataire']);
                    if (!$serviceDestinataire) {
                        return $this->json(['code' => 404, 'message' => 'Service destinataire introuvable.'], 404);
                    }
                    $currentUser = $this->getUser();
                    $currentUserService = $currentUser instanceof \App\Entity\Core\User ? $currentUser->getIdService() : null;
                    if ($currentUserService && $currentUserService->getId() === $serviceDestinataire->getId()) {
                        return $this->json([
                            'code' => 400,
                            'message' => 'Vous ne pouvez pas creer un courrier interne à votre propre service.'
                        ], 400);
                    }
                    $reponse->setIdServiceDestinataire($serviceDestinataire);
                    $newServiceDestinataire = $serviceDestinataire;
                } else {
                    $reponse->setIdServiceDestinataire(null);
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
                    $reponse->setIdRedacteur($redacteur);
                    $newRedacteur = $redacteur;
                } else {
                    $reponse->setIdRedacteur(null);
                    $newRedacteur = null;
                }
                unset($data['idRedacteur']);
            }

            if (isset($data['typesCourrierIds'])) {
                if (!empty($data['typesCourrierIds']) && is_string($data['typesCourrierIds'])) {
                    $ids = array_map('intval', array_filter(explode(',', $data['typesCourrierIds'])));
                    $data['typesCourrierIds'] = !empty($ids) ? $ids : null;
                } else {
                    $data['typesCourrierIds'] = null;
                }
            }

            $idTransmission = null;
            if (isset($data['idTransmission'])) {
                if (!empty($data['idTransmission']) && is_string($data['idTransmission'])) {
                    $ids = array_map('intval', array_filter(explode(',', $data['idTransmission'])));
                    $idTransmission = !empty($ids) ? $ids : null;
                }
                $data['idTransmission'] = $idTransmission;
            }

            $idCourrierInternes = null;
            if (isset($data['idCourrierInternes'])) {
                if (!empty($data['idCourrierInternes']) && is_string($data['idCourrierInternes'])) {
                    $ids = array_map('intval', array_filter(explode(',', $data['idCourrierInternes'])));
                    $idCourrierInternes = !empty($ids) ? $ids : null;
                } elseif (is_array($data['idCourrierInternes'])) {
                    $ids = array_map('intval', array_filter($data['idCourrierInternes']));
                    $idCourrierInternes = !empty($ids) ? $ids : null;
                }
                $data['idCourrierInternes'] = $idCourrierInternes;
            }

            if (isset($data['dateReponse']) && !empty($data['dateReponse'])) {
                $data['dateReponse'] = new \DateTime($data['dateReponse']);
            }

            if (isset($data['nombrePieceJointe'])) {
                $data['nombrePieceJointe'] = (int)$data['nombrePieceJointe'];
            }

            if (isset($data['delaiTraitement'])) {
                $data['delaiTraitement'] = ($data['delaiTraitement'] === '' || $data['delaiTraitement'] === null)
                    ? null
                    : (int) $data['delaiTraitement'];
            }

            if (isset($data['structuresCopie'])) {
                if (!empty($data['structuresCopie'])) {
                    if (is_string($data['structuresCopie'])) {
                        $ids = array_map('intval', array_filter(explode(',', $data['structuresCopie'])));
                        $data['structuresCopie'] = !empty($ids) ? $ids : null;
                    } elseif (is_array($data['structuresCopie'])) {
                        $ids = array_map('intval', array_filter($data['structuresCopie']));
                        $data['structuresCopie'] = !empty($ids) ? $ids : null;
                    } else {
                        $data['structuresCopie'] = null;
                    }
                } else {
                    $data['structuresCopie'] = null;
                }
            }

            $keepPiecesJointes = $data['keepPiecesJointes'] ?? null;
            unset($data['keepPiecesJointes']);

            $this->crudService->patchEntity($reponse, $data);

            $deletedCount = 0;
            if ($keepPiecesJointes !== null) {
                $idsToKeep = !empty($keepPiecesJointes) ? array_map('intval', explode(',', $keepPiecesJointes)) : [];

                $allPieces = $this->pieceJointeRepository->findBy([
                    'idParent' => $reponse->getId(),
                    'typeParent' => 'TransmissionReponse',
                    'isDelete' => false
                ]);

                foreach ($allPieces as $piece) {
                    if (!in_array($piece->getId(), $idsToKeep)) {
                        $piece->setDelete(true);
                        $deletedCount++;
                    }
                }
            }

            $uploadedCount = 0;
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
                        $piece->setIdParent($reponse->getId());
                        $piece->setTypeParent('TransmissionReponse');

                        $this->crudService->postEntity($piece, []);
                        $uploadedCount++;
                    }
                }
            }

            //  NOTIFICATIONS EN BD POUR LE SERVICE DESTINATAIRE
            $dbNotificationCount = 0;
            try {
                if ($newServiceDestinataire) {
                    $dbNotificationCount = $this->notificationService->createNotificationForService(
                        service: $newServiceDestinataire,
                        titre: 'courrier interne pour votre service',
                        message: 'vous avez recu un nouveau courrier interne - ' . ($reponse->getObjet() ?? ''),
                        type: 'reponse',
                        data: [
                            'transmission_reponse_id' => $reponse->getId(),
                        ]
                    );
                } else {
                    $this->logger?->warning('Notification BD demandee mais service destinataire manquant pour transmission reponse (mise a jour)', [
                        'transmission_reponse_id' => $reponse->getId(),
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger?->error('Erreur lors de la creation des notifications BD pour transmission reponse (mise a jour)', [
                    'transmission_reponse_id' => $reponse->getId(),
                    'exception' => $e->getMessage()
                ]);
            }

            $notificationResults = $this->handleAllNotifications(
                $reponse,
                $newServiceDestinataire,
                $newRedacteur,
                $sendMail,
                $sendSms
            );

            $this->actionLogger->logUpdate(
                'TransmissionReponse',
                $reponse->getId(),
                'Mise à jour d\'une transmission reponse',
                [
                    'before' => $oldData,
                    'after' => [
                        'id' => $reponse->getId(),
                        'objet' => $reponse->getObjet(),
                        'commentairePublic' => $reponse->getCommentairePublic(),
                        'commentaireInterne' => $reponse->getCommentaireInterne(),
                        'classeCourrier' => $reponse->getClasseCourrier(),
                        'typeTransmission' => $reponse->getTypeTransmission(),
                        'priorite' => $reponse->getPriorite(),
                        'statut' => $reponse->getStatut(),
                        'typesCourrierIds' => $reponse->getTypesCourrierIds(),
                        'idCourrierInternes' => $reponse->getIdCourrierInternes(),
                        'delaiTraitement' => $reponse->getDelaiTraitement(),
                        'structuresCopie' => $reponse->getStructuresCopie(),
                        'typeReponse' => $reponse->getTypeReponse()?->getNom(),
                        'serviceDestinataire' => $reponse->getIdServiceDestinataire()?->getNom(),
                        'redacteur' => $reponse->getIdRedacteur()?->getUserIdentifier(),
                        'dateReponse' => $reponse->getDateReponse()?->format('Y-m-d'),
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
                'message' => 'Transmission reponse mise Ã  jour avec succÃ¨s',
                'data' => [
                    'id' => $reponse->getId(),
                    'objet' => $reponse->getObjet(),
                    'classeCourrier' => $reponse->getClasseCourrier(),
                    'typeTransmission' => $reponse->getTypeTransmission(),
                    'priorite' => $reponse->getPriorite(),
                    'statut' => $reponse->getStatut(),
                    'typesCourrierIds' => $reponse->getTypesCourrierIds(),
                    'idTransmission' => $reponse->getIdTransmission(),
                    'idCourrierInternes' => $reponse->getIdCourrierInternes(),
                    'nombrePieceJointe' => $reponse->getNombrePieceJointe(),
                    'delaiTraitement' => $reponse->getDelaiTraitement(),
                    'structuresCopie' => $reponse->getStructuresCopie(),
                    'piecesJointesAdded' => $uploadedCount,
                    'piecesJointesDeleted' => $deletedCount,
                    'notifications' => $notificationResults,
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    private function handleAllNotifications(
        $reponse,
        $newServiceDestinataire,
        $newRedacteur,
        bool $sendMail,
        bool $sendSms
    ): array {
        $results = [
            'emailSentToServiceUsers' => false,
            'smsSentToServiceUsers' => false,
        ];

        try {
            if ($sendMail) {
                if ($newServiceDestinataire) {
                    $results['emailSentToServiceUsers'] = $this->sendEmailToServiceUsers($reponse, $newServiceDestinataire);
                } else {
                    $this->logger?->warning('Email service destinataire demande mais service manquant pour transmission reponse', [
                        'transmission_reponse_id' => $reponse->getId(),
                    ]);
                }
            }

            if ($sendSms) {
                if ($newServiceDestinataire) {
                    $results['smsSentToServiceUsers'] = $this->sendSmsToServiceUsers($reponse, $newServiceDestinataire);
                } else {
                    $this->logger?->warning('SMS service destinataire demande mais service manquant pour transmission reponse', [
                        'transmission_reponse_id' => $reponse->getId(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de l\'envoi des notifications pour transmission reponse', [
                'transmission_reponse_id' => $reponse->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        return $results;
    }

    private function sendSmsToServiceUsers($reponse, $serviceDestinataire): bool
    {
        $message = "MINEPIA: Mise a jour transmission reponse n{$reponse->getId()}. Objet: {$reponse->getObjet()}.\nMINEPIA: Update to reply transmission no {$reponse->getId()}. Subject: {$reponse->getObjet()}.";

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

    private function sendEmailToServiceUsers($reponse, $serviceDestinataire): bool
    {
        $subject = "Mise a jour transmission reponse - {$reponse->getObjet()}";

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

    private function parseIntitules(string $input): array
    {
        if (empty(trim($input))) {
            return [];
        }

        $decoded = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_map('trim', $decoded);
        }

        if (strpos($input, '|||') !== false) {
            return array_map('trim', explode('|||', $input));
        }

        if (strpos($input, ';') !== false) {
            return array_map('trim', explode(';', $input));
        }

        return array_map('trim', preg_split('/\r\n|\r|\n/', $input));
    }
}
