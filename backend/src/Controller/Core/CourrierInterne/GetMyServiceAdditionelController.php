<?php

namespace App\Controller\Core\CourrierInterne;

use App\Entity\Cour\CourrierInterne;
use App\Entity\Core\TypeCourrier;
use App\Entity\Core\User;
use App\Repository\Cour\CourrierInterneRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'CourrierInterne')]
class GetMyServiceAdditionelController extends AbstractController
{
    public function __construct(
        private CourrierInterneRepository $courrierInterneRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private TransmissionRepository $transmissionRepository,
        private TransmissionReponseRepository $transmissionReponseRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/courrier-interne/my-service-additionel', name: 'app_core_courrier_interne_get_my_service_additionel', methods: ['GET'])]
    #[OA\Get(
        path: '/core/courrier-interne/my-service-additionel',
        summary: 'Lister les courriers internes crees par le service additionel de l\'utilisateur connecte',
        description: 'Retourne uniquement les courriers internes crees par le service additionel de l\'utilisateur connecte (redacteur).',
        tags: ['CourrierInterne'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'order_by',
                in: 'query',
                required: false,
                description: 'Ordre de tri (createdAt)',
                schema: new OA\Schema(type: 'string', enum: ['DESC', 'ASC'], default: 'DESC'),
                example: 'DESC'
            ),
            new OA\Parameter(
                name: 'start_date',
                in: 'query',
                required: false,
                description: 'Date de debut du filtre (format: YYYY-MM-DD). a utiliser avec end_date pour un intervalle.',
                schema: new OA\Schema(type: 'string', format: 'date'),
                example: '2025-01-01'
            ),
            new OA\Parameter(
                name: 'end_date',
                in: 'query',
                required: false,
                description: 'Date de fin du filtre (format: YYYY-MM-DD). a utiliser avec start_date.',
                schema: new OA\Schema(type: 'string', format: 'date'),
                example: '2025-12-31'
            ),
            new OA\Parameter(
                name: 'date',
                in: 'query',
                required: false,
                description: 'Filtrer sur une date specifique (format: YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date'),
                example: '2025-12-17'
            ),
            new OA\Parameter(
                name: 'priorite',
                in: 'query',
                required: false,
                description: 'Filtrer par priorite du courrier',
                schema: new OA\Schema(type: 'string', enum: ['Toutes', 'Basse', 'Normal', 'Haute']),
                example: 'Haute'
            ),
            new OA\Parameter(
                name: 'categorie',
                in: 'query',
                required: false,
                description: 'Filtrer par ID de categorie du correspondant',
                schema: new OA\Schema(type: 'integer'),
                example: 5
            ),
            new OA\Parameter(
                name: 'type_courrier',
                in: 'query',
                required: false,
                description: 'Filtrer par ID de type de courrier',
                schema: new OA\Schema(type: 'integer'),
                example: 12
            ),
            new OA\Parameter(
                name: 'classe_courrier',
                in: 'query',
                required: false,
                description: 'Filtrer par classe du courrier (ex: Urgent, Normal, Confidentiel)',
                schema: new OA\Schema(type: 'string'),
                example: 'Urgent'
            ),
            new OA\Parameter(
                name: 'type_transmission',
                in: 'query',
                required: false,
                description: 'Filtrer par type de transmission (ex: Electronique, Physique, Courrier)',
                schema: new OA\Schema(type: 'string'),
                example: 'Electronique'
            ),
            new OA\Parameter(
                name: 'courrier',
                in: 'query',
                required: false,
                description: 'Filtrer par ID de courrier specifique',
                schema: new OA\Schema(type: 'integer'),
                example: 150
            ),
            new OA\Parameter(
                name: 'provenance',
                in: 'query',
                required: false,
                description: 'Filtrer par ID de provenance (correspondant)',
                schema: new OA\Schema(type: 'integer'),
                example: 8
            ),
            new OA\Parameter(
                name: 'service_destinataire',
                in: 'query',
                required: false,
                description: 'Filtrer par ID de service destinataire specifique (remplace le filtre automatique)',
                schema: new OA\Schema(type: 'integer'),
                example: 25
            ),
            new OA\Parameter(
                name: 'service_destinataire_reponse_liee',
                in: 'query',
                required: false,
                description: 'Filtrer par ID du service destinataire de la derniere transmission des courriers internes lies',
                schema: new OA\Schema(type: 'integer'),
                example: 283
            ),
            new OA\Parameter(
                name: 'statut_reponse_liee',
                in: 'query',
                required: false,
                description: 'Filtrer par statut de la derniere transmission des courriers internes lies',
                schema: new OA\Schema(type: 'string'),
                example: 'Recu'
            ),
            new OA\Parameter(
                name: 'redacteur',
                in: 'query',
                required: false,
                description: 'Filtrer par ID du redacteur (utilisateur)',
                schema: new OA\Schema(type: 'integer'),
                example: 10
            ),
            new OA\Parameter(
                name: 'type_reponse',
                in: 'query',
                required: false,
                description: 'Filtrer par ID de type de reponse',
                schema: new OA\Schema(type: 'integer'),
                example: 3
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Numero de la page (pagination)',
                schema: new OA\Schema(type: 'integer', default: 1),
                example: 1
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: "Nombre d'elements par page. Utiliser 0 pour tout recuperer.",
                schema: new OA\Schema(type: 'integer', default: 10),
                example: 10
            ),
            new OA\Parameter(
                name: 'is_delete',
                in: 'query',
                required: false,
                description: 'Inclure les courriers internes supprimes (soft delete)',
                schema: new OA\Schema(type: 'boolean', default: false),
                example: false
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Recherche textuelle sur les donnees du courrier interne retourne',
                schema: new OA\Schema(type: 'string'),
                example: 'demande conge'
            ),
            new OA\Parameter(
                name: 'year',
                in: 'query',
                required: false,
                description: 'Filtrer par annee de la date de reponse',
                schema: new OA\Schema(type: 'integer'),
                example: 2025
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Courriers internes récupérés avec succés.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function __invoke(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetMyCourrierInterne');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['code' => 401, 'message' => 'Accès non autorisé.'], 401);
        }

        $servicesAdditionel = $currentUser->getServicesAdditionel();
        $serviceIdsAdditionel = [];
        if (is_array($servicesAdditionel)) {
            foreach ($servicesAdditionel as $service) {
                if (is_array($service) && isset($service['serviceId'])) {
                    $serviceIdsAdditionel[] = (int) $service['serviceId'];
                } elseif (is_array($service) && isset($service['id'])) {
                    $serviceIdsAdditionel[] = (int) $service['id'];
                } elseif (is_int($service) || is_numeric($service)) {
                    $serviceIdsAdditionel[] = (int) $service;
                }
            }
        }
        $serviceIdsAdditionel = array_values(array_unique(array_filter(
            $serviceIdsAdditionel,
            static fn ($id) => $id > 0
        )));

        $filters = [
            'order_by' => $request->query->get('order_by', 'DESC'),
            'start_date' => $request->query->get('start_date'),
            'end_date' => $request->query->get('end_date'),
            'date' => $request->query->get('date'),
            'priorite' => $request->query->get('priorite'),
            'categorie' => $request->query->get('categorie'),
            'type_courrier' => $request->query->get('type_courrier'),
            'classe_courrier' => $request->query->get('classe_courrier'),
            'type_transmission' => $request->query->get('type_transmission'),
            'courrier' => $request->query->get('courrier'),
            'provenance' => $request->query->get('provenance'),
            'service_destinataire' => $request->query->get('service_destinataire'),
            'service_destinataire_reponse_liee' => $request->query->get('service_destinataire_reponse_liee'),
            'statut_reponse_liee' => $request->query->get('statut_reponse_liee'),
            'redacteur' => $request->query->get('redacteur'),
            'type_reponse' => $request->query->get('type_reponse'),
            'is_delete' => filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN),
            'search' => $request->query->get('search'),
            'year' => $request->query->get('year'),
            'page' => max(1, (int) $request->query->get('page', 1)),
            'limit' => (int) $request->query->get('limit', 10),
        ];

