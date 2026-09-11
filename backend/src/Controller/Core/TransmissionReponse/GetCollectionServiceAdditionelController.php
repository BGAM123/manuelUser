<?php

namespace App\Controller\Core\TransmissionReponse;

use App\Entity\Cour\CourrierInterne;
use App\Entity\Core\User;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\ReponseRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TransmissionReponse")]
class GetCollectionServiceAdditionelController extends AbstractController
{
    public function __construct(
        private TransmissionReponseRepository $transmissionReponseRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private ReponseRepository $reponseRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/transmission-reponse/service-additionel', name: 'app_core_transmission_reponse_get_collection_service_additionel', methods: ['GET'])]
    #[OA\Get(
        path: '/core/transmission-reponse/service-additionel',
        summary: 'Lister les transmissions reponse des services additionels de l\'utilisateur connecte avec filtres et pagination',
        description: 'Retourne la liste des transmissions reponse recues par les services additionels de l\'utilisateur connecte.',
        tags: ['TransmissionReponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'order_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['DESC', 'ASC'], default: 'DESC')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'type_courrier', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'service_destinataire_reponse_liee', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'statut_reponse_liee', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'priorite', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'classe_courrier', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type_transmission', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'service_destinataire', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'redacteur', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type_reponse', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Recherche textuelle sur toutes les donnees retournees de la reponse', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succes - Liste des transmissions reponse recuperee',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'reponsesRecuesParMonService', type: 'object'),
                        new OA\Property(property: 'totalGlobal', type: 'integer', example: 5),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non autorise')
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTransmissionReponseCollection');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['code' => 401, 'message' => 'Accès non autorisé.'], 401);
        }

        $servicesAdditionel = $currentUser->getServicesAdditionel();
        $serviceIdsAdditionel = [];
        $serviceAdditionelUserIdsByService = [];
        if (is_array($servicesAdditionel)) {
            foreach ($servicesAdditionel as $service) {
                $serviceId = null;
                $userId = null;
                if (is_array($service)) {
                    if (isset($service['serviceId'])) {
                        $serviceId = (int) $service['serviceId'];
                    } elseif (isset($service['id'])) {
                        $serviceId = (int) $service['id'];
                    }
                    if (isset($service['userId'])) {
                        $userId = (int) $service['userId'];
                    } elseif (isset($service['user_id'])) {
                        $userId = (int) $service['user_id'];
                    } elseif (isset($service['idUser'])) {
                        $userId = (int) $service['idUser'];
                    }
                } elseif (is_int($service) || is_numeric($service)) {
                    $serviceId = (int) $service;
                }

                if ($serviceId && $serviceId > 0) {
                    $serviceIdsAdditionel[] = $serviceId;
                    if ($userId && $userId > 0) {
                        $serviceAdditionelUserIdsByService[$serviceId] = $userId;
                    }
                }
            }
        }
        $serviceIdsAdditionel = array_values(array_unique(array_filter(
            $serviceIdsAdditionel,
            static fn ($id) => $id > 0
        )));

        $additionelUserIds = array_values(array_unique(array_filter(
            $serviceAdditionelUserIdsByService,
            static fn ($id) => $id > 0
        )));
        $additionelUsersById = [];
        if (!empty($additionelUserIds)) {
            $additionelUsers = $this->entityManager->getRepository(User::class)->findBy(['id' => $additionelUserIds]);
            foreach ($additionelUsers as $user) {
                $additionelUsersById[$user->getId()] = $user;
            }
        }

        $getTransmitUserForResponse = function (\App\Entity\Cour\TransmissionReponse $response) use (
            $serviceAdditionelUserIdsByService,
            $additionelUsersById,
            $currentUser
        ): ?User {
            $serviceDestinataire = $response->getIdServiceDestinataire();
            if ($serviceDestinataire) {
                $serviceId = $serviceDestinataire->getId();
                $userId = $serviceAdditionelUserIdsByService[$serviceId] ?? null;
                if ($userId && isset($additionelUsersById[$userId])) {
                    return $additionelUsersById[$userId];
                }
            }

            return $currentUser;
        };

        $filters = [
            'order_by' => $request->query->get('order_by', 'DESC'),
            'start_date' => $request->query->get('start_date'),
            'end_date' => $request->query->get('end_date'),
            'date' => $request->query->get('date'),
            'type_courrier' => $request->query->get('type_courrier'),
            'service_destinataire_reponse_liee' => $request->query->get('service_destinataire_reponse_liee'),
            'statut_reponse_liee' => $request->query->get('statut_reponse_liee'),
            'priorite' => $request->query->get('priorite'),
            'classe_courrier' => $request->query->get('classe_courrier'),
            'type_transmission' => $request->query->get('type_transmission'),
            'service_destinataire' => $request->query->get('service_destinataire'),
            'redacteur' => $request->query->get('redacteur'),
            'type_reponse' => $request->query->get('type_reponse'),
            'is_delete' => filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN),
            'search' => $request->query->get('search'),
            'year' => $request->query->get('year'),
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => (int)$request->query->get('limit', 10),
        ];

        $page = $filters['page'];
        $limit = $filters['limit'];
        if (empty($serviceIdsAdditionel)) {
            $this->actionLogger->logView(
                'TransmissionReponse',
                null,
                'Consultation de la liste des transmissions reponse (services additionels)',
                [
                    'totalReponsesService' => 0,
                    'totalGlobal' => 0,
                    'page' => $page,
                    'limit' => $limit,
                    'search' => $filters['search'] ?: null,
                    'filters' => $filters,
                    'service_additionel_ids' => $serviceIdsAdditionel,
                ]
            );

            return $this->json([
                'page' => $page,
                'limit' => $limit,
                'reponsesRecuesParMonService' => [
                    'description' => 'Transmissions reponse envoyees aux services additionels de l\'utilisateur connecte',
                    'total' => 0,
                    'data' => [],
                ],
                'totalGlobal' => 0,
            ], 200);
        }

        $queryBuilderServiceResponses = $this->transmissionReponseRepository->createQueryBuilder('r')
            ->leftJoin('r.idServiceDestinataire', 'sd')
            ->leftJoin('r.idRedacteur', 'red')
            ->leftJoin('r.typeReponse', 'tr')
            ->addSelect('sd', 'red', 'tr')
            ->where('r.isDelete = :isDelete')
            ->andWhere('r.idServiceDestinataire IN (:serviceIdsAdditionel)')
            ->setParameter('isDelete', $filters['is_delete'])
            ->setParameter('serviceIdsAdditionel', $serviceIdsAdditionel);

        $applyFilters = function($qb) use ($filters) {

            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $qb->andWhere('r.dateReponse >= :start AND r.dateReponse <= :end')
                   ->setParameter('start', $filters['start_date'] . ' 00:00:00')
                   ->setParameter('end', $filters['end_date'] . ' 23:59:59');
            } elseif (!empty($filters['start_date'])) {
                $qb->andWhere('r.dateReponse >= :startOnly')
                   ->setParameter('startOnly', $filters['start_date'] . ' 00:00:00');
            } elseif (!empty($filters['end_date'])) {
                $qb->andWhere('r.dateReponse >= :endDateStart AND r.dateReponse <= :endDateEnd')
                   ->setParameter('endDateStart', $filters['end_date'] . ' 00:00:00')
                   ->setParameter('endDateEnd', $filters['end_date'] . ' 23:59:59');
            } elseif (!empty($filters['date'])) {
                $qb->andWhere('r.dateReponse >= :dateStart AND r.dateReponse <= :dateEnd')
                   ->setParameter('dateStart', $filters['date'] . ' 00:00:00')
                   ->setParameter('dateEnd', $filters['date'] . ' 23:59:59');
            } elseif (!empty($filters['year'])) {
                $year = (int) $filters['year'];
                if ($year >= 1900 && $year <= 2100) {
                    $startOfYear = (new \DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
                    $endOfYear = (new \DateTimeImmutable())->setDate($year, 12, 31)->setTime(23, 59, 59);
                    $qb->andWhere('r.dateReponse >= :yearStart AND r.dateReponse <= :yearEnd')
                        ->setParameter('yearStart', $startOfYear)
                        ->setParameter('yearEnd', $endOfYear);
                }
            }

            if (!empty($filters['priorite']) && $filters['priorite'] !== 'Toutes') {
                $qb->andWhere('r.priorite = :priorite')
                   ->setParameter('priorite', $filters['priorite']);
            }
            if (!empty($filters['classe_courrier'])) {
                $qb->andWhere('LOWER(r.classeCourrier) LIKE LOWER(:classeCourrier)')
                   ->setParameter('classeCourrier', '%' . trim($filters['classe_courrier']) . '%');
            }
            if (!empty($filters['type_transmission'])) {
                $qb->andWhere('LOWER(r.typeTransmission) LIKE LOWER(:typeTransmission)')
                   ->setParameter('typeTransmission', '%' . trim($filters['type_transmission']) . '%');
            }
            if (!empty($filters['type_reponse'])) {
                $qb->andWhere('tr.id = :typeReponse')
                   ->setParameter('typeReponse', $filters['type_reponse']);
            }
            if (!empty($filters['service_destinataire'])) {
                $qb->andWhere('r.idServiceDestinataire = :filterServiceDestinataire')
                   ->setParameter('filterServiceDestinataire', $filters['service_destinataire']);
            }
            if (!empty($filters['redacteur'])) {
                $qb->andWhere('r.idRedacteur = :filterRedacteur')
                   ->setParameter('filterRedacteur', $filters['redacteur']);
            }

            return $qb;
        };

        $applyFilters($queryBuilderServiceResponses);

        $queryBuilderServiceResponses->orderBy('r.createdAt', $filters['order_by']);

        $limit = $filters['limit'];
        $page = $filters['page'];
        $typeCourrierFilter = (is_numeric($filters['type_courrier'] ?? null) && (int) $filters['type_courrier'] > 0)
            ? (int) $filters['type_courrier']
            : null;
        $linkedServiceDestinataireFilter = (is_numeric($filters['service_destinataire_reponse_liee'] ?? null) && (int) $filters['service_destinataire_reponse_liee'] > 0)
            ? (int) $filters['service_destinataire_reponse_liee']
            : null;
        $linkedStatutFilter = is_string($filters['statut_reponse_liee'] ?? null) && trim($filters['statut_reponse_liee']) !== ''
            ? trim($filters['statut_reponse_liee'])
            : null;
        $linkedStatutFilterNormalized = $linkedStatutFilter !== null
            ? (function_exists('mb_strtolower') ? mb_strtolower($linkedStatutFilter, 'UTF-8') : strtolower($linkedStatutFilter))
            : null;
        $searchFilter = is_string($filters['search'] ?? null) && trim($filters['search']) !== ''
            ? trim($filters['search'])
            : null;
        $searchFilterNormalized = $searchFilter !== null
            ? (function_exists('mb_strtolower') ? mb_strtolower($searchFilter, 'UTF-8') : strtolower($searchFilter))
            : null;
        $requiresPostFiltering = $typeCourrierFilter !== null
            || $linkedServiceDestinataireFilter !== null
            || $linkedStatutFilterNormalized !== null
            || $searchFilterNormalized !== null;

        if ($requiresPostFiltering) {
            // Filtrage applicatif sur champs JSON et reponses liees pour eviter les differences SQL selon les versions.
            $allServiceResponses = $queryBuilderServiceResponses->getQuery()->getResult();

            $courrierInterneByIdForSearch = [];
            if ($searchFilterNormalized !== null) {
                $courrierInterneIdsForSearch = [];
                foreach ($allServiceResponses as $response) {
                    foreach ($this->resolveCourrierInterneIds($response) as $courrierInterneId) {
                        if (is_numeric($courrierInterneId)) {
                            $courrierInterneIdsForSearch[] = (int) $courrierInterneId;
                        }
                    }
                }

                $courrierInterneIdsForSearch = array_values(array_unique($courrierInterneIdsForSearch));
                if (!empty($courrierInterneIdsForSearch)) {
                    $courrierInternesForSearch = $this->entityManager->getRepository(CourrierInterne::class)->findBy([
                        'id' => $courrierInterneIdsForSearch,
                    ]);
                    foreach ($courrierInternesForSearch as $courrierInterne) {
                        $courrierInterneId = $courrierInterne->getId();
                        if ($courrierInterneId !== null) {
                            $courrierInterneByIdForSearch[(int) $courrierInterneId] = $courrierInterne;
                        }
                    }
                }
            }

            $allServiceResponses = array_values(array_filter($allServiceResponses, function ($response) use ($typeCourrierFilter, $linkedServiceDestinataireFilter, $linkedStatutFilterNormalized, $searchFilterNormalized, $courrierInterneByIdForSearch, $getTransmitUserForResponse) {
                if ($typeCourrierFilter !== null) {
                    $typesCourrierIds = $response->getTypesCourrierIds();
                    if (!is_array($typesCourrierIds)) {
                        return false;
                    }

                    $hasTypeCourrier = false;
                    foreach ($typesCourrierIds as $typeId) {
                        if ((int) $typeId === $typeCourrierFilter) {
                            $hasTypeCourrier = true;
                            break;
                        }
                    }

                    if (!$hasTypeCourrier) {
                        return false;
                    }
                }

                if ($linkedServiceDestinataireFilter !== null || $linkedStatutFilterNormalized !== null) {
                    $linkedIds = is_array($response->getIdCourrierInternes()) ? $response->getIdCourrierInternes() : [];
                    if (empty($linkedIds)) {
                        $linkedIds = [$response->getId()];
                    }

                    $hasLinkedServiceDestinataire = $linkedServiceDestinataireFilter === null;
                    $hasLinkedStatut = $linkedStatutFilterNormalized === null;
                    foreach ($linkedIds as $reponseId) {
                        if (!is_numeric($reponseId)) {
                            continue;
                        }

                        $lastTransmission = $this->transmissionReponseRepository->findLatestByCourrierInterneId((int) $reponseId);
                        if (!$hasLinkedServiceDestinataire) {
                            $serviceDestinataire = $lastTransmission?->getIdServiceDestinataire();
                            if ($serviceDestinataire && $serviceDestinataire->getId() === $linkedServiceDestinataireFilter) {
                                $hasLinkedServiceDestinataire = true;
                            }
                        }

                        if (!$hasLinkedStatut) {
                            $statut = $lastTransmission?->getStatut();
                            if (is_string($statut) && trim($statut) !== '') {
                                $normalizedStatut = function_exists('mb_strtolower')
                                    ? mb_strtolower(trim($statut), 'UTF-8')
                                    : strtolower(trim($statut));
                                if ($normalizedStatut === $linkedStatutFilterNormalized) {
                                    $hasLinkedStatut = true;
                                }
                            }
                        }

                        if ($hasLinkedServiceDestinataire && $hasLinkedStatut) {
                            break;
                        }
                    }

                    if (!$hasLinkedServiceDestinataire || !$hasLinkedStatut) {
                        return false;
                    }
                }

                if ($searchFilterNormalized !== null) {
                    $searchValues = [];
                    $pushSearchValue = static function (array &$values, $value): void {
                        if ($value === null) {
                            return;
                        }
                        if (is_bool($value)) {
                            $values[] = $value ? 'true' : 'false';
                            return;
                        }
                        if (is_scalar($value)) {
                            $stringValue = trim((string) $value);
                            if ($stringValue !== '') {
                                $values[] = $stringValue;
                            }
                        }
                    };

                    // Champs retournes de la reponse TransmissionReponse.
                    $pushSearchValue($searchValues, $response->getId());
                    $pushSearchValue($searchValues, $response->getObjet());
                    $pushSearchValue($searchValues, $response->getCommentairePublic());
                    $pushSearchValue($searchValues, $response->getClasseCourrier());
                    $pushSearchValue($searchValues, $response->getTypeTransmission());
                    $pushSearchValue($searchValues, $response->getPriorite());
                    $pushSearchValue($searchValues, $response->getStatut());
                    $pushSearchValue($searchValues, $response->isAccuseReception());
                    $pushSearchValue($searchValues, $response->isinstance());
                    $pushSearchValue($searchValues, $response->isGeled());
                    $pushSearchValue($searchValues, $response->getDateReponse()?->format('Y-m-d'));
                    $pushSearchValue($searchValues, $response->getNombrePieceJointe());
                    $pushSearchValue($searchValues, $response->getCreatedAt()?->format('Y-m-d'));

                    $serviceDestinataire = $response->getIdServiceDestinataire();
                    if ($serviceDestinataire) {
                        $pushSearchValue($searchValues, $serviceDestinataire->getId());
                        $pushSearchValue($searchValues, $serviceDestinataire->getNom());
                        $pushSearchValue($searchValues, $serviceDestinataire->getSigle());
                    }

                    $redacteur = $response->getIdRedacteur();
                    if ($redacteur) {
                        $pushSearchValue($searchValues, $redacteur->getId());
                        $pushSearchValue($searchValues, $redacteur->getFullName());
                        $pushSearchValue($searchValues, $redacteur->getEmail());
                        $serviceRedacteur = $redacteur->getIdService();
                        if ($serviceRedacteur) {
                            $pushSearchValue($searchValues, $serviceRedacteur->getId());
                            $pushSearchValue($searchValues, $serviceRedacteur->getNom());
                            $pushSearchValue($searchValues, $serviceRedacteur->getSigle());
                        }
                    }

                    if (is_array($response->getIdTransmission())) {
                        foreach ($response->getIdTransmission() as $idTransmission) {
                            $pushSearchValue($searchValues, $idTransmission);
                        }
                    }

                    $typesCourrierIds = $response->getTypesCourrierIds();
                    if (is_array($typesCourrierIds)) {
                        foreach ($typesCourrierIds as $typeId) {
                            $pushSearchValue($searchValues, $typeId);
                            if (is_numeric($typeId)) {
                                $typeCourrier = $this->entityManager->getRepository(\App\Entity\Core\TypeCourrier::class)->find((int) $typeId);
                                if ($typeCourrier && !$typeCourrier->isDelete()) {
                                    $pushSearchValue($searchValues, $typeCourrier->getId());
                                    $pushSearchValue($searchValues, $typeCourrier->getNom());
                                    $pushSearchValue($searchValues, $typeCourrier->getType());
                                }
                            }
                        }
                    }

                    $linkedIdsForSearch = is_array($response->getIdCourrierInternes()) ? $response->getIdCourrierInternes() : [];
                    if (empty($linkedIdsForSearch)) {
                        $linkedIdsForSearch = [$response->getId()];
                    }

                    foreach ($linkedIdsForSearch as $reponseId) {
                        $pushSearchValue($searchValues, $reponseId);
                        if (!is_numeric($reponseId)) {
                            continue;
                        }

                        $courrierInterneId = (int) $reponseId;
                        $courrierInterne = $courrierInterneByIdForSearch[$courrierInterneId] ?? null;
                        if ($courrierInterne) {
                            $pushSearchValue($searchValues, $courrierInterne->getNumero());
                            $pushSearchValue($searchValues, $courrierInterne->getObjet());
                            $pushSearchValue($searchValues, $courrierInterne->getCommentairePublic());
                            $pushSearchValue($searchValues, $courrierInterne->getCommentaireInterne());
                            $pushSearchValue($searchValues, $courrierInterne->getPriorite());
                            $pushSearchValue($searchValues, $courrierInterne->getStatut());
                            $pushSearchValue($searchValues, $courrierInterne->getClasseCourrier());
                            $pushSearchValue($searchValues, $courrierInterne->getTypeTransmission());
                            $pushSearchValue($searchValues, $courrierInterne->getSearch());
                        }

                        $lastTransmission = $this->transmissionReponseRepository->findLatestByCourrierInterneId((int) $reponseId);
                        if ($lastTransmission) {
                            $pushSearchValue($searchValues, $lastTransmission->getStatut());
                            $pushSearchValue($searchValues, $lastTransmission->getId());
                            $pushSearchValue($searchValues, $lastTransmission->isAccuseReception());

                            $linkedServiceDestinataire = $lastTransmission->getIdServiceDestinataire();
                            if ($linkedServiceDestinataire) {
                                $pushSearchValue($searchValues, $linkedServiceDestinataire->getId());
                                $pushSearchValue($searchValues, $linkedServiceDestinataire->getNom());
                                $pushSearchValue($searchValues, $linkedServiceDestinataire->getSigle());
                            }
                        }
                    }

                    // Champs derives de la reponse retournee: canTransmit et derniereCreer.
                    $transmitUser = $getTransmitUserForResponse($response);
                    [
                        $canTransmit,
                        $latestUserTransmissionId,
                        $latestUserTransmissionCanModify
                    ] = $this->computeTransmissionPermissions($response, $transmitUser);
                    $pushSearchValue($searchValues, $canTransmit);
                    $pushSearchValue($searchValues, $latestUserTransmissionId);
                    $pushSearchValue($searchValues, $latestUserTransmissionCanModify);

                    $piecesJointes = $this->pieceJointeRepository->findBy([
                        'idParent' => $response->getId(),
                        'typeParent' => 'TransmissionReponse',
                        'isDelete' => false
                    ]);
                    foreach ($piecesJointes as $pieceJointe) {
                        $pushSearchValue($searchValues, $pieceJointe->getId());
                        $pushSearchValue($searchValues, $pieceJointe->getNom());
                        $pushSearchValue($searchValues, $pieceJointe->getChemin());
                        $pushSearchValue($searchValues, $pieceJointe->getType());
                    }

                    $searchHaystack = implode(' ', $searchValues);
                    $searchHaystackNormalized = function_exists('mb_strtolower')
                        ? mb_strtolower($searchHaystack, 'UTF-8')
                        : strtolower($searchHaystack);
                    if ($searchHaystackNormalized === '' || !str_contains($searchHaystackNormalized, $searchFilterNormalized)) {
                        return false;
                    }
                }

                return true;
            }));

            $totalServiceResponses = count($allServiceResponses);
            if ($limit > 0) {
                $offset = ($page - 1) * $limit;
                $serviceResponses = array_slice($allServiceResponses, $offset, $limit);
            } else {
                $serviceResponses = $allServiceResponses;
            }
        } else {
            $totalServiceResponses = (clone $queryBuilderServiceResponses)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

            if ($limit > 0) {
                $queryBuilderServiceResponses->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
            }

            $serviceResponses = $queryBuilderServiceResponses->getQuery()->getResult();
        }

        $courrierInterneNumerosById = [];
        $courrierInterneIds = [];
        foreach ($serviceResponses as $response) {
            foreach ($this->resolveCourrierInterneIds($response) as $courrierInterneId) {
                $courrierInterneIds[] = $courrierInterneId;
            }
        }
        $courrierInterneIds = array_values(array_unique(array_filter($courrierInterneIds, static fn ($id) => is_numeric($id))));
        if (!empty($courrierInterneIds)) {
            $courrierInternes = $this->entityManager->getRepository(CourrierInterne::class)->findBy([
                'id' => $courrierInterneIds
            ]);
            foreach ($courrierInternes as $courrierInterne) {
                $courrierInterneNumerosById[$courrierInterne->getId()] = $courrierInterne->getNumero();
            }
        }

        $formatReponse = function($r) use ($getTransmitUserForResponse, $courrierInterneNumerosById) {
            $piecesJointes = $this->pieceJointeRepository->findBy([
                'idParent' => $r->getId(),
                'typeParent' => 'TransmissionReponse',
                'isDelete' => false
            ]);

            $typesCourrier = [];
            if ($r->getTypesCourrierIds()) {
                foreach ($r->getTypesCourrierIds() as $typeId) {
                    $typeCourrier = $this->entityManager->getRepository(\App\Entity\Core\TypeCourrier::class)->find($typeId);
                    if ($typeCourrier && !$typeCourrier->isDelete()) {
                        $typesCourrier[] = [
                            'id' => $typeCourrier->getId(),
                            'nom' => $typeCourrier->getNom(),
                            'type' => $typeCourrier->getType(),
                        ];
                    }
                }
            }

            $courrierInterneLiees = [];
            foreach ($this->resolveCourrierInterneIds($r) as $courrierInterneId) {
                $lastTransmission = $this->transmissionReponseRepository->findLatestByCourrierInterneId($courrierInterneId);
                $courrierInterneLiees[] = [
                    'id' => $courrierInterneId,
                    'numero' => $courrierInterneNumerosById[$courrierInterneId] ?? null,
                    'statut' => $lastTransmission?->getStatut(),
                    'derniereTransmissionReponse' => $lastTransmission ? [
                        'id' => $lastTransmission->getId(),
                        'accuseReception' => $lastTransmission->isAccuseReception(),
                    ] : null,
                    'serviceDestinataire' => $lastTransmission?->getIdServiceDestinataire() ? [
                        'id' => $lastTransmission->getIdServiceDestinataire()->getId(),
                        'nom' => $lastTransmission->getIdServiceDestinataire()->getNom(),
                        'sigle' => $lastTransmission->getIdServiceDestinataire()->getSigle(),
                    ] : null,
                ];
            }

            $idCourrierInternesWithReference = array_map(fn($ci) => [
                'id' => $ci['id'],
                'reference' => $ci['numero'] ?? null,
            ], $courrierInterneLiees);

            $reponses = [];
            $reponseIds = is_array($r->getIdReponses()) ? array_values(array_unique(array_filter($r->getIdReponses(), 'is_numeric'))) : [];
            if (!empty($reponseIds)) {
                $reponsesEntities = $this->reponseRepository->findBy([
                    'id' => $reponseIds,
                    'isDelete' => false
                ]);
                $reponsesById = [];
                foreach ($reponsesEntities as $reponseEntity) {
                    $reponsesById[$reponseEntity->getId()] = $reponseEntity;
                }
                foreach ($reponseIds as $reponseId) {
                    $reponseEntity = $reponsesById[(int) $reponseId] ?? null;
                    if (!$reponseEntity) {
                        continue;
                    }
                    $reponses[] = [
                        'id' => $reponseEntity->getId(),
                        'objet' => $reponseEntity->getObjet(),
                        'commentairePublic' => $reponseEntity->getCommentairePublic(),
                        'commentaireInterne' => $reponseEntity->getCommentaireInterne(),
                        'classeCourrier' => $reponseEntity->getClasseCourrier(),
                        'typeTransmission' => $reponseEntity->getTypeTransmission(),
                        'priorite' => $reponseEntity->getPriorite(),
                        'statut' => $reponseEntity->getStatut(),
                        'dateReponse' => $reponseEntity->getDateReponse()?->format('Y-m-d'),
                        'serviceDestinataire' => $reponseEntity->getIdServiceDestinataire() ? [
                            'id' => $reponseEntity->getIdServiceDestinataire()->getId(),
                            'nom' => $reponseEntity->getIdServiceDestinataire()->getNom(),
                            'sigle' => $reponseEntity->getIdServiceDestinataire()->getSigle(),
                        ] : null,
                        'redacteur' => $reponseEntity->getIdRedacteur() ? [
                            'id' => $reponseEntity->getIdRedacteur()->getId(),
                            'fullName' => $reponseEntity->getIdRedacteur()->getFullName(),
                            'email' => $reponseEntity->getIdRedacteur()->getEmail(),
                        ] : null,
                    ];
                }
            }

            [
                $canTransmit,
                $latestUserTransmissionId,
                $latestUserTransmissionCanModify
            ] = $this->computeTransmissionPermissions($r, $getTransmitUserForResponse($r));

            $isReponse = !empty($reponses);

            return [
                'id' => $r->getId(),
                'courriers' => [],
                'typesCourrier' => $typesCourrier,
                'idTransmission' => $r->getIdTransmission(),
                'idCourrierInternes' => $idCourrierInternesWithReference,
                'reponses' => $reponses,
                'is_reponse' => $isReponse,
                'courrierInterneLiees' => $courrierInterneLiees,
                'objet' => $r->getObjet(),
                'commentairePublic' => $r->getCommentairePublic(),
                'classeCourrier' => $r->getClasseCourrier(),
                'typeTransmission' => $r->getTypeTransmission(),
                'priorite' => $r->getPriorite(),
                'statut' => $r->getStatut(),
                'accuseReception' => $r->isAccuseReception(),
                'isinstance' => $r->isinstance(),
                'is_geled' => $r->isGeled(),
                'serviceDestinataire' => $r->getIdServiceDestinataire() ? [
                    'id' => $r->getIdServiceDestinataire()->getId(),
                    'nom' => $r->getIdServiceDestinataire()->getNom(),
                    'sigle' => $r->getIdServiceDestinataire()->getSigle(),
                ] : null,
                'redacteur' => $r->getIdRedacteur() ? [
                    'id' => $r->getIdRedacteur()->getId(),
                    'fullName' => $r->getIdRedacteur()->getFullName(),
                    'email' => $r->getIdRedacteur()->getEmail(),
                    'service' => $r->getIdRedacteur()->getIdService() ? [
                        'id' => $r->getIdRedacteur()->getIdService()->getId(),
                        'nom' => $r->getIdRedacteur()->getIdService()->getNom(),
                        'sigle' => $r->getIdRedacteur()->getIdService()->getSigle(),
                    ] : null,
                ] : null,
                'dateReponse' => $r->getDateReponse()?->format('Y-m-d'),
                'canTransmit' => $canTransmit,
                'derniereCreer' => [
                    'idDernier' => $latestUserTransmissionId,
                    'canModify' => $latestUserTransmissionCanModify,
                ],
                'nombrePieceJointe' => $r->getNombrePieceJointe(),
                'piecesJointes' => array_map(fn($pj) => [
                    'id' => $pj->getId(),
                    'nom' => $pj->getNom(),
                    'chemin' => $pj->getChemin(),
                    'type' => $pj->getType(),
                ], $piecesJointes),
                'createdAt' => $r->getCreatedAt()?->format('Y-m-d'),
            ];
        };

        $serviceResponsesData = array_map($formatReponse, $serviceResponses);

        $this->actionLogger->logView(
            'TransmissionReponse',
            null,
            'Consultation de la liste des transmissions reponse (services additionels)',
            [
                'totalReponsesService' => (int)$totalServiceResponses,
                'totalGlobal' => (int)$totalServiceResponses,
                'page' => $filters['page'],
                'limit' => $filters['limit'],
                'search' => $filters['search'] ?: null,
                'filters' => $filters,
                'service_additionel_ids' => $serviceIdsAdditionel,
            ]
        );

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'reponsesRecuesParMonService' => [
                'description' => 'Transmissions reponse envoyees au service de l\'utilisateur connecte',
                'total' => (int)$totalServiceResponses,
                'data' => $serviceResponsesData
            ],
            'totalGlobal' => (int)$totalServiceResponses,
        ], 200);
    }

    /**
     * @return array<int, int>
     */
    private function resolveCourrierInterneIds(\App\Entity\Cour\TransmissionReponse $reponse): array
    {
        $ids = $reponse->getIdCourrierInternes();
        if (!is_array($ids)) {
            $fallbackId = $reponse->getId();
            return $fallbackId !== null ? [(int) $fallbackId] : [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $normalized[] = (int) $id;
            }
        }

        if (empty($normalized)) {
            $fallbackId = $reponse->getId();
            return $fallbackId !== null ? [(int) $fallbackId] : [];
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array{0: bool, 1: ?int, 2: bool}
     */
    private function computeTransmissionPermissions(
        \App\Entity\Cour\TransmissionReponse $reponse,
        ?\App\Entity\Core\User $currentUser
    ): array {
        $canTransmit = true;
        $latestUserTransmissionId = null;
        $latestUserTransmissionCanModify = false;

        $linkedIds = $this->resolveCourrierInterneIds($reponse);
        if (empty($linkedIds) || !$currentUser) {
            return [$canTransmit, $latestUserTransmissionId, $latestUserTransmissionCanModify];
        }

        $latestUserTransmission = null;
        $latestOverallTransmission = null;
        foreach ($linkedIds as $courrierInterneId) {
            $latestTransmissionForLinked = $this->transmissionReponseRepository
                ->findLatestByCourrierInterneId($courrierInterneId);

            if ($latestTransmissionForLinked) {
                $shouldReplaceOverall = false;
                if (!$latestOverallTransmission) {
                    $shouldReplaceOverall = true;
                } else {
                    $currentCreated = $latestTransmissionForLinked->getCreatedAt();
                    $latestCreated = $latestOverallTransmission->getCreatedAt();

                    if ($currentCreated && (!$latestCreated || $currentCreated > $latestCreated)) {
                        $shouldReplaceOverall = true;
                    } elseif (!$currentCreated && !$latestCreated && $latestTransmissionForLinked->getId() > $latestOverallTransmission->getId()) {
                        $shouldReplaceOverall = true;
                    }
                }

                if ($shouldReplaceOverall) {
                    $latestOverallTransmission = $latestTransmissionForLinked;
                }
            }

            $userTransmission = $this->transmissionReponseRepository
                ->findLatestByCourrierInterneIdAndRedacteurId($courrierInterneId, $currentUser->getId());

            if (!$userTransmission) {
                continue;
            }

            $shouldReplace = false;
            if (!$latestUserTransmission) {
                $shouldReplace = true;
            } else {
                $currentCreated = $userTransmission->getCreatedAt();
                $latestCreated = $latestUserTransmission->getCreatedAt();

                if ($currentCreated && (!$latestCreated || $currentCreated > $latestCreated)) {
                    $shouldReplace = true;
                } elseif (!$currentCreated && !$latestCreated && $userTransmission->getId() > $latestUserTransmission->getId()) {
                    $shouldReplace = true;
                }
            }

            if ($shouldReplace) {
                $latestUserTransmission = $userTransmission;
            }
        }

        if ($latestOverallTransmission?->getIdRedacteur() && $latestOverallTransmission->getIdRedacteur()->getId() === $currentUser->getId()) {
            $canTransmit = false;
        }

        if ($latestUserTransmission) {
            $latestUserTransmissionId = $latestUserTransmission->getId();
            $latestUserTransmissionCanModify = !$latestUserTransmission->isAccuseReception();
        }

        return [$canTransmit, $latestUserTransmissionId, $latestUserTransmissionCanModify];
    }
}
