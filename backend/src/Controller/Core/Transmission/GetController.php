<?php

namespace App\Controller\Core\Transmission;

use App\Entity\Core\User;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\ReponseRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Core\NotificationRepository;
use App\Repository\Core\UserRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Transmission")]
class GetController extends AbstractController
{
    public function __construct(
        private TransmissionRepository $transmissionRepository,
        private ReponseRepository $reponseRepository,
        private UserRepository $userRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private PieceJointeRepository $pieceJointeRepository,
        private NotificationRepository $notificationRepository,
    ) {}

    #[Route('/core/transmission/{id}', name: 'app_core_transmission_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/core/transmission/{id}',
        summary: 'Récupérer une transmission par son ID',
        tags: ['Transmission'],
        description: "Retourne les détails complets d'une transmission, incluant les informations du courrier et la liste des réponses associées.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la transmission',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200, 
                description: 'Transmission récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(
                            property: 'notification',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', nullable: true, example: 10),
                                new OA\Property(property: 'is_read', type: 'boolean', nullable: true, example: false),
                            ]
                        ),
                        new OA\Property(property: 'courrier', type: 'object'),
                        new OA\Property(property: 'serviceDestinataire', type: 'object'),
                        new OA\Property(property: 'emetteur', type: 'object'),
                        new OA\Property(property: 'statut', type: 'string', example: 'TraitÃ©'),
                        new OA\Property(property: 'isinstance', type: 'boolean', example: false, description: 'Indique si la transmission est en instance'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 3, description: 'Nombre de pieces jointes'),
                        new OA\Property(property: 'traitePar', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(
                            property: 'piecesJointes',
                            type: 'array',
                            description: 'Liste des pièces jointes de la transmission',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 250),
                                    new OA\Property(property: 'nom', type: 'string', example: 'bordereau.pdf'),
                                    new OA\Property(property: 'intitule', type: 'string', nullable: true, example: 'Bordereau de transmission'),
                                    new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier/pieces/6942c641c1399.pdf'),
                                    new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'reponses',
                            type: 'array',
                            description: 'Liste des réponses associées à cette transmission',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 5),
                                    new OA\Property(property: 'objet', type: 'string', example: 'RE: Demande de budget'),
                                    new OA\Property(property: 'commentairePublic', type: 'string', example: 'Voici notre réponse'),
                                    new OA\Property(property: 'commentaireInterne', type: 'string', example: 'Réponse à traiter en priorité'),
                                    new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                                    new OA\Property(property: 'typeTransmission', type: 'string', example: 'Electronique'),
                                    new OA\Property(property: 'dateReponse', type: 'string', format: 'date-time', example: '2025-12-03 14:30:00'),
                                    new OA\Property(
                                        property: 'typeReponse',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'nom', type: 'string', example: 'RÃ©ponse directe'),
                                        ]
                                    ),
                                    new OA\Property(
                                        property: 'serviceDestinataire',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 3),
                                            new OA\Property(property: 'nom', type: 'string', example: 'Service Financier'),
                                            new OA\Property(property: 'sigle', type: 'string', example: 'SF'),
                                        ]
                                    ),
                                    new OA\Property(
                                        property: 'redacteur',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 12),
                                            new OA\Property(property: 'fullName', type: 'string', example: 'Jean Dupont'),
                                            new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                                        ]
                                    ),
                                    new OA\Property(
                                        property: 'piecesJointes',
                                        type: 'array',
                                        description: 'Pièces jointes associées à la réponse',
                                        items: new OA\Items(
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 965),
                                                new OA\Property(property: 'nom', type: 'string', example: 'bordereau.pdf'),
                                                new OA\Property(property: 'intitule', type: 'string', nullable: true, example: 'Bordereau de réponse'),
                                                new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier/pieces/bordereau.pdf'),
                                                new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                                            ]
                                        )
                                    ),
                                    new OA\Property(property: 'courrierIds', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2]),
                                    new OA\Property(property: 'typesCourrierIds', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 3]),
                                    new OA\Property(
                                        property: 'typesCourrier',
                                        type: 'array',
                                        description: 'Types de courrier détaillés avec id et nom',
                                        items: new OA\Items(
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                                new OA\Property(property: 'nom', type: 'string', example: 'Accusé de réception'),
                                            ]
                                        )
                                    ),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-12-03 14:25:00'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Transmission non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function show(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTransmission');

        // Charger la transmission avec toutes les relations pour Ã©viter le lazy loading
        $transmission = $this->transmissionRepository->createQueryBuilder('t')
            ->leftJoin('t.idServiceDestinataire', 'sd')
            ->leftJoin('t.idEmetteur', 'em')
            ->leftJoin('em.idService', 'es')
            ->leftJoin('t.idCourrier', 'c')
            ->leftJoin('c.typeCourrier', 'tc')
            ->leftJoin('c.idProvenance', 'p')
            ->addSelect('sd', 'em', 'es', 'c', 'tc', 'p')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$transmission) {
            return $this->json(['code' => 404, 'message' => 'Transmission non trouvée.'], 404);
        }

        try {
            // Gérer le service destinataire qui pourrait être supprimé
            $serviceDestinataire = null;
            if ($transmission->getIdServiceDestinataire()) {
                try {
                    $service = $transmission->getIdServiceDestinataire();
                    $service->getId(); // Déclenche le lazy loading pour vérifier l'existence
                    $serviceDestinataire = [
                        'id' => $service->getId(),
                        'nom' => $service->getNom(),
                        'sigle' => $service->getSigle(),
                    ];
                } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                    $serviceDestinataire = null;
                }
            }

            // GÃ©rer le service de l'Ã©metteur qui pourrait Ãªtre supprimÃ©
            $emetteurService = null;
            if ($transmission->getIdEmetteur() && $transmission->getIdEmetteur()->getIdService()) {
                try {
                    $emetteurServiceEntity = $transmission->getIdEmetteur()->getIdService();
                    $emetteurServiceEntity->getId();
                    $emetteurService = [
                        'id' => $emetteurServiceEntity->getId(),
                        'nom' => $emetteurServiceEntity->getNom(),
                        'sigle' => $emetteurServiceEntity->getSigle(),
                    ];
                } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                    $emetteurService = null;
                }
            }

            $reponses = $this->getReponsesForTransmission($transmission->getId());

            $currentUser = $this->getUser();
            $service = $currentUser instanceof User ? $currentUser->getIdService() : null;
            $notificationMeta = $service
                ? $this->notificationRepository->findLatestTransmissionNotificationMetaForServiceAndTransmissionId($service, (int) $transmission->getId())
                : null;
            $responseData = [
                'id' => $transmission->getId(),
                'notification' => [
                    'id' => is_array($notificationMeta) ? ($notificationMeta['id'] ?? null) : null,
                    'is_read' => is_array($notificationMeta) ? ($notificationMeta['is_read'] ?? null) : null,
                ],
                'courrier' => [
                    'id' => $transmission->getIdCourrier()->getId(),
                    'numero' => $transmission->getIdCourrier()->getNumero(),
                    'reference' => $transmission->getIdCourrier()->getNumero(),
                    'objet' => $transmission->getIdCourrier()->getObjet(),
                    'commentaire' => $transmission->getIdCourrier()->getCommentaire(),
                    'commentairePublic' => $transmission->getIdCourrier()->getCommentairePublic(),
                    'commentaireInterne' => $transmission->getIdCourrier()->getCommentaireInterne(),
                    'classeCourrier' => $transmission->getIdCourrier()->getClasseCourrier(),
                    'document'=>$transmission->getIdCourrier()->getDocument(),
                    'dateArrivee' => $transmission->getIdCourrier()->getDateArrivee()?->format('Y-m-d'),
                    'dateEnregistrement' => $transmission->getIdCourrier()->getDateEnregistrement()?->format('Y-m-d H:i:s'),
                    'typeCourrier' => [
                        'id' => $transmission->getIdCourrier()->getTypeCourrier()?->getId(),
                        'nom' => $transmission->getIdCourrier()->getTypeCourrier()?->getNom(),
                    ],
                    'provenance' => [
                        'id' => $transmission->getIdCourrier()->getIdProvenance()?->getId(),
                        'nom' => $transmission->getIdCourrier()->getIdProvenance()?->getNom(),
                        'email' => $transmission->getIdCourrier()->getIdProvenance()?->getEmail(),
                        'telephone' => $transmission->getIdCourrier()->getIdProvenance()?->getTelephone(),
                    ],
                    'priorite' => $transmission->getIdCourrier()->getPriorite(),
                    'piecesJointes' => $this->getPiecesJointesCourrier($transmission->getIdCourrier()->getId()),
                    'statut' => $transmission->getIdCourrier()->getStatut(),
                    'isConfidentiel' => $transmission->getIdCourrier()->isConfidentiel(),
                ],
                'serviceDestinataire' => $serviceDestinataire,
                'emetteur' => [
                    'id' => $transmission->getIdEmetteur()?->getId(),
                    'username' => $transmission->getIdEmetteur()?->getUsername(),
                    'fullName' => $transmission->getIdEmetteur()?->getFullName(),
                    'email' => $transmission->getIdEmetteur()?->getEmail(),
                    'service' => $emetteurService,
                ],
                'structuresCopie' => $transmission->getStructuresCopie(),
                'dateInstruction' => $transmission->getDateInstruction()?->format('Y-m-d H:i:s'),
                'instruction' => $transmission->getInstruction(),
                'delaiTraitement' => $transmission->getDelaiTraitement(),
                'typeTransfert' => $transmission->getTypeTransfert(),
                'accuseReception' => $transmission->isAccuseReception(),
                'statut' => $transmission->getStatut(),
                'isinstance' => $transmission->isinstance(),
                'nombrePieceJointe' => $transmission->getNombrePieceJointe(),
                'traitePar' => $this->enrichTraitePar($transmission->getTraitePar()),
                'piecesJointes' => $this->getPiecesJointes($transmission->getId()),
                'createdAt' => $transmission->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $transmission->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'reponses' => $reponses,
                'is_reponse' => !empty($reponses),
            ];

            // Récupérer les pièces jointes des AUTRES transmissions du même courrier
            $piecesAutresTransmissions = [];
            $courrierId = $transmission->getIdCourrier()?->getId();
            if ($courrierId) {
                $otherTransmissions = $this->transmissionRepository->createQueryBuilder('t2')
                    ->where('t2.idCourrier = :courrierId')
                    ->andWhere('t2.isDelete = :isDelete')
                    ->andWhere('t2.id != :currentId')
                    ->setParameter('courrierId', $courrierId)
                    ->setParameter('isDelete', false)
                    ->setParameter('currentId', $transmission->getId())
                    ->orderBy('t2.dateInstruction', 'DESC')
                    ->getQuery()
                    ->getResult();

                foreach ($otherTransmissions as $ot) {
                    $piecesAutresTransmissions[] = [
                        'transmission_id' => $ot->getId(),
                        'dateInstruction' => $ot->getDateInstruction()?->format('Y-m-d H:i:s'),
                        'pieces' => $this->getPiecesJointes($ot->getId()),
                    ];
                }
            }

            // Ajouter au payload
            $responseData['piecesJointesAutresTransmissions'] = $piecesAutresTransmissions;
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la récupération de la transmission: ' . $e->getMessage()
            ], 500);
        }

        return $this->json($responseData, 200);
    }

    /**
     * ðŸ” Enrichit le champ traitePar avec les informations complÃ¨tes des utilisateurs
     * 
     * @param array|null $traitePar Le tableau traitePar de la transmission
     * @return array|null Le tableau enrichi avec fullName et service pour chaque action
     */
    private function enrichTraitePar(?array $traitePar): ?array
    {
        if (empty($traitePar)) {
            return $traitePar;
        }

        $enrichedTraitePar = [];

        foreach ($traitePar as $action) {
            $enrichedAction = $action;

            // Identifier le champ contenant l'ID de l'utilisateur selon l'action
            $userIdField = null;
            if (isset($action['transmis_par_id'])) {
                $userIdField = 'transmis_par_id';
            } elseif (isset($action['accuse_par_id'])) {
                $userIdField = 'accuse_par_id';
            } elseif (isset($action['repondu_par_id'])) {
                $userIdField = 'repondu_par_id';
            } elseif (isset($action['classe_par_id'])) {
                $userIdField = 'classe_par_id';
            }

            // Si un utilisateur est trouvÃ©, rÃ©cupÃ©rer ses informations
            if ($userIdField && isset($action[$userIdField])) {
                $userId = $action[$userIdField];
                $user = $this->userRepository->find($userId);

                if ($user) {
                    // Ajouter le fullName de l'utilisateur
                    $enrichedAction[$userIdField . '_fullname'] = $user->getFullName();

                    // Ajouter le service de l'utilisateur s'il en a un
                    if ($user->getIdService()) {
                        $enrichedAction[$userIdField . '_service'] = [
                            'id' => $user->getIdService()->getId(),
                            'nom' => $user->getIdService()->getNom(),
                            'sigle' => $user->getIdService()->getSigle(),
                        ];
                    } else {
                        $enrichedAction[$userIdField . '_service'] = null;
                    }
                } else {
                    // Utilisateur non trouvÃ©
                    $enrichedAction[$userIdField . '_fullname'] = 'Utilisateur introuvable';
                    $enrichedAction[$userIdField . '_service'] = null;
                }
            }

            $enrichedTraitePar[] = $enrichedAction;
        }

        return $enrichedTraitePar;
    }

    /**
     * ï¿½ RÃ©cupÃ¨re les piÃ¨ces jointes d'une transmission depuis la base de donnÃ©es
     * 
     * @param int $transmissionId L'ID de la transmission
     * @return array Liste des piÃ¨ces jointes avec id, nom, intitule, chemin, type
     */
    private function getPiecesJointes(int $transmissionId): array
    {
        try {
            $piecesJointes = $this->pieceJointeRepository->findBy([
                'idParent' => $transmissionId,
                'typeParent' => 'Transmission',
                'isDelete' => false
            ]);

            $result = [];
            foreach ($piecesJointes as $piece) {
                $result[] = [
                    'id' => $piece->getId(),
                    'nom' => $piece->getNom(),
                    'intitule' => $piece->getIntitule(),
                    'chemin' => $piece->getChemin(),
                    'type' => $piece->getType(),
                ];
            }

            return $result;
        } catch (\Exception $e) {
            // En cas d'erreur, retourner un tableau vide
            return [];
        }
    }

    /**
     * ï¿½ðŸ“‹ RÃ©cupÃ¨re toutes les rÃ©ponses associÃ©es Ã  une transmission
     * 
     * @param int $transmissionId L'ID de la transmission
     * @return array Liste des rÃ©ponses formatÃ©es
     */
    private function getReponsesForTransmission(int $transmissionId): array
    {
        try {
            // Rechercher toutes les rÃ©ponses oÃ¹ idTransmission contient cet ID de transmission
            // Utilisation d'un filtrage en PHP car JSON_CONTAINS peut causer des problÃ¨mes
            $allReponses = $this->reponseRepository->createQueryBuilder('r')
                ->where('r.isDelete = :isDelete')
                ->setParameter('isDelete', false)
                ->andWhere('r.idTransmission IS NOT NULL')
                ->orderBy('r.dateReponse', 'DESC')
                ->addOrderBy('r.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            $reponses = [];
            foreach ($allReponses as $reponse) {
                // Filtrer en PHP les rÃ©ponses qui contiennent l'ID de transmission
                $idTransmissionArray = $reponse->getIdTransmission();
                if (is_array($idTransmissionArray) && in_array($transmissionId, $idTransmissionArray)) {
                    $reponses[] = [
                        'id' => $reponse->getId(),
                        'objet' => $reponse->getObjet(),
                        'commentairePublic' => $reponse->getCommentairePublic(),
                        'commentaireInterne' => $reponse->getCommentaireInterne(),
                        'classeCourrier' => $reponse->getClasseCourrier(),
                        'typeTransmission' => $reponse->getTypeTransmission(),
                        'dateReponse' => $reponse->getDateReponse()?->format('Y-m-d H:i:s'),
                        'typeReponse' => $reponse->getTypeReponse() ? [
                            'id' => $reponse->getTypeReponse()->getId(),
                            'nom' => $reponse->getTypeReponse()->getNom(),
                        ] : null,
                        'serviceDestinataire' => $reponse->getIdServiceDestinataire() ? [
                            'id' => $reponse->getIdServiceDestinataire()->getId(),
                            'nom' => $reponse->getIdServiceDestinataire()->getNom(),
                            'sigle' => $reponse->getIdServiceDestinataire()->getSigle(),
                        ] : null,
                        'redacteur' => $reponse->getIdRedacteur() ? [
                            'id' => $reponse->getIdRedacteur()->getId(),
                            'fullName' => $reponse->getIdRedacteur()->getFullName(),
                            'email' => $reponse->getIdRedacteur()->getEmail(),
                        ] : null,
                        'piecesJointes' => $this->getPiecesJointesReponse($reponse->getId()),
                        'courriers' => $reponse->getCourriers()->map(function($courrier) {
                            return [
                                'id' => $courrier->getId(),
                                'numero' => $courrier->getNumero(),
                                'objet' => $courrier->getObjet(),
                            ];
                        })->toArray(),
                        'typesCourrierIds' => $reponse->getTypesCourrierIds(),
                        'typesCourrier' => $this->getTypesCourrierDetails($reponse->getTypesCourrierIds()),
                        'createdAt' => $reponse->getCreatedAt()?->format('Y-m-d H:i:s'),
                    ];
                }
            }

            return $reponses;
        } catch (\Exception $e) {
            // En cas d'erreur, retourner un tableau vide plutÃ´t que de faire Ã©chouer toute la requÃªte
            return [];
        }
    }

    /**
     * ðŸ“Ž RÃ©cupÃ¨re les piÃ¨ces jointes d'un courrier depuis la base de donnÃ©es
     * 
     * @param int $courrierId L'ID du courrier
     * @return array Liste des piÃ¨ces jointes avec id, nom, intitule, chemin, type
     */
    private function getPiecesJointesCourrier(int $courrierId): array
    {
        try {
            $piecesJointes = $this->pieceJointeRepository->findBy([
                'idParent' => $courrierId,
                'typeParent' => 'Courrier',
                'isDelete' => false
            ]);

            $result = [];
            foreach ($piecesJointes as $piece) {
                $result[] = [
                    'id' => $piece->getId(),
                    'nom' => $piece->getNom(),
                    'intitule' => $piece->getIntitule(),
                    'chemin' => $piece->getChemin(),
                    'type' => $piece->getType(),
                ];
            }

            return $result;
        } catch (\Exception $e) {
            // En cas d'erreur, retourner un tableau vide
            return [];
        }
    }

    /**
     * Récupère les pièces jointes d'une réponse depuis la base de données
     *
     * @param int $reponseId L'ID de la réponse
     * @return array Liste des pièces jointes avec id, nom, intitule, chemin, type
     */
    private function getPiecesJointesReponse(int $reponseId): array
    {
        try {
            $piecesJointes = $this->pieceJointeRepository->findBy([
                'idParent' => $reponseId,
                'typeParent' => 'Reponse',
                'isDelete' => false
            ]);

            $result = [];
            foreach ($piecesJointes as $piece) {
                $result[] = [
                    'id' => $piece->getId(),
                    'nom' => $piece->getNom(),
                    'intitule' => $piece->getIntitule(),
                    'chemin' => $piece->getChemin(),
                    'type' => $piece->getType(),
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Récupère les types de courrier détaillés depuis une liste d'IDs
     *
     * @param array|null $typesCourrierIds Liste d'IDs des types de courrier
     * @return array Liste des types de courrier avec id et nom
     */
    private function getTypesCourrierDetails(?array $typesCourrierIds): array
    {
        if (empty($typesCourrierIds)) {
            return [];
        }

        $typesCourrier = [];

        foreach ($typesCourrierIds as $typeId) {
            if (!is_numeric($typeId)) {
                continue;
            }

            $typeCourrier = $this->entityManager->getRepository(\App\Entity\Core\TypeCourrier::class)->find((int) $typeId);
            if ($typeCourrier && !$typeCourrier->isDelete()) {
                $typesCourrier[] = [
                    'id' => $typeCourrier->getId(),
                    'nom' => $typeCourrier->getNom(),
                ];
            }
        }

        return $typesCourrier;
    }
}