        $orderBy = strtoupper((string) $filters['order_by']);
        if (!in_array($orderBy, ['ASC', 'DESC'], true)) {
            $orderBy = 'DESC';
        }
        $filters['order_by'] = $orderBy;

        $page = $filters['page'];
        $limit = $filters['limit'];
        if (empty($serviceIdsAdditionel)) {
            $this->actionLogger->logView(
                'CourrierInterne',
                null,
                'Consultation des courriers internes crees par le service additionel de l\'utilisateur connecte',
                [
                    'userId' => $currentUser->getId(),
                    'username' => $currentUser->getUserIdentifier(),
                    'page' => $page,
                    'limit' => $limit,
                    'total' => 0,
                    'search' => $filters['search'] ?: null,
                    'filters' => $filters,
                    'service_additionel_ids' => $serviceIdsAdditionel,
                ]
            );

            return $this->json([
                'page' => $page,
                'limit' => $limit,
                'total' => 0,
                'data' => [],
            ], 200);
        }

        $queryBuilder = $this->courrierInterneRepository->createQueryBuilder('ci')
            ->leftJoin('ci.idServiceDestinataire', 'sd')
            ->leftJoin('ci.idRedacteur', 'rd')
            ->leftJoin('rd.idService', 'rs')
            ->leftJoin('ci.typeReponse', 'tr')
            ->addSelect('sd', 'rd', 'rs', 'tr')
            ->where('rs.id IN (:serviceIdsAdditionel)')
            ->andWhere('ci.isDelete = :isDelete')
            ->setParameter('serviceIdsAdditionel', $serviceIdsAdditionel)
            ->setParameter('isDelete', $filters['is_delete']);

