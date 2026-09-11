<?php

namespace App\Controller\Core\TransmissionReponse;

use App\Entity\Cour\TransmissionReponse;
use App\Entity\Cour\PieceJointe;
use App\Repository\Cour\CourrierInterneRepository;
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

#[OA\Tag(name: "TransmissionReponse")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FileService $fileService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private NotificationService $notificationService,
        private TransmissionRepository $transmissionRepository,
        private CourrierInterneRepository $courrierInterneRepository,
        private ServiceRepository $serviceRepository,
        private TypeReponseRepository $typeReponseRepository,
        private UserRepository $userRepository,
        private MailService $mailService,
        private SmsService $smsService,
        private UserActionLoggerService $actionLogger,
        private Environment $twig,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/transmission-reponse', name: 'app_core_transmission_reponse_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/transmission-reponse',
        summary: 'Creer une transmission reponse avec pieces jointes',
        tags: ['TransmissionReponse'],
        description: "Cree une transmission reponse et upload les fichiers joints en une seule requete avec notifications par email et SMS optionnelles.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'idTypeReponse', type: 'integer', example: 1, description: 'ID du type de reponse'),
                        new OA\Property(
                            property: 'typesCourrierIds',
                            type: 'string',
                            example: '1,2,3',
                            description: 'IDs des types de courrier separes par des virgules'
                        ),
                        new OA\Property(
                            property: 'idTransmission',
                            type: 'string',
                            example: '1,2,3',
                            description: 'IDs des transmissions associees separees par des virgules (optionnel)'
                        ),
                        new OA\Property(
                            property: 'idCourrierInternes',
                            type: 'string',
                            example: '4,5,6',
                            description: 'IDs des courriers internes lies separes par des virgules (optionnel)'
                        ),
                        new OA\Property(property: 'objet', type: 'string', example: 'RE: Demande de budget'),
                        new OA\Property(property: 'commentairePublic', type: 'string', example: 'Voici notre reponse'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent', description: 'Classe du courrier (ex: Urgent, Normal, Confidentiel)'),
                        new OA\Property(property: 'typeTransmission', type: 'string', example: 'Electronique', description: 'Type de transmission (ex: Electronique, Physique, Courrier)'),
                        new OA\Property(property: 'priorite', type: 'string', example: 'Haute', description: 'Priorite de la reponse (ex: Basse, Normal, Haute)'),
                        new OA\Property(property: 'delaiTraitement', type: 'integer', example: 5, description: 'Delai de traitement en jours (optionnel)'),
                        new OA\Property(property: 'structuresCopie', type: 'string', example: '1,2,3', description: 'IDs des services en copie separes par des virgules (optionnel)'),
                        new OA\Property(property: 'idServiceDestinataire', type: 'integer', example: 3),
                        new OA\Property(property: 'idRedacteur', type: 'integer', example: 2, description: 'Optionnel, sinon utilisateur connecte'),
                        new OA\Property(property: 'dateReponse', type: 'string', format: 'date', example: '2025-02-14'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2, description: 'Nombre de pieces jointes (simple champ entre par l\'utilisateur, aucune verification)'),
                        new OA\Property(property: 'sendMail', type: 'boolean', example: true, description: 'Envoyer un email aux utilisateurs du service destinataire'),
                        new OA\Property(property: 'sendSms', type: 'boolean', example: false, description: 'Envoyer un SMS aux utilisateurs du service destinataire'),
                        new OA\Property(
                            property: 'piecesJointes[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'Fichiers a joindre (optionnel)'
                        ),
                        new OA\Property(
                            property: 'intitulesPiecesJointes',
                            type: 'string',
                            example: '["Rapport financier", "Justificatif", "Annexe"]',
                            description: 'Intitules des pieces jointes. Formats acceptes: JSON array, separation par retour a la ligne, par "|||" ou par ";"'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Transmission reponse creee avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'Transmission reponse creee avec succes'),
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
                                new OA\Property(property: 'piecesJointesCount', type: 'integer', example: 2),
                                new OA\Property(
                                    property: 'notifications',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'emailSentToServiceUsers', type: 'boolean', example: true),
                                        new OA\Property(property: 'smsSentToServiceUsers', type: 'boolean', example: true),
                                    ]
                                ),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requete invalide.'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PostTransmissionReponse');

        $data = $request->request->all();

        // Parametres d'envoi (non sauvegardes)
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

        // Ne pas accepter courriers dans cette API
        unset($data['courrierIds']);

        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'id', 'sendMail', 'sendSms', 'statut']);

        try {
            // $transmissionReponse = new TransmissionReponse();

            // $courriersInternes = [];

            // if (!empty($idCourrierInternes)) {

            //     foreach ($idCourrierInternes as $courrierId) {

            //         $courrier = $this->courrierInterneRepository->find($courrierId);

            //         if (!$courrier) {
            //             return $this->json([
            //                 'code' => 404,
            //                 'message' => "Courrier interne {$courrierId} introuvable."
            //             ], 404);
            //         }

            //         $courriersInternes[] = $courrier;
            //     }
            // }

            $serviceDestinataire = null;
            $redacteur = null;

            $typeReponse = null;

            if (!empty($data['idTypeReponse'])) {

                $typeReponse = $this->typeReponseRepository
                    ->find($data['idTypeReponse']);

                if (!$typeReponse) {

                    return $this->json([
                        'code' => 404,
                        'message' => 'Type de reponse introuvable.'
                    ], 404);
                }
            }

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
                // $transmissionReponse->setIdServiceDestinataire($serviceDestinataire);
            }

            if (!empty($data['idRedacteur'])) {
                $redacteur = $this->userRepository->find($data['idRedacteur']);
                if (!$redacteur) {
                    return $this->json(['code' => 404, 'message' => 'Redacteur introuvable.'], 404);
                }
                // $transmissionReponse->setIdRedacteur($redacteur);
            } else {
                $redacteur = $this->getUser();
                // $transmissionReponse->setIdRedacteur($redacteur);
            }

            // typesCourrierIds (string "1,2,3")
            $typesCourrierIds = null;
            if (!empty($data['typesCourrierIds']) && is_string($data['typesCourrierIds'])) {
                $ids = array_map('intval', array_filter(explode(',', $data['typesCourrierIds'])));
                $typesCourrierIds = !empty($ids) ? $ids : null;
            }

            // idTransmission (string "1,2,3")
            $idTransmission = null;
            if (!empty($data['idTransmission']) && is_string($data['idTransmission'])) {
                $ids = array_map('intval', array_filter(explode(',', $data['idTransmission'])));
                $idTransmission = !empty($ids) ? $ids : null;
            }

            // idCourrierInternes (string "1,2,3" ou array)
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

            $courriersInternes = [];

            if (!empty($idCourrierInternes)) {

                foreach ($idCourrierInternes as $courrierId) {

                    $courrier = $this->courrierInterneRepository->find($courrierId);

                    if (!$courrier) {

                        return $this->json([
                            'code' => 404,
                            'message' => "Courrier interne {$courrierId} introuvable."
                        ], 404);
                    }

                    $courriersInternes[] = $courrier;
                }
            }

            // === Lecture des données spécifiques par courrier envoyées par le frontend ===
            $courriersData = [];
            if (!empty($data['courriersData'])) {
                $decoded = json_decode($data['courriersData'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        if (isset($item['idCourrier'])) {
                            $courriersData[(int)$item['idCourrier']] = $item;
                        }
                    }
                }
            }

            $objet = $data['objet'] ?? null;
            $classeCourrier = $data['classeCourrier'] ?? null;
            $typeTransmission = $data['typeTransmission'] ?? null;

            $hasObjet = !empty(trim((string) $objet));
            $hasClasse = !empty(trim((string) $classeCourrier));
            $hasType = !empty(trim((string) $typeTransmission));
            $hasTypesCourrier = is_array($typesCourrierIds) && !empty($typesCourrierIds);

            if ((!$hasObjet || !$hasClasse || !$hasType || !$hasTypesCourrier) && !empty($idCourrierInternes)) {
                $courrierInterneId = (int) (reset($idCourrierInternes) ?: 0);
                if ($courrierInterneId > 0) {
                    $courrierInterne = $this->courrierInterneRepository->find($courrierInterneId);
                    if ($courrierInterne) {
                        if (!$hasObjet) {
                            $objet = $courrierInterne->getObjet();
                        }
                        if (!$hasClasse) {
                            $classeCourrier = $courrierInterne->getClasseCourrier();
                        }
                        if (!$hasType) {
                            $typeTransmission = $courrierInterne->getTypeTransmission();
                        }
                        if (!$hasTypesCourrier) {
                            $typesCourrierIds = $courrierInterne->getTypesCourrierIds();
                        }
                    } else {
                        $this->logger?->warning('Courrier interne introuvable pour prefilling transmission reponse', [
                            'courrier_interne_id' => $courrierInterneId,
                        ]);
                    }
                }
            }

            // structuresCopie (string "1,2,3" ou array)
            $structuresCopie = null;
            if (!empty($data['structuresCopie'])) {
                if (is_string($data['structuresCopie'])) {
                    $ids = array_map('intval', array_filter(explode(',', $data['structuresCopie'])));
                    $structuresCopie = !empty($ids) ? $ids : null;
                } elseif (is_array($data['structuresCopie'])) {
                    $ids = array_map('intval', array_filter($data['structuresCopie']));
                    $structuresCopie = !empty($ids) ? $ids : null;
                }
            }

            // delaiTraitement (integer)
            $delaiTraitement = null;
            if (isset($data['delaiTraitement']) && $data['delaiTraitement'] !== '') {
                $delaiTraitement = (int) $data['delaiTraitement'];
            }

            $dateReponse = new \DateTime();
            if (!empty($data['dateReponse']) && is_string($data['dateReponse'])) {
                $dateReponse = new \DateTime($data['dateReponse']);
            }

            // $transmissionReponse = $this->crudService->postEntity($transmissionReponse, [
            //     'objet' => $objet,
            //     'commentairePublic' => $data['commentairePublic'] ?? null,
            //     'classeCourrier' => $classeCourrier,
            //     'typeTransmission' => $typeTransmission,
            //     'priorite' => $data['priorite'] ?? null,
            //     'dateReponse' => $dateReponse,
            //     'typesCourrierIds' => $typesCourrierIds,
            //     'idTransmission' => $idTransmission,
            //     'idCourrierInternes' => $idCourrierInternes,
            //     'nombrePieceJointe' => isset($data['nombrePieceJointe']) ? (int)$data['nombrePieceJointe'] : 0,
            //     'delaiTraitement' => $delaiTraitement,
            //     'structuresCopie' => $structuresCopie,
            // ]);


            $transmissionReponses = [];

            // === NOUVEAUTÉ : Lecture des données spécifiques par courrier envoyées par le frontend ===
            $courriersData = [];
            if (!empty($data['courriersData'])) {
                $decoded = json_decode($data['courriersData'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        if (isset($item['idCourrier'])) {
                            $courriersData[(int)$item['idCourrier']] = $item;
                        }
                    }
                }
            }

            foreach ($courriersInternes as $courrierInterne) {

                $cid = $courrierInterne->getId();
                $specific = $courriersData[$cid] ?? [];   // Données envoyées par le frontend pour CE courrier

                $transmissionReponse = new TransmissionReponse();

                if ($typeReponse) {
                    $transmissionReponse->setTypeReponse($typeReponse);
                }
                if ($serviceDestinataire) {
                    $transmissionReponse->setIdServiceDestinataire($serviceDestinataire);
                }
                if ($redacteur) {
                    $transmissionReponse->setIdRedacteur($redacteur);
                }

                // === PRIORITÉ : Données du frontend > Données du courrier ===
                $objetFinal       = $specific['objet'] ?? $courrierInterne->getObjet();
                $classeFinal      = $specific['classeCourrier'] ?? $courrierInterne->getClasseCourrier();
                $typeFinal        = $specific['typeTransmission'] ?? $courrierInterne->getTypeTransmission() ?? 'Pour traitement';
                $prioriteFinal    = $specific['priorite'] ?? $data['priorite'] ?? $courrierInterne->getPriorite() ?? 'normal';

                // === CORRECTION POUR PROVENANCE ET TYPE DE COURRIER ===
                // $provenanceFinal   = $specific['provenance'] ?? $courrierInterne->getProvenance() ?? null;
                $typesCourrierFinal = $specific['typesCourrierIds'] 
                                ?? $courrierInterne->getTypesCourrierIds() 
                                ?? $typesCourrierIds;

                $transmissionReponse = $this->crudService->postEntity(
                    $transmissionReponse,
                    [
                        'objet'            => $objetFinal,
                        'commentairePublic'=> $data['commentairePublic'] ?? null,
                        'classeCourrier'   => $classeFinal,
                        'typeTransmission' => $typeFinal,
                        'priorite'         => $prioriteFinal,
                        // 'provenance'       => $provenanceFinal,           // ← Corrigé
                        'typesCourrierIds' => $typesCourrierFinal,
                        'dateReponse'      => $dateReponse,
                        // 'typesCourrierIds' => $typesCourrierIds,
                        'idTransmission'   => $idTransmission,
                        'idCourrierInternes' => [$cid],
                        'nombrePieceJointe'=> isset($data['nombrePieceJointe']) ? (int)$data['nombrePieceJointe'] : 0,
                        'delaiTraitement'  => $delaiTraitement,
                        'structuresCopie'  => $structuresCopie,
                    ]
                );

                $transmissionReponses[] = $transmissionReponse;
            }
            

            // Mise a jour statut des transmissions (si fournis)
            if (!empty($idTransmission) && is_array($idTransmission)) {
                foreach ($idTransmission as $transmissionId) {
                    $transmission = $this->transmissionRepository->find($transmissionId);
                    if ($transmission) {
                        $transmission->setStatut('Traite');
                        if ($redacteur) {
                            $traitePar = $transmission->getTraitePar() ?? [];
                            $traitePar[] = [
                                'action' => 'transmission_reponse',
                                'repondu_par_id' => $redacteur->getId(),
                                'date_traitement' => (new \DateTime())->format('Y-m-d H:i:s'),
                            ];
                            $transmission->setTraitePar($traitePar);
                        }
                        $this->crudService->postEntity($transmission, []);
                    }
                }
            }

            // Ajout des pieces jointes
            $uploadedCount = 0;
            $intitulesInput = $request->request->get('intitulesPiecesJointes', '');
            $intitules = $this->parseIntitules($intitulesInput);

            if (!empty($request->files->get('piecesJointes'))) {
                $files = $request->files->get('piecesJointes');

                foreach ($transmissionReponses as $transmissionReponse) {
                    foreach ($files as $index => $file) {
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
                            $piece->setIdParent($transmissionReponse->getId());
                            $piece->setTypeParent('TransmissionReponse');

                            $this->crudService->postEntity($piece, []);
                            $uploadedCount++;
                        }
                    }
                }
            }

            //  NOTIFICATIONS EN BD POUR LE SERVICE DESTINATAIRE
            $dbNotificationCount = 0;
            $dbNotificationCopieCount = 0;
            $servicesCopieNotifies = [];
            // try {
            //     if ($serviceDestinataire) {
            //         $dbNotificationCount += $this->notificationService->createNotificationForService(
            //             service: $serviceDestinataire,
            //             titre: 'courrier interne pour votre service',
            //             message: 'vous avez recu un nouveau courrier interne - ' . ($transmissionReponse->getObjet() ?? ''),
            //             type: 'courrier_interne',
            //             data: [
            //                 'transmission_reponse_id' => $transmissionReponse->getId(),
            //             ]
            //         );
            //     } else {
            //         $this->logger?->warning('Notification BD demandee mais service destinataire manquant pour transmission reponse', [
            //             'transmission_reponse_id' => $transmissionReponse->getId(),
            //         ]);
            //     }

            //     if (!empty($structuresCopie) && is_array($structuresCopie)) {
            //         foreach ($structuresCopie as $serviceId) {
            //             if (!is_numeric($serviceId)) {
            //                 continue;
            //             }
            //             $serviceId = (int) $serviceId;
            //             if ($serviceDestinataire && $serviceDestinataire->getId() === $serviceId) {
            //                 continue;
            //             }

            //             try {
            //                 $serviceCopie = $this->serviceRepository->find($serviceId);
            //                 if ($serviceCopie) {
            //                     $count = $this->notificationService->createNotificationForService(
            //                         service: $serviceCopie,
            //                         titre: 'courrier interne en copie',
            //                         message: 'vous avez recu un courrier interne en copie - ' . ($transmissionReponse->getObjet() ?? ''),
            //                         type: 'courrier_interne',
            //                         data: [
            //                             'transmission_reponse_id' => $transmissionReponse->getId(),
            //                             'is_copie' => true,
            //                             'service_copie_id' => $serviceCopie->getId(),
            //                         ]
            //                     );
            //                     $dbNotificationCount += $count;
            //                     $dbNotificationCopieCount += $count;
            //                     $servicesCopieNotifies[] = $serviceCopie->getId();
            //                 } else {
            //                     $this->logger?->warning('Service en copie introuvable pour notification transmission reponse', [
            //                         'service_id' => $serviceId,
            //                         'transmission_reponse_id' => $transmissionReponse->getId(),
            //                     ]);
            //                 }
            //             } catch (\Exception $serviceException) {
            //                 $this->logger?->error('Erreur lors de la creation des notifications BD pour service en copie', [
            //                     'transmission_reponse_id' => $transmissionReponse->getId(),
            //                     'service_id' => $serviceId,
            //                     'exception' => $serviceException->getMessage()
            //                 ]);
            //             }
            //         }
            //     }
            // } catch (\Exception $e) {
            //     $this->logger?->error('Erreur lors de la creation des notifications BD pour transmission reponse', [
            //         'transmission_reponse_id' => $transmissionReponse->getId(),
            //         'exception' => $e->getMessage()
            //     ]);
            // }

            // $notificationResults = $this->handleAllNotifications(
            //     $transmissionReponse,
            //     $serviceDestinataire,
            //     $redacteur,
            //     [],
            //     $sendMail,
            //     $sendSms
            // );

            $notificationResults = [];

            foreach ($transmissionReponses as $transmissionReponse) {

                // Notification dans la base de données pour chaque courrier
                if ($serviceDestinataire) {
                    $dbNotificationCount += $this->notificationService->createNotificationForService(
                        service: $serviceDestinataire,
                        titre: 'courrier interne pour votre service',
                        message: 'vous avez recu un nouveau courrier interne - ' . ($transmissionReponse->getObjet() ?? ''),
                        type: 'courrier_interne',
                        data: [
                            'transmission_reponse_id' => $transmissionReponse->getId(),
                        ]
                    );
                }

                // Notifications Email + SMS pour chaque transmission
                $notificationResults[] = $this->handleAllNotifications(
                    $transmissionReponse,
                    $serviceDestinataire,
                    $redacteur,
                    [],
                    $sendMail,
                    $sendSms
                );
            }

            $this->actionLogger->logCreate(
                'TransmissionReponse',
                $transmissionReponses[0]->getId() ?? null,
                'Creation de ' . count($transmissionReponses) . ' transmission(s) reponse',
                [
                    'transmission_reponse' => [
                        'nombreTransmissions' => count($transmissionReponses),
                        'ids' => array_map(fn($r) => $r->getId(), $transmissionReponses),
                        'objet' => $transmissionReponse->getObjet(),
                        'commentairePublic' => $transmissionReponse->getCommentairePublic(),
                        'classeCourrier' => $transmissionReponse->getClasseCourrier(),
                        'typeTransmission' => $transmissionReponse->getTypeTransmission(),
                        'priorite' => $transmissionReponse->getPriorite(),
                        'statut' => $transmissionReponse->getStatut(),
                        'delaiTraitement' => $transmissionReponse->getDelaiTraitement(),
                        'structuresCopie' => $transmissionReponse->getStructuresCopie(),
                        'typesCourrierIds' => $transmissionReponse->getTypesCourrierIds(),
                        'idCourrierInternes' => $transmissionReponse->getIdCourrierInternes(),
                        'typeReponse' => $transmissionReponse->getTypeReponse()?->getNom(),
                        'serviceDestinataire' => $transmissionReponse->getIdServiceDestinataire()?->getNom(),
                        'redacteur' => $redacteur->getUserIdentifier(),
                        'dateReponse' => $transmissionReponse->getDateReponse()?->format('Y-m-d'),
                    ],
                    'piecesJointesCount' => $uploadedCount,
                    'notifications' => $notificationResults,
                    'dbNotificationsCount' => $dbNotificationCount,
                    'dbNotificationsCopieCount' => $dbNotificationCopieCount,
                    'servicesCopieNotifies' => $servicesCopieNotifies,
                ]
            );

            return $this->json([
                'code' => 201,
                'message' => 'transmission reponse creee avec succes',
                'data' => [
                'transmissionReponses' => array_map(
                    fn ($r) => [
                        'id' => $r->getId(),
                        'objet' => $r->getObjet(),
                        'classeCourrier' => $r->getClasseCourrier(),
                        'typeTransmission' => $r->getTypeTransmission(),
                        'priorite' => $r->getPriorite(),
                        'statut' => $r->getStatut(),
                        'typesCourrierIds' => $r->getTypesCourrierIds(),
                        'idTransmission' => $r->getIdTransmission(),
                        'idCourrierInternes' => $r->getIdCourrierInternes(),
                        'nombrePieceJointe' => $r->getNombrePieceJointe(),
                        'delaiTraitement' => $r->getDelaiTraitement(),
                        'structuresCopie' => $r->getStructuresCopie(),
                    ],
                    $transmissionReponses
                ),

                'piecesJointesCount' => $uploadedCount,
                'notifications' => $notificationResults,
            ]
            ], 201);

        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    private function handleAllNotifications(
        TransmissionReponse $reponse,
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
                    $this->logger?->warning('Email service destinataire demande mais service manquant pour transmission reponse', [
                        'transmission_reponse_id' => $reponse->getId(),
                    ]);
                }
            }

            if ($sendSms) {
                if ($serviceDestinataire) {
                    $results['smsSentToServiceUsers'] = $this->sendSmsToServiceUsers($reponse, $serviceDestinataire, $courriers);
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

    private function sendSmsToServiceUsers(TransmissionReponse $reponse, $serviceDestinataire, $courrier = null): bool
    {
        $courrierItem = is_array($courrier) ? ($courrier[0] ?? null) : $courrier;

        if ($courrierItem) {
            $message = "MINEPIA: courrier interne : merci de prendre connaissance du courrier n{$courrierItem->getNumero()}. Objet: {$reponse->getObjet()}. qui vous a étét transmis par: {$reponse->getIdRedacteur()?->getFirstName()} {$reponse->getIdRedacteur()?->getLastName()}.merci! \nMINEPIA: internal reply:  kindly review the mail n{$courrierItem->getNumero()}. Subject: {$reponse->getObjet()}. that has been forwarded to you by: {$reponse->getIdRedacteur()?->getFirstName()} {$reponse->getIdRedacteur()?->getLastName()}. thank you!";
        } else {
            $message = "MINEPIA: courrier interne : merci de prendre connaissance du courrier. Objet: {$reponse->getObjet()}. qui vous a étét transmis par: {$reponse->getIdRedacteur()?->getFirstName()} {$reponse->getIdRedacteur()?->getLastName()}.merci! \nMINEPIA: internal reply:  kindly review the mail. Subject: {$reponse->getObjet()}. that has been forwarded to you by: {$reponse->getIdRedacteur()?->getFirstName()} {$reponse->getIdRedacteur()?->getLastName()}. thank you!";
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
                            'transmission_reponse_id' => $reponse->getId(),
                            'user_id' => $user->getId(),
                            'telephone' => $user->getPhone(),
                            'success' => $userResult['success'] ?? false
                        ]);
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

    private function sendEmailToServiceUsers(TransmissionReponse $reponse, $serviceDestinataire, $courrier = null): bool
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
                            'transmission_reponse_id' => $reponse->getId(),
                            'user_id' => $user->getId(),
                            'email' => $user->getEmail()
                        ]);
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