        $applyFilters = function ($qb) use ($filters) {
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $qb->andWhere('ci.dateReponse >= :start AND ci.dateReponse <= :end')
                    ->setParameter('start', $filters['start_date'] . ' 00:00:00')
                    ->setParameter('end', $filters['end_date'] . ' 23:59:59');
            } elseif (!empty($filters['start_date'])) {
                $qb->andWhere('ci.dateReponse >= :startOnly')
                    ->setParameter('startOnly', $filters['start_date'] . ' 00:00:00');
            } elseif (!empty($filters['end_date'])) {
                $qb->andWhere('ci.dateReponse >= :endDateStart AND ci.dateReponse <= :endDateEnd')
                    ->setParameter('endDateStart', $filters['end_date'] . ' 00:00:00')
                    ->setParameter('endDateEnd', $filters['end_date'] . ' 23:59:59');
            } elseif (!empty($filters['date'])) {
                $qb->andWhere('ci.dateReponse >= :dateStart AND ci.dateReponse <= :dateEnd')
                    ->setParameter('dateStart', $filters['date'] . ' 00:00:00')
                    ->setParameter('dateEnd', $filters['date'] . ' 23:59:59');
            } elseif (!empty($filters['year'])) {
                $year = (int) $filters['year'];
                if ($year >= 1900 && $year <= 2100) {
                    $startOfYear = (new \DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
                    $endOfYear = (new \DateTimeImmutable())->setDate($year, 12, 31)->setTime(23, 59, 59);
                    $qb->andWhere('ci.dateReponse >= :yearStart AND ci.dateReponse <= :yearEnd')
                        ->setParameter('yearStart', $startOfYear)
                        ->setParameter('yearEnd', $endOfYear);
                }
            }

            if (!empty($filters['classe_courrier'])) {
                $qb->andWhere('LOWER(ci.classeCourrier) LIKE LOWER(:classeCourrier)')
                    ->setParameter('classeCourrier', '%' . trim($filters['classe_courrier']) . '%');
            }
            if (!empty($filters['type_transmission'])) {
                $qb->andWhere('LOWER(ci.typeTransmission) LIKE LOWER(:typeTransmission)')
                    ->setParameter('typeTransmission', '%' . trim($filters['type_transmission']) . '%');
            }
            if (!empty($filters['type_reponse'])) {
                $qb->andWhere('tr.id = :typeReponse')
                    ->setParameter('typeReponse', $filters['type_reponse']);
            }
            if (!empty($filters['service_destinataire'])) {
                $qb->andWhere('ci.idServiceDestinataire = :filterServiceDestinataire')
                    ->setParameter('filterServiceDestinataire', $filters['service_destinataire']);
            }
            if (!empty($filters['redacteur'])) {
                $qb->andWhere('ci.idRedacteur = :filterRedacteur')
                    ->setParameter('filterRedacteur', $filters['redacteur']);
            }

            return $qb;
        };

        $applyFilters($queryBuilder);

        $queryBuilder->orderBy('ci.createdAt', $filters['order_by']);

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
        $prioriteFilter = is_string($filters['priorite'] ?? null) ? trim($filters['priorite']) : null;
        if ($prioriteFilter === '' || $prioriteFilter === 'Toutes') {
            $prioriteFilter = null;
        }
        $categorieFilter = (is_numeric($filters['categorie'] ?? null) && (int) $filters['categorie'] > 0)
            ? (int) $filters['categorie']
            : null;
        $courrierFilter = (is_numeric($filters['courrier'] ?? null) && (int) $filters['courrier'] > 0)
            ? (int) $filters['courrier']
            : null;
        $provenanceFilter = (is_numeric($filters['provenance'] ?? null) && (int) $filters['provenance'] > 0)
            ? (int) $filters['provenance']
            : null;

        $requiresPostFiltering = $typeCourrierFilter !== null
            || $linkedServiceDestinataireFilter !== null
            || $linkedStatutFilterNormalized !== null
            || $searchFilterNormalized !== null
            || $prioriteFilter !== null
            || $categorieFilter !== null
            || $courrierFilter !== null
            || $provenanceFilter !== null;

        if ($requiresPostFiltering) {
            $courrierInternes = $queryBuilder->getQuery()->getResult();
        } else {
            $total = (clone $queryBuilder)->select('COUNT(ci.id)')->getQuery()->getSingleScalarResult();
            if ($limit > 0) {
                $queryBuilder->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
            }
            $courrierInternes = $queryBuilder->getQuery()->getResult();
        }

        $courrierInterneIds = [];
        $allTransmissionIds = [];
        $allTypeIds = [];

        foreach ($courrierInternes as $courrierInterne) {
            $courrierInterneIds[] = $courrierInterne->getId();

            $transmissionIds = $courrierInterne->getIdTransmission();
            if (is_array($transmissionIds)) {
                foreach ($transmissionIds as $transmissionId) {
                    if (is_numeric($transmissionId)) {
                        $allTransmissionIds[] = (int) $transmissionId;
                    }
                }
            }

            $typeIds = $courrierInterne->getTypesCourrierIds();
            if (is_array($typeIds)) {
                foreach ($typeIds as $typeId) {
                    if (is_numeric($typeId)) {
                        $allTypeIds[] = (int) $typeId;
                    }
                }
            }
        }

        $allTransmissionIds = array_values(array_unique($allTransmissionIds));
        $allTypeIds = array_values(array_unique($allTypeIds));

        $transmissionsById = [];
        if (!empty($allTransmissionIds)) {
            $transmissions = $this->transmissionRepository->createQueryBuilder('t')
                ->leftJoin('t.idCourrier', 'c')
                ->leftJoin('c.typeCourrier', 'tc')
                ->leftJoin('c.idProvenance', 'prov')
                ->leftJoin('prov.categories', 'cat')
                ->addSelect('c', 'tc', 'prov', 'cat')
                ->where('t.id IN (:ids)')
                ->setParameter('ids', $allTransmissionIds)
                ->getQuery()
                ->getResult();

            foreach ($transmissions as $transmission) {
                $transmissionsById[$transmission->getId()] = $transmission;
            }
        }

        $typesById = [];
        if (!empty($allTypeIds)) {
            $types = $this->entityManager->getRepository(TypeCourrier::class)->findBy(['id' => $allTypeIds]);
            foreach ($types as $type) {
                if (!$type->isDelete()) {
                    $typesById[$type->getId()] = $type;
                }
            }
        }

        $piecesByParent = [];
        if (!empty($courrierInterneIds)) {
            $pieces = $this->pieceJointeRepository->createQueryBuilder('p')
                ->where('p.typeParent = :typeParent')
                ->andWhere('p.isDelete = :isDelete')
                ->andWhere('p.idParent IN (:ids)')
                ->setParameter('typeParent', 'CourrierInterne')
                ->setParameter('isDelete', false)
                ->setParameter('ids', $courrierInterneIds)
                ->getQuery()
                ->getResult();

            foreach ($pieces as $piece) {
                $parentId = $piece->getIdParent();
                if (!$parentId) {
                    continue;
                }
                $piecesByParent[$parentId][] = [
                    'id' => $piece->getId(),
                    'nom' => $piece->getNom(),
                    'chemin' => $piece->getChemin(),
                    'type' => $piece->getType(),
                ];
            }
        }

        $rows = array_map(function (CourrierInterne $courrierInterne) use ($transmissionsById, $typesById, $piecesByParent): array {
            $courriers = [];
            $courrierEntities = [];
            $seenCourrierIds = [];
            $transmissionIds = $courrierInterne->getIdTransmission();
            if (is_array($transmissionIds)) {
                foreach ($transmissionIds as $transmissionId) {
                    if (!is_numeric($transmissionId)) {
                        continue;
                    }
                    $transmission = $transmissionsById[(int) $transmissionId] ?? null;
                    $courrier = $transmission?->getIdCourrier();
                    if (!$courrier) {
                        continue;
                    }
                    $courrierId = $courrier->getId();
                    if ($courrierId === null || isset($seenCourrierIds[$courrierId])) {
                        continue;
                    }
                    $seenCourrierIds[$courrierId] = true;
                    $courrierEntities[] = $courrier;

                    $courriers[] = [
                        'id' => $courrierId,
                        'numero' => $courrier->getNumero(),
                        'reference' => $courrier->getNumero(),
                        'objet' => $courrier->getObjet(),
                        'dateArrivee' => $courrier->getDateArrivee()?->format('Y-m-d'),
                        'dateEnregistrement' => $courrier->getDateEnregistrement()?->format('Y-m-d'),
                        'typeCourrier' => $courrier->getTypeCourrier()?->getNom(),
                        'provenance' => $courrier->getIdProvenance()?->getNom(),
                        'categorie' => $courrier->getIdProvenance() && $courrier->getIdProvenance()->getCategories()->count() > 0
                            ? $courrier->getIdProvenance()->getCategories()->first()->getNom()
                            : null,
                        'priorite' => $courrier->getPriorite(),
                    ];
                }
            }

            $typesCourrier = [];
            $typeIds = $courrierInterne->getTypesCourrierIds();
            if (is_array($typeIds)) {
                foreach ($typeIds as $typeId) {
                    if (!is_numeric($typeId)) {
                        continue;
                    }
                    $type = $typesById[(int) $typeId] ?? null;
                    if ($type) {
                        $typesCourrier[] = [
                            'id' => $type->getId(),
                            'nom' => $type->getNom(),
                            'type' => $type->getType(),
                        ];
                    }
                }
            }

            return [
                'entity' => $courrierInterne,
                'courriers' => $courriers,
                'courrierEntities' => $courrierEntities,
                'typesCourrier' => $typesCourrier,
                'pieces' => $piecesByParent[$courrierInterne->getId()] ?? [],
            ];
        }, $courrierInternes);

        if ($requiresPostFiltering) {
            $matchesPostFilters = function (array $row) use (
                $typeCourrierFilter,
                $linkedServiceDestinataireFilter,
                $linkedStatutFilterNormalized,
                $searchFilterNormalized,
                $prioriteFilter,
                $categorieFilter,
                $courrierFilter,
                $provenanceFilter
            ): bool {
                /** @var CourrierInterne $courrierInterne */
                $courrierInterne = $row['entity'];
                $courrierEntities = $row['courrierEntities'] ?? [];
                $typesCourrier = $row['typesCourrier'] ?? [];
                $pieces = $row['pieces'] ?? [];

                if ($typeCourrierFilter !== null) {
                    $hasTypeCourrier = false;
                    foreach ($courrierEntities as $courrier) {
                        $typeCourrier = $courrier->getTypeCourrier();
                        if ($typeCourrier && $typeCourrier->getId() === $typeCourrierFilter) {
                            $hasTypeCourrier = true;
                            break;
                        }
                    }

                    if (!$hasTypeCourrier) {
                        $typesCourrierIds = $courrierInterne->getTypesCourrierIds();
                        if (is_array($typesCourrierIds)) {
                            foreach ($typesCourrierIds as $typeId) {
                                if ((int) $typeId === $typeCourrierFilter) {
                                    $hasTypeCourrier = true;
                                    break;
                                }
                            }
                        }
                    }

                    if (!$hasTypeCourrier) {
                        return false;
                    }
                }

                if ($courrierFilter !== null) {
                    $hasCourrier = false;
                    foreach ($courrierEntities as $courrier) {
                        if ($courrier->getId() === $courrierFilter) {
                            $hasCourrier = true;
                            break;
                        }
                    }
                    if (!$hasCourrier) {
                        return false;
                    }
                }

                if ($provenanceFilter !== null) {
                    $hasProvenance = false;
                    foreach ($courrierEntities as $courrier) {
                        $provenance = $courrier->getIdProvenance();
                        if ($provenance && $provenance->getId() === $provenanceFilter) {
                            $hasProvenance = true;
                            break;
                        }
                    }
                    if (!$hasProvenance) {
                        return false;
                    }
                }

                if ($categorieFilter !== null) {
                    $hasCategorie = false;
                    foreach ($courrierEntities as $courrier) {
                        $provenance = $courrier->getIdProvenance();
                        if (!$provenance) {
                            continue;
                        }
                        foreach ($provenance->getCategories() as $categorie) {
                            if ($categorie->getId() === $categorieFilter) {
                                $hasCategorie = true;
                                break 2;
                            }
                        }
                    }
                    if (!$hasCategorie) {
                        return false;
                    }
                }

                if ($prioriteFilter !== null) {
                    $hasPriorite = false;
                    foreach ($courrierEntities as $courrier) {
                        if ($courrier->getPriorite() === $prioriteFilter) {
                            $hasPriorite = true;
                            break;
                        }
                    }
                    if (!$hasPriorite && $courrierInterne->getPriorite() === $prioriteFilter) {
                        $hasPriorite = true;
                    }
                    if (!$hasPriorite) {
                        return false;
                    }
                }

                if ($linkedServiceDestinataireFilter !== null || $linkedStatutFilterNormalized !== null) {
                    $linkedIds = is_array($courrierInterne->getIdReponses()) ? $courrierInterne->getIdReponses() : [];
                    if (empty($linkedIds)) {
                        $linkedIds = [$courrierInterne->getId()];
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

                    $pushSearchValue($searchValues, $courrierInterne->getId());
                    $pushSearchValue($searchValues, $courrierInterne->getObjet());
                    $pushSearchValue($searchValues, $courrierInterne->getCommentairePublic());
                    $pushSearchValue($searchValues, $courrierInterne->getCommentaireInterne());
                    $pushSearchValue($searchValues, $courrierInterne->getClasseCourrier());
                    $pushSearchValue($searchValues, $courrierInterne->getTypeTransmission());
                    $pushSearchValue($searchValues, $courrierInterne->getPriorite());
                    $pushSearchValue($searchValues, $courrierInterne->getStatut());
                    $pushSearchValue($searchValues, $courrierInterne->isAccuseReception());
                    $pushSearchValue($searchValues, $courrierInterne->isinstance());
                    $pushSearchValue($searchValues, $courrierInterne->isGeled());
                    $pushSearchValue($searchValues, $courrierInterne->getDateReponse()?->format('Y-m-d'));
                    $pushSearchValue($searchValues, $courrierInterne->getNombrePieceJointe());
                    $pushSearchValue($searchValues, $courrierInterne->getCreatedAt()?->format('Y-m-d'));

                    $serviceDestinataire = $courrierInterne->getIdServiceDestinataire();
                    if ($serviceDestinataire) {
                        $pushSearchValue($searchValues, $serviceDestinataire->getId());
                        $pushSearchValue($searchValues, $serviceDestinataire->getNom());
                        $pushSearchValue($searchValues, $serviceDestinataire->getSigle());
                    }

                    $redacteur = $courrierInterne->getIdRedacteur();
                    if ($redacteur) {
                        $pushSearchValue($searchValues, $redacteur->getId());
                        $pushSearchValue($searchValues, $redacteur->getFullName());
                        $pushSearchValue($searchValues, $redacteur->getEmail());
                    }

                    if (is_array($courrierInterne->getIdTransmission())) {
                        foreach ($courrierInterne->getIdTransmission() as $idTransmission) {
                            $pushSearchValue($searchValues, $idTransmission);
                        }
                    }

                    if (is_array($courrierInterne->getTypesCourrierIds())) {
                        foreach ($courrierInterne->getTypesCourrierIds() as $typeId) {
                            $pushSearchValue($searchValues, $typeId);
                        }
                    }
                    foreach ($typesCourrier as $typeCourrier) {
                        $pushSearchValue($searchValues, $typeCourrier['id'] ?? null);
                        $pushSearchValue($searchValues, $typeCourrier['nom'] ?? null);
                        $pushSearchValue($searchValues, $typeCourrier['type'] ?? null);
                    }

                    $linkedIdsForSearch = is_array($courrierInterne->getIdReponses()) ? $courrierInterne->getIdReponses() : [];
                    if (empty($linkedIdsForSearch)) {
                        $linkedIdsForSearch = [$courrierInterne->getId()];
                    }
                    foreach ($linkedIdsForSearch as $reponseId) {
                        $pushSearchValue($searchValues, $reponseId);
                        if (!is_numeric($reponseId)) {
                            continue;
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

                    foreach ($pieces as $pieceJointe) {
                        $pushSearchValue($searchValues, $pieceJointe['id'] ?? null);
                        $pushSearchValue($searchValues, $pieceJointe['nom'] ?? null);
                        $pushSearchValue($searchValues, $pieceJointe['chemin'] ?? null);
                        $pushSearchValue($searchValues, $pieceJointe['type'] ?? null);
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
            };

            $rows = array_values(array_filter($rows, $matchesPostFilters));
            $total = count($rows);

            if ($limit > 0) {
                $offset = ($page - 1) * $limit;
                $rows = array_slice($rows, $offset, $limit);
            }
        }

        $data = array_map(function (array $row): array {
            /** @var CourrierInterne $courrierInterne */
            $courrierInterne = $row['entity'];
            $serviceDestinataire = $courrierInterne->getIdServiceDestinataire();
            $redacteur = $courrierInterne->getIdRedacteur();
            $serviceRedacteur = $redacteur?->getIdService();

            $courrierInterneLiees = [];
            $linkedIds = is_array($courrierInterne->getIdReponses()) ? $courrierInterne->getIdReponses() : [];
            if (empty($linkedIds)) {
                $linkedIds = [$courrierInterne->getId()];
            }
            foreach ($linkedIds as $reponseId) {
                if (!is_numeric($reponseId)) {
                    continue;
                }
                $lastTransmission = $this->transmissionReponseRepository->findLatestByCourrierInterneId((int) $reponseId);
                $courrierInterneLiees[] = [
                    'id' => (int) $reponseId,
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

            return [
                'id' => $courrierInterne->getId(),
                'objet' => $courrierInterne->getObjet(),
                'commentairePublic' => $courrierInterne->getCommentairePublic(),
                'commentaireInterne' => $courrierInterne->getCommentaireInterne(),
                'classeCourrier' => $courrierInterne->getClasseCourrier(),
                'typeTransmission' => $courrierInterne->getTypeTransmission(),
                'priorite' => $courrierInterne->getPriorite(),
                'statut' => $courrierInterne->getStatut(),
                'numero'=> $courrierInterne->getNumero(),
                'accuseReception' => $courrierInterne->isAccuseReception(),
                'isinstance' => $courrierInterne->isinstance(),
                'is_geled' => $courrierInterne->isGeled(),
                'dateReponse' => $courrierInterne->getDateReponse()?->format('Y-m-d H:i:s'),
                'createdAt' => $courrierInterne->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $courrierInterne->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'courriers' => $row['courriers'],
                'typesCourrier' => $row['typesCourrier'],
                'idTransmission' => $courrierInterne->getIdTransmission(),
                'idReponses' => $courrierInterne->getIdReponses(),
                'courrierInterneLiees' => $courrierInterneLiees,
                'serviceDestinataire' => $serviceDestinataire ? [
                    'id' => $serviceDestinataire->getId(),
                    'nom' => $serviceDestinataire->getNom(),
                    'sigle' => $serviceDestinataire->getSigle(),
                ] : null,
                'redacteur' => $redacteur ? [
                    'id' => $redacteur->getId(),
                    'fullName' => $redacteur->getFullName(),
                    'email' => $redacteur->getEmail(),
                    'service' => $serviceRedacteur ? [
                        'id' => $serviceRedacteur->getId(),
                        'nom' => $serviceRedacteur->getNom(),
                        'sigle' => $serviceRedacteur->getSigle(),
                    ] : null,
                ] : null,
                'nombrePieceJointe' => $courrierInterne->getNombrePieceJointe(),
                'piecesJointes' => $row['pieces'],
            ];
        }, $rows);

        $this->actionLogger->logView(
            'CourrierInterne',
            null,
            'Consultation des courriers internes crees par le service additionel de l\'utilisateur connecte',
            [
                'userId' => $currentUser->getId(),
                'username' => $currentUser->getUserIdentifier(),
                'page' => $page,
                'limit' => $limit,
                'total' => (int) $total,
                'search' => $filters['search'] ?: null,
                'filters' => $filters,
                'service_additionel_ids' => $serviceIdsAdditionel,
            ]
        );

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => (int) $total,
            'data' => $data,
        ], 200);
    }
}


