<?php

namespace App\Controller\Core\statistique;

use App\Entity\Core\Service;
use App\Entity\Core\User;
use App\Repository\Core\CorrespondantRepository;
use App\Repository\Cour\CourrierDepartRepository;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: "Statistics")]
class ServiceDashboardStatisticsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
        private CourrierRepository $courrierRepository,
        private CourrierDepartRepository $courrierDepartRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private TransmissionRepository $transmissionRepository,
        private CorrespondantRepository $correspondantRepository,
    ) {
    }

    #[Route('/core/statistics/service-dashboard', name: 'app_core_statistics_service_dashboard', methods: ['GET'])]
    #[OA\Get(
        path: '/core/statistics/service-dashboard',
        summary: 'Statistiques dashboard par service connecte',
        description: 'Retourne les statistiques du service de l utilisateur connecte (et ses services enfants), avec filtres et pagination.',
        tags: ['Statistics'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Date de debut (YYYY-MM-DD) sur dateEnregistrement', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Date de fin (YYYY-MM-DD) sur dateEnregistrement', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'priorite', in: 'query', required: false, description: 'Filtre de priorite des courriers arrives', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_confidentiel', in: 'query', required: false, description: 'Filtre de confidentialite (true/false, confidentiel/non_confidentiel)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Recherche globale sur toutes les donnees retournees par cette API', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'all_services', in: 'query', required: false, description: 'Si true, calcule les statistiques sur tous les services, avec pagination (page, limit)', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'service_id', in: 'query', required: false, description: 'ID d un service dans le scope du service connecte (ou plusieurs séparés par des virgules)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type_courrier', in: 'query', required: false, description: 'ID(s) du type de courrier (plusieurs séparés par des virgules)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page pour les sections listees', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Taille de page pour les sections listees', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Statistiques recuperees avec succes'),
            new OA\Response(response: 400, description: 'Parametres invalides'),
            new OA\Response(response: 401, description: 'Non autorise'),
            new OA\Response(response: 403, description: 'Service hors scope'),
        ]
    )]
    public function __invoke(Request $request): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json([
                'code' => 401,
                'message' => 'Utilisateur non authentifie.',
            ], 401);
        }

        $this->accessChecker->checker($currentUser, $this->isGranted('ROLE_USER'), 'GetDashboardStatistics');

        try {
            $startDate = $this->parseDate($request->query->get('start_date'), 'start_date');
            $endDate = $this->parseDate($request->query->get('end_date'), 'end_date');
            $prioriteParam = $request->query->get('priorite');
            if ($prioriteParam === null) {
                $prioriteParam = $request->query->get('priority');
            }
            if (is_array($prioriteParam)) {
                $prioriteParam = reset($prioriteParam) ?: null;
            }
            $priorite = trim((string) ($prioriteParam ?? ''));
            $isConfidentiel = $this->parseConfidentialite($request->query->get('is_confidentiel'));
            $allServices = $this->parseBooleanQuery($request->query->get('all_services'), false);

            if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
                return $this->json([
                    'code' => 400,
                    'message' => 'start_date ne peut pas etre superieure a end_date.',
                ], 400);
            }
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'code' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }

        // Récupération des services (tableau)
        $serviceIdsParam = $request->query->get('service_id');
        $selectedServiceIds = [];
        if ($serviceIdsParam !== null && $serviceIdsParam !== '') {
            $ids = array_map('trim', explode(',', $serviceIdsParam));
            foreach ($ids as $id) {
                if (is_numeric($id) && (int) $id > 0) {
                    $selectedServiceIds[] = (int) $id;
                }
            }
            if (empty($selectedServiceIds)) {
                return $this->json([
                    'code' => 400,
                    'message' => 'service_id doit être un ou plusieurs entiers positifs séparés par des virgules.',
                ], 400);
            }
        }

        // Récupération des types de courrier (tableau)
        $typeCourrierParam = $request->query->get('type_courrier');
        $selectedTypeCourrierIds = [];
        if ($typeCourrierParam !== null && $typeCourrierParam !== '') {
            $ids = array_map('trim', explode(',', $typeCourrierParam));
            foreach ($ids as $id) {
                if (is_numeric($id) && (int) $id > 0) {
                    $selectedTypeCourrierIds[] = (int) $id;
                }
            }
            if (empty($selectedTypeCourrierIds)) {
                return $this->json([
                    'code' => 400,
                    'message' => 'type_courrier doit être un ou plusieurs entiers positifs séparés par des virgules.',
                ], 400);
            }
        }

        $requestedPage = max(1, (int) $request->query->get('page', 1));
        $requestedLimit = max(1, (int) $request->query->get('limit', 10));
        $page = $requestedPage;
        $limit = $requestedLimit;

        $userService = $currentUser->getIdService();
        if (!$userService instanceof Service) {
            return $this->json([
                'code' => 400,
                'message' => 'Aucun service associe a l utilisateur connecte.',
            ], 400);
        }
        $userServiceId = $userService->getId();
        if ($userServiceId === null) {
            return $this->json([
                'code' => 400,
                'message' => 'Service utilisateur invalide.',
            ], 400);
        }

        $scopeServices = $allServices ? $this->getAllActiveServices() : $this->getServiceScope($userService);
        $scopeServiceIds = array_values(array_map(
            static fn (Service $service) => (int) $service->getId(),
            array_filter($scopeServices, static fn (Service $service) => $service->getId() !== null)
        ));

        // Vérification que les services sélectionnés sont dans le scope
        if (!$allServices && !empty($selectedServiceIds)) {
            foreach ($selectedServiceIds as $id) {
                if (!in_array($id, $scopeServiceIds, true)) {
                    return $this->json([
                        'code' => 403,
                        'message' => 'Un des services sélectionnés est hors du scope du service connecte.',
                    ], 403);
                }
            }
        }

        // Détermination des serviceIds actifs pour les requêtes
        if ($allServices) {
            $activeServiceIds = !empty($selectedServiceIds) ? $selectedServiceIds : $scopeServiceIds;
        } elseif (!empty($selectedServiceIds)) {
            $activeServiceIds = $selectedServiceIds;
        } else {
            $activeServiceIds = [(int) $userServiceId];
        }

        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'priorite' => $priorite !== '' ? $priorite : null,
            'is_confidentiel' => $isConfidentiel,
            'order_by' => $request->query->get('order_by', 'DESC'),
            'date' => $request->query->get('date'),
            'categorie' => $request->query->get('categorie'),
            'type_courrier' => $selectedTypeCourrierIds,
            'classe_courrier' => $request->query->get('classe_courrier'),
            'provenance' => $request->query->get('provenance'),
            'service_traitant' => $request->query->get('service_traitant'),
            'createur' => $request->query->get('createur'),
            'statut' => $request->query->get('statut'),
            'year' => $request->query->get('year'),
            'has_courrier_depart' => $request->query->has('has_courrier_depart')
                ? filter_var($request->query->get('has_courrier_depart'), FILTER_VALIDATE_BOOLEAN)
                : null,
            'is_delete' => filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN),
            'isarchive' => filter_var($request->query->get('isarchive', false), FILTER_VALIDATE_BOOLEAN),
            'id_destinataire' => $request->query->get('id_destinataire'),
            'service_id' => $selectedServiceIds,
            'all_services' => $allServices,
        ];
        $search = trim((string) $request->query->get('search', ''));

        $filters['restrict_to_current_user'] = $this->isNoFilterModeForCurrentUserScope($filters, $search, $allServices, $selectedServiceIds);
        $filters['current_user'] = $currentUser;

        $repartitionPriorite = $this->getRepartitionParPriorite($activeServiceIds, $filters);
        $repartitionStatut = $this->getRepartitionParStatut($activeServiceIds, $filters);
        $topPostes = $this->getTopPostes($activeServiceIds, $filters);

        $childrenServices = $allServices
            ? $this->getAllActiveServices()
            : $this->getDirectChildrenServices($userService);
        $servicesChildrenData = array_map(
            static fn (Service $service) => [
                'id' => $service->getId(),
                'nom' => $service->getNom(),
                'sigle' => $service->getSigle(),
                'typeService' => $service->getTypeService(),
                'idServiceParent' => $service->getIdServiceParent()?->getId(),
            ],
            $childrenServices
        );

        if ($search !== '') {
            $servicesChildrenData = $this->applyGlobalSearch($servicesChildrenData, $search);

            if ($allServices) {
                try {
                    $courriersArrivesCollection = $this->getCourriersArriveCollectionAllServices($filters, $search, $page, $limit);
                } catch (\Throwable $e) {
                    $this->actionLogger->logView('ServiceDashboardStatistics', null, 'Erreur getCourriersArriveCollectionAllServices', ['exception' => $e->getMessage(), 'trace' => substr($e->getTraceAsString(), 0, 1000), 'search' => $search]);
                    $courriersArrivesCollection = ['total' => 0, 'data' => []];
                }
                try {
                    $courriersDepartCollection = $this->getCourriersDepartCollectionAllServices($filters, $search, $page, $limit);
                } catch (\Throwable $e) {
                    $this->actionLogger->logView('ServiceDashboardStatistics', null, 'Erreur getCourriersDepartCollectionAllServices', ['exception' => $e->getMessage(), 'trace' => substr($e->getTraceAsString(), 0, 1000), 'search' => $search]);
                    $courriersDepartCollection = ['total' => 0, 'data' => []];
                }

                $totalCourriersArrive = $courriersArrivesCollection['total'];
                $totalCourriersDepart = $courriersDepartCollection['total'];

                $courriersArrivesSection = $this->formatDetailedSection(
                    $totalCourriersArrive,
                    $courriersArrivesCollection['data'],
                    $page,
                    $limit
                );
                $courriersDepartSection = $this->formatDetailedSection(
                    $totalCourriersDepart,
                    $courriersDepartCollection['data'],
                    $page,
                    $limit
                );
            } else {
                $courriersArrivesData = $this->applyGlobalSearch(
                    $this->getCourriersArriveData($activeServiceIds, $filters, null, null),
                    $search
                );
                $courriersDepartData = $this->applyGlobalSearch(
                    $this->getCourriersDepartData($activeServiceIds, $filters, null, null),
                    $search
                );

                $totalCourriersArrive = count($courriersArrivesData);
                $totalCourriersDepart = count($courriersDepartData);

                $courriersArrivesSection = $this->paginateList($courriersArrivesData, $page, $limit);
                $courriersDepartSection = $this->paginateList($courriersDepartData, $page, $limit);
            }

            $transmissionsData = $this->applyGlobalSearch(
                $this->getTransmissionsData($activeServiceIds, $filters, null, null),
                $search
            );
            $reponsesData = $this->applyGlobalSearch(
                $this->getReponsesData($activeServiceIds, $filters, null, null),
                $search
            );

            $totalTransmissions = count($transmissionsData);
            $totalReponses = count($reponsesData);

            $transmissionsSection = $this->paginateList($transmissionsData, $page, $limit);
            $reponsesSection = $this->paginateList($reponsesData, $page, $limit);

            $repartitionPriorite = $this->applyGlobalSearch($repartitionPriorite, $search);
            $repartitionStatut = $this->applyGlobalSearch($repartitionStatut, $search);
            $topPostes = $this->applyGlobalSearch($topPostes, $search);
        } else {
            if ($allServices) {
                try {
                    $courriersArrivesCollection = $this->getCourriersArriveCollectionAllServices($filters, '', $page, $limit);
                } catch (\Throwable $e) {
                    $courriersArrivesCollection = ['total' => 0, 'data' => []];
                }
                try {
                    $courriersDepartCollection = $this->getCourriersDepartCollectionAllServices($filters, '', $page, $limit);
                } catch (\Throwable $e) {
                    $courriersDepartCollection = ['total' => 0, 'data' => []];
                }

                $totalCourriersArrive = $courriersArrivesCollection['total'];
                $totalCourriersDepart = $courriersDepartCollection['total'];

                $courriersArrivesSection = $this->formatDetailedSection(
                    $totalCourriersArrive,
                    $courriersArrivesCollection['data'],
                    $page,
                    $limit
                );
                $courriersDepartSection = $this->formatDetailedSection(
                    $totalCourriersDepart,
                    $courriersDepartCollection['data'],
                    $page,
                    $limit
                );
            } else {
                $totalCourriersArrive = $this->countCourriersArrive($activeServiceIds, $filters);
                $totalCourriersDepart = $this->countCourriersDepart($activeServiceIds, $filters);

                if ($limit === 0) {
                    $courriersArrivesData = $this->getCourriersArriveData($activeServiceIds, $filters, null, null);
                    $courriersDepartData = $this->getCourriersDepartData($activeServiceIds, $filters, null, null);
                } else {
                    $courriersArrivesData = $this->getCourriersArriveData($activeServiceIds, $filters, $page, $limit);
                    $courriersDepartData = $this->getCourriersDepartData($activeServiceIds, $filters, $page, $limit);
                }

                $courriersArrivesSection = $this->formatDetailedSection(
                    $totalCourriersArrive,
                    $courriersArrivesData,
                    $page,
                    $limit
                );
                $courriersDepartSection = $this->formatDetailedSection(
                    $totalCourriersDepart,
                    $courriersDepartData,
                    $page,
                    $limit
                );
            }

            $totalTransmissions = $this->countTransmissions($activeServiceIds, $filters);
            $totalReponses = $this->countReponses($activeServiceIds, $filters);

            if ($limit === 0) {
                $transmissionsData = $this->getTransmissionsData($activeServiceIds, $filters, null, null);
                $reponsesData = $this->getReponsesData($activeServiceIds, $filters, null, null);
            } else {
                $transmissionsData = $this->getTransmissionsData($activeServiceIds, $filters, $page, $limit);
                $reponsesData = $this->getReponsesData($activeServiceIds, $filters, $page, $limit);
            }

            $transmissionsSection = $this->formatDetailedSection($totalTransmissions, $transmissionsData, $page, $limit);
            $reponsesSection = $this->formatDetailedSection($totalReponses, $reponsesData, $page, $limit);

            // Agrégations par service -> types (nombre de courriers) pour arrivées et départs.
            // Si aucun service n'est fourni, on prend le scope actif ;
            // si aucun type n'est fourni, on prend tous les types de courrier disponibles.
            $aggregationServiceIds = !empty($selectedServiceIds) ? $selectedServiceIds : $activeServiceIds;
            $aggregationTypeCourrierIds = !empty($selectedTypeCourrierIds)
                ? $selectedTypeCourrierIds
                : $this->getAllTypeCourrierIds();

            $courriersArrivesParServiceType = [];
            $courriersDepartParServiceType = [];
            if (!empty($aggregationServiceIds)) {
                $courriersArrivesParServiceType = $this->paginateServiceTypeAggregation(
                    $this->getCountsByServiceAndTypeForArrive($aggregationServiceIds, $aggregationTypeCourrierIds, $filters),
                    $page,
                    $limit
                );
                $courriersDepartParServiceType = $this->paginateServiceTypeAggregation(
                    $this->getCountsByServiceAndTypeForDepart($aggregationServiceIds, $aggregationTypeCourrierIds, $filters),
                    $page,
                    $limit
                );
            }

            // Agrégations mensuelles par service pour une année donnée (par défaut année en filtres ou année courante)
            $courrierArriveeParAnnee = [];
            $courrierDepartParAnnee = [];
            $yearForMonthly = (int) ($filters['year'] ?? (new \DateTimeImmutable())->format('Y'));
            if (!empty($aggregationServiceIds)) {
                $courrierArriveeParAnnee = $this->getMonthlyCountsByServiceForArrive($aggregationServiceIds, $filters, $yearForMonthly);
                $courrierDepartParAnnee = $this->getMonthlyCountsByServiceForDepart($aggregationServiceIds, $filters, $yearForMonthly);

                if (!empty($courrierArriveeParAnnee['courrierArrivee']['services'] ?? [])) {
                    $paginatedMonthlyArriveServices = $this->paginateList($courrierArriveeParAnnee['courrierArrivee']['services'], $page, $limit);
                    $courrierArriveeParAnnee['courrierArrivee']['page'] = $paginatedMonthlyArriveServices['page'];
                    $courrierArriveeParAnnee['courrierArrivee']['limit'] = $paginatedMonthlyArriveServices['limit'];
                    $courrierArriveeParAnnee['courrierArrivee']['total'] = $paginatedMonthlyArriveServices['total'];
                    $courrierArriveeParAnnee['courrierArrivee']['services'] = $paginatedMonthlyArriveServices['data'];
                }

                if (!empty($courrierDepartParAnnee['courrierDepart']['services'] ?? [])) {
                    $paginatedMonthlyDepartServices = $this->paginateList($courrierDepartParAnnee['courrierDepart']['services'], $page, $limit);
                    $courrierDepartParAnnee['courrierDepart']['page'] = $paginatedMonthlyDepartServices['page'];
                    $courrierDepartParAnnee['courrierDepart']['limit'] = $paginatedMonthlyDepartServices['limit'];
                    $courrierDepartParAnnee['courrierDepart']['total'] = $paginatedMonthlyDepartServices['total'];
                    $courrierDepartParAnnee['courrierDepart']['services'] = $paginatedMonthlyDepartServices['data'];
                }
            }
        }

        $responseData = [
            'filtres' => [
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
                'priorite' => $filters['priorite'],
                'is_confidentiel' => $filters['is_confidentiel'],
                'search' => $search !== '' ? $search : null,
                'all_services' => $allServices,
                'service_id' => !empty($selectedServiceIds) ? implode(',', $selectedServiceIds) : null,
                'type_courrier' => !empty($selectedTypeCourrierIds) ? implode(',', $selectedTypeCourrierIds) : null,
            ],
            'scope' => [
                'serviceConnecte' => [
                    'id' => $userService->getId(),
                    'nom' => $userService->getNom(),
                    'sigle' => $userService->getSigle(),
                ],
                'mode' => $allServices ? 'all_services' : 'service_scope',
            ],
            'servicesEnfants' => $this->paginateList($servicesChildrenData, $page, $limit),
            'courriersArrives' => $courriersArrivesSection,
            'transmissions' => $transmissionsSection,
            'courriersDepart' => $courriersDepartSection,
            'reponses' => $reponsesSection,
            'repartitionCourriersArrivesParPriorite' => $this->formatGroupedSection($repartitionPriorite, $page, $limit, 'nombreCourriers'),
            'repartitionCourriersArrivesParStatut' => $this->formatGroupedSection($repartitionStatut, $page, $limit, 'nombreCourriers'),
            'top10PostesCourriersArrives' => $this->formatGroupedSection(array_slice($topPostes, 0, 10), $page, $limit, 'nombreCourriers'),
            'courriersArrivesParServiceType' => $courriersArrivesParServiceType ?? [],
            'courriersDepartParServiceType' => $courriersDepartParServiceType ?? [],
            'courrierArriveeParAnnee' => $courrierArriveeParAnnee ?? [],
            'courrierDepartParAnnee' => $courrierDepartParAnnee ?? [],
        ];

        $this->actionLogger->logView(
            'ServiceDashboardStatistics',
            null,
            'Consultation des statistiques par service connecte',
            [
                'filters' => $responseData['filtres'],
                'scope' => $responseData['scope'],
                'totaux' => [
                    'courriersArrives' => $totalCourriersArrive,
                    'transmissions' => $totalTransmissions,
                    'courriersDepart' => $totalCourriersDepart,
                    'reponses' => $totalReponses,
                ],
            ]
        );

        return $this->json([
            'code' => 200,
            'message' => 'Statistiques recuperees avec succes.',
            'data' => $responseData,
        ], 200);
    }

    private function countCourriersArrive(array $serviceIds, array $filters): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from('App\Entity\Cour\Courrier', 'c');

        $restrictToCurrentUser = !empty($filters['restrict_to_current_user']);
        if ($restrictToCurrentUser) {
            $currentUser = $filters['current_user'] ?? null;

            // Sans aucun filtre: uniquement les courriers arrivÃ©s non supprimÃ©s, crÃ©Ã©s par l'utilisateur connectÃ©.
            // On ignore le scope service (idServiceTraitant) dans ce mode.
            $this->applyCourrierFilters($qb, 'c', $serviceIds, $filters, true, false, true);

            if ($currentUser instanceof User) {
                $qb->andWhere('c.idCreateur = :dashboardCreateur')
                    ->setParameter('dashboardCreateur', $currentUser);
            }
        } else {
            $this->applyCourrierFilters($qb, 'c', $serviceIds, $filters);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function countTransmissions(array $serviceIds, array $filters): int
    {
        $isAllServicesMode = !empty($filters['all_services']);
        $isArchive = (bool) ($filters['isarchive'] ?? false);
        $isDelete = $isAllServicesMode ? false : (bool) ($filters['is_delete'] ?? false);
        $restrictToCurrentUser = false;
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from('App\Entity\Cour\Transmission', 't')
            ->leftJoin('t.idCourrier', 'c')
            ->where('1 = 1');

        // Filtre sur les services destinataires
        if (!empty($filters['service_id'])) {
            $qb->andWhere('t.idServiceDestinataire IN (:serviceIds)')
                ->setParameter('serviceIds', $filters['service_id']);
        } elseif (!$isAllServicesMode) {
            $qb->andWhere('t.idServiceDestinataire IN (:serviceIds)')
                ->setParameter('serviceIds', $serviceIds);
        }

        // Filtre type_courrier (tableau)
        if (!empty($filters['type_courrier'])) {
            $qb->andWhere('c.typeCourrier IN (:typeCourrier)')
                ->setParameter('typeCourrier', $filters['type_courrier']);
        }

        $qb->andWhere('t.isDelete = :transmissionDeleted')
            ->setParameter('transmissionDeleted', $isDelete);
        $qb->andWhere('t.isArchive = :transmissionArchived')
            ->setParameter('transmissionArchived', $isArchive);

        if ($restrictToCurrentUser) {
            $currentUser = $filters['current_user'] ?? null;
            if ($currentUser instanceof User) {
                $qb->andWhere('t.idEmetteur = :dashboardEmetteur')
                    ->setParameter('dashboardEmetteur', $currentUser);
            }
        }

        // Pour les transmissions, on n'applique pas le filtre sur le service traitant du courrier
        if (!$isAllServicesMode) {
            $this->applyCourrierFilters($qb, 'c', $serviceIds, $filters, false, false, true, true, 'dateArrivee');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function countCourriersDepart(array $serviceIds, array $filters): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(cd.id)')
            ->from('App\Entity\Cour\CourrierDepart', 'cd')
            ->innerJoin('cd.idCourrier', 'c')
            ->where('cd.isDelete = :courrierDepartDeleted')
            ->andWhere('cd.isArchive = :courrierDepartArchived')
            ->setParameter('courrierDepartDeleted', false)
            ->setParameter('courrierDepartArchived', false);

        $this->applyCourrierFilters($qb, 'c', $serviceIds, $filters, false);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function countReponses(array $serviceIds, array $filters): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT r.id)')
            ->from('App\Entity\Cour\Reponse', 'r')
            ->leftJoin('r.courriers', 'c')
            ->where('r.isDelete = :reponseDeleted')
            ->setParameter('reponseDeleted', false);

        // Filtre sur les services destinataires
        if (!empty($filters['service_id'])) {
            $qb->andWhere('r.idServiceDestinataire IN (:serviceIds)')
                ->setParameter('serviceIds', $filters['service_id']);
        } elseif (empty($filters['all_services'])) {
            $qb->andWhere('r.idServiceDestinataire IN (:serviceIds)')
                ->setParameter('serviceIds', $serviceIds);
        }

        // Filtre type_courrier (tableau)
        if (!empty($filters['type_courrier'])) {
            $qb->andWhere('c.typeCourrier IN (:typeCourrier)')
                ->setParameter('typeCourrier', $filters['type_courrier']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCourriersArriveData(array $serviceIds, array $filters, ?int $page, ?int $limit): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('c, s, tc, prov, u')
            ->from('App\Entity\Cour\Courrier', 'c')
            ->leftJoin('c.idServiceTraitant', 's')
            ->leftJoin('c.typeCourrier', 'tc')
            ->leftJoin('c.idProvenance', 'prov')
            ->leftJoin('c.idCreateur', 'u')
            ->orderBy('c.dateEnregistrement', 'DESC')
            ->addOrderBy('c.id', 'DESC');

        $restrictToCurrentUser = !empty($filters['restrict_to_current_user']);
        if ($restrictToCurrentUser) {
            $currentUser = $filters['current_user'] ?? null;

            $this->applyCourrierFilters($qb, 'c', $serviceIds, $filters, true, false, true);

            if ($currentUser instanceof User) {
                $qb->andWhere('c.idCreateur = :dashboardCreateurData')
                    ->setParameter('dashboardCreateurData', $currentUser);
            }
        } else {
            $this->applyCourrierFilters($qb, 'c', $serviceIds, $filters);
        }
        if ($page !== null && $limit !== null) {
            $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        }

        $rows = $qb->getQuery()->getResult();

        return array_map(static function ($courrier): array {
            return [
                'id' => $courrier->getId(),
                'numero' => $courrier->getNumero(),
                'objet' => $courrier->getObjet(),
                'reference' => $courrier->getReference(),
                'priorite' => $courrier->getPriorite(),
                'statut' => $courrier->getStatut(),
                'classeCourrier' => $courrier->getClasseCourrier(),
                'isConfidentiel' => $courrier->isConfidentiel(),
                'isGeled' => $courrier->isGeled(),
                'dateArrivee' => $courrier->getDateArrivee()?->format('Y-m-d'),
                'dateEnregistrement' => $courrier->getDateEnregistrement()?->format('Y-m-d'),
                'serviceTraitant' => $courrier->getIdServiceTraitant() ? [
                    'id' => $courrier->getIdServiceTraitant()->getId(),
                    'nom' => $courrier->getIdServiceTraitant()->getNom(),
                    'sigle' => $courrier->getIdServiceTraitant()->getSigle(),
                ] : null,
                'typeCourrier' => $courrier->getTypeCourrier() ? [
                    'id' => $courrier->getTypeCourrier()->getId(),
                    'nom' => $courrier->getTypeCourrier()->getNom(),
                ] : null,
                'provenance' => $courrier->getIdProvenance() ? [
                    'id' => $courrier->getIdProvenance()->getId(),
                    'nom' => $courrier->getIdProvenance()->getNom(),
                ] : null,
                'createur' => $courrier->getIdCreateur() ? [
                    'id' => $courrier->getIdCreateur()->getId(),
                    'fullName' => $courrier->getIdCreateur()->getFullName(),
                ] : null,
                'createdAt' => $courrier->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTransmissionsData(array $serviceIds, array $filters, ?int $page, ?int $limit): array
    {
        $isAllServicesMode = !empty($filters['all_services']);
        $restrictToCurrentUser = false;
        $isArchive = (bool) ($filters['isarchive'] ?? false);
        $isDelete = $isAllServicesMode ? false : (bool) ($filters['is_delete'] ?? false);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('t, c, sd, em')
            ->from('App\Entity\Cour\Transmission', 't')
            ->leftJoin('t.idCourrier', 'c')
            ->leftJoin('t.idServiceDestinataire', 'sd')
            ->leftJoin('t.idEmetteur', 'em')
            ->where('1 = 1')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC');

        // Filtre sur les services destinataires
        if (!empty($filters['service_id'])) {
            $qb->andWhere('t.idServiceDestinataire IN (:serviceIds)')
                ->setParameter('serviceIds', $filters['service_id']);
        } elseif (!$isAllServicesMode) {
            $qb->andWhere('t.idServiceDestinataire IN (:serviceIds)')
                ->setParameter('serviceIds', $serviceIds);
        }

        // Filtre type_courrier (tableau)
        if (!empty($filters['type_courrier'])) {
            $qb->andWhere('c.typeCourrier IN (:typeCourrier)')
                ->setParameter('typeCourrier', $filters['type_courrier']);
        }

        $qb->andWhere('t.isDelete = :transmissionDeletedData')
            ->setParameter('transmissionDeletedData', $isDelete);
        $qb->andWhere('t.isArchive = :transmissionArchivedData')
            ->setParameter('transmissionArchivedData', $isArchive);

        if ($restrictToCurrentUser) {
            $currentUser = $filters['current_user'] ?? null;
            if ($currentUser instanceof User) {
                $qb->andWhere('t.idEmetteur = :dashboardEmetteurData')
                    ->setParameter('dashboardEmetteurData', $currentUser);
            }
        }

        if (!$isAllServicesMode && !$restrictToCurrentUser) {
            $this->applyCourrierFilters($qb, 'c', $serviceIds, $filters, false, false, true, true, 'dateArrivee');
        }
        if ($page !== null && $limit !== null) {
            $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        }

        $rows = $qb->getQuery()->getResult();

        return array_map(static function ($transmission): array {
            return [
                'id' => $transmission->getId(),
                'statut' => $transmission->getStatut(),
                'typeTransfert' => $transmission->getTypeTransfert(),
                'instruction' => $transmission->getInstruction(),
                'dateInstruction' => $transmission->getDateInstruction()?->format('Y-m-d H:i:s'),
                'dateReception' => $transmission->getDateReception()?->format('Y-m-d H:i:s'),
                'accuseReception' => $transmission->isAccuseReception(),
                'courrier' => $transmission->getIdCourrier() ? [
                    'id' => $transmission->getIdCourrier()->getId(),
                    'numero' => $transmission->getIdCourrier()->getNumero(),
                    'objet' => $transmission->getIdCourrier()->getObjet(),
                    'dateEnregistrement' => $transmission->getIdCourrier()->getDateEnregistrement()?->format('Y-m-d'),
                ] : null,
                'serviceDestinataire' => $transmission->getIdServiceDestinataire() ? [
                    'id' => $transmission->getIdServiceDestinataire()->getId(),
                    'nom' => $transmission->getIdServiceDestinataire()->getNom(),
                    'sigle' => $transmission->getIdServiceDestinataire()->getSigle(),
                ] : null,
                'emetteur' => $transmission->getIdEmetteur() ? [
                    'id' => $transmission->getIdEmetteur()->getId(),
                    'fullName' => $transmission->getIdEmetteur()->getFullName(),
                ] : null,
                'createdAt' => $transmission->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCourriersDepartData(array $serviceIds, array $filters, ?int $page, ?int $limit): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('cd, c, d, s')
            ->from('App\Entity\Cour\CourrierDepart', 'cd')
            ->innerJoin('cd.idCourrier', 'c')
            ->leftJoin('cd.destinataire', 'd')
            ->leftJoin('cd.idSignataire', 's')
            ->where('cd.isDelete = :courrierDepartDeletedData')
            ->andWhere('cd.isArchive = :courrierDepartArchivedData')
            ->setParameter('courrierDepartDeletedData', false)
            ->setParameter('courrierDepartArchivedData', false)
            ->orderBy('cd.createdAt', 'DESC')
            ->addOrderBy('cd.id', 'DESC');

        $this->applyCourrierFilters($qb, 'c', $serviceIds, $filters, false);
        if ($page !== null && $limit !== null) {
            $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        }

        $rows = $qb->getQuery()->getResult();

        return array_map(static function ($courrierDepart): array {
            return [
                'id' => $courrierDepart->getId(),
                'numeroReference' => $courrierDepart->getNumeroReference(),
                'numeroActe' => $courrierDepart->getNumeroActe(),
                'typeCourrier' => $courrierDepart->getTypeCourrier(),
                'classeCourrier' => $courrierDepart->getClasseCourrier(),
                'dateSignature' => $courrierDepart->getDateSignature()?->format('Y-m-d'),
                'courrier' => $courrierDepart->getIdCourrier() ? [
                    'id' => $courrierDepart->getIdCourrier()->getId(),
                    'numero' => $courrierDepart->getIdCourrier()->getNumero(),
                    'objet' => $courrierDepart->getIdCourrier()->getObjet(),
                    'dateEnregistrement' => $courrierDepart->getIdCourrier()->getDateEnregistrement()?->format('Y-m-d'),
                ] : null,
                'destinataire' => $courrierDepart->getDestinataire() ? [
                    'id' => $courrierDepart->getDestinataire()->getId(),
                    'nom' => $courrierDepart->getDestinataire()->getNom(),
                ] : null,
                'signataire' => $courrierDepart->getIdSignataire() ? [
                    'id' => $courrierDepart->getIdSignataire()->getId(),
                    'fullName' => $courrierDepart->getIdSignataire()->getFullName(),
                ] : null,
                'createdAt' => $courrierDepart->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $rows);
    }

    /**
     * @return array{total: int, data: array<int, array<string, mixed>>}
     */
    private function getCourriersArriveCollectionAllServices(array $filters, string $search, int $page, int $limit): array
    {
        $isAllServicesMode = !empty($filters['all_services']);
        $isDelete = $isAllServicesMode ? false : (bool) ($filters['is_delete'] ?? false);
        $isArchive = (bool) ($filters['isarchive'] ?? false);
        $orderBy = $filters['order_by'] ?? 'DESC';
        $serviceIdFilter = $filters['service_id'] ?? null;
        $isConfidentiel = $filters['is_confidentiel'] ?? null;
        $startDate = $filters['start_date'] instanceof \DateTimeImmutable
            ? $filters['start_date']->format('Y-m-d')
            : $filters['start_date'];
        $endDate = $filters['end_date'] instanceof \DateTimeImmutable
            ? $filters['end_date']->format('Y-m-d')
            : $filters['end_date'];
        $date = $filters['date'] ?? null;
        $priorite = $filters['priorite'] ?? null;
        $categorie = $filters['categorie'] ?? null;
        $typeCourrier = $filters['type_courrier'] ?? null;
        $classeCourrier = $filters['classe_courrier'] ?? null;
        $provenance = $filters['provenance'] ?? null;
        $serviceTraitant = $filters['service_traitant'] ?? null;
        $createur = $filters['createur'] ?? null;
        $statut = $filters['statut'] ?? null;
        $year = $filters['year'] ?? null;
        $hasCourrierDepart = $filters['has_courrier_depart'] ?? null;

        $queryBuilder = $this->courrierRepository->createQueryBuilder('c')
            ->leftJoin('c.idServiceTraitant', 's')
            ->leftJoin('c.idCreateur', 'u')
            ->leftJoin('c.idProvenance', 'p')
            ->leftJoin('p.categories', 'cat')
            ->leftJoin('c.typeCourrier', 't')
            ->leftJoin('c.transmissions', 'trans')
            ->leftJoin('trans.idServiceDestinataire', 'servTrans')
            ->addSelect('s', 'u', 'p', 'cat', 't', 'trans', 'servTrans')
            ->where('c.isDelete = :isDelete')
            ->setParameter('isDelete', $isDelete)
            ->andWhere('c.isArchive = :isArchive')
            ->setParameter('isArchive', $isArchive);

        $this->applyCourrierArriveCollectionSearch(
            $queryBuilder,
            $search,
            'c',
            's',
            'u',
            'p',
            'cat',
            't',
            'trans',
            'servTrans',
            'search'
        );

        // Dates
        if (!empty($startDate) && !empty($endDate)) {
            $queryBuilder->andWhere('c.dateArrivee >= :start AND c.dateArrivee <= :end')
                ->setParameter('start', $startDate . ' 00:00:00')
                ->setParameter('end', $endDate . ' 23:59:59');
        } elseif (!empty($date)) {
            $queryBuilder->andWhere('c.dateArrivee >= :dateStart AND c.dateArrivee <= :dateEnd')
                ->setParameter('dateStart', $date . ' 00:00:00')
                ->setParameter('dateEnd', $date . ' 23:59:59');
        } elseif (!empty($year)) {
            $yearInt = (int) $year;
            if ($yearInt >= 1900 && $yearInt <= 2100) {
                $startOfYear = (new \DateTimeImmutable())->setDate($yearInt, 1, 1)->setTime(0, 0, 0);
                $endOfYear = (new \DateTimeImmutable())->setDate($yearInt, 12, 31)->setTime(23, 59, 59);
                $queryBuilder->andWhere('c.dateArrivee >= :yearStart AND c.dateArrivee <= :yearEnd')
                    ->setParameter('yearStart', $startOfYear)
                    ->setParameter('yearEnd', $endOfYear);
            }
        }

        if (!empty($priorite) && $priorite !== 'Toutes') {
            $queryBuilder->andWhere('c.priorite = :priorite')
                ->setParameter('priorite', $priorite);
        }
        if (!empty($categorie)) {
            $queryBuilder->andWhere('cat.id = :categorie')
                ->setParameter('categorie', $categorie);
        }
        // Filtre type_courrier (tableau)
        if (!empty($typeCourrier)) {
            $queryBuilder->andWhere('t.id IN (:type)')
                ->setParameter('type', $typeCourrier);
        }
        if (!empty($classeCourrier)) {
            $queryBuilder->andWhere('LOWER(c.classeCourrier) LIKE LOWER(:classeCourrier)')
                ->setParameter('classeCourrier', '%' . trim((string) $classeCourrier) . '%');
        }
        if (!empty($provenance)) {
            $queryBuilder->andWhere('p.id = :provenance')
                ->setParameter('provenance', $provenance);
        }
        // Filtre service_id (tableau)
        if (!empty($serviceIdFilter)) {
            $queryBuilder->andWhere('s.id IN (:serviceIdFilter)')
                ->setParameter('serviceIdFilter', $serviceIdFilter);
        }
        if ($isConfidentiel !== null) {
            $queryBuilder->andWhere('c.isConfidentiel = :isConfidentiel')
                ->setParameter('isConfidentiel', $isConfidentiel);
        }
        if (!empty($serviceTraitant)) {
            $subQuery = $this->courrierRepository->createQueryBuilder('c2')
                ->select('MAX(t2.id)')
                ->leftJoin('c2.transmissions', 't2')
                ->where('c2.id = c.id')
                ->andWhere('t2.isDelete = false')
                ->getDQL();

            $queryBuilder->andWhere(
                $queryBuilder->expr()->exists(
                    $this->courrierRepository->createQueryBuilder('c3')
                        ->select('1')
                        ->leftJoin('c3.transmissions', 't3')
                        ->where('c3.id = c.id')
                        ->andWhere('t3.id = (' . $subQuery . ')')
                        ->andWhere('t3.idServiceDestinataire = :serviceTraitant')
                        ->getDQL()
                )
            );
            $queryBuilder->setParameter('serviceTraitant', $serviceTraitant);
        }
        if (!empty($createur)) {
            $queryBuilder->andWhere('u.id = :createur')
                ->setParameter('createur', $createur);
        }
        if (!empty($statut)) {
            $queryBuilder->andWhere('c.statut = :statut')
                ->setParameter('statut', $statut);
        }
        if ($hasCourrierDepart !== null) {
            if ($hasCourrierDepart === true) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->exists(
                        $this->courrierRepository->createQueryBuilder('c_dep')
                            ->select('1')
                            ->from('App\Entity\Cour\CourrierDepart', 'cd')
                            ->where('cd.idCourrier = c.id')
                            ->getDQL()
                    )
                );
            } else {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->not(
                        $queryBuilder->expr()->exists(
                            $this->courrierRepository->createQueryBuilder('c_dep')
                                ->select('1')
                                ->from('App\Entity\Cour\CourrierDepart', 'cd')
                                ->where('cd.idCourrier = c.id')
                                ->getDQL()
                        )
                    )
                );
            }
        }

        $queryBuilder->orderBy('c.createdAt', $orderBy);

        $total = (int) (clone $queryBuilder)
            ->select('COUNT(DISTINCT c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }

        $results = $queryBuilder->getQuery()->getResult();

        // Pagination supplémentaire si nécessaire (pour combler les lacunes dues aux jointures)
        if ($limit > 0 && count($results) < $limit) {
            $idsDeja = array_map(static fn ($c) => $c->getId(), $results);
            $qbArrivee = $this->courrierRepository->createQueryBuilder('c2')
                ->leftJoin('c2.idServiceTraitant', 's2')
                ->leftJoin('c2.idCreateur', 'u2')
                ->leftJoin('c2.idProvenance', 'p2')
                ->leftJoin('p2.categories', 'cat2')
                ->leftJoin('c2.typeCourrier', 't2')
                ->leftJoin('c2.transmissions', 'trans2')
                ->leftJoin('trans2.idServiceDestinataire', 'servTrans2')
                ->addSelect('s2', 'u2', 'p2', 'cat2', 't2', 'trans2', 'servTrans2')
                ->where('c2.isDelete = :isDelete2')
                ->andWhere('c2.isArchive = :isArchive2')
                ->setParameter('isDelete2', $isDelete)
                ->setParameter('isArchive2', $isArchive);

            $this->applyCourrierArriveCollectionSearch($qbArrivee, $search, 'c2', 's2', 'u2', 'p2', 'cat2', 't2', 'trans2', 'servTrans2', 'search2');
            // On réapplique les mêmes filtres
            if (!empty($startDate) && !empty($endDate)) {
                $qbArrivee->andWhere('c2.dateArrivee >= :start2 AND c2.dateArrivee <= :end2')
                    ->setParameter('start2', $startDate . ' 00:00:00')
                    ->setParameter('end2', $endDate . ' 23:59:59');
            } elseif (!empty($date)) {
                $qbArrivee->andWhere('c2.dateArrivee >= :dateStart2 AND c2.dateArrivee <= :dateEnd2')
                    ->setParameter('dateStart2', $date . ' 00:00:00')
                    ->setParameter('dateEnd2', $date . ' 23:59:59');
            } elseif (!empty($year)) {
                $yearInt2 = (int) $year;
                if ($yearInt2 >= 1900 && $yearInt2 <= 2100) {
                    $startOfYear2 = (new \DateTimeImmutable())->setDate($yearInt2, 1, 1)->setTime(0, 0, 0);
                    $endOfYear2 = (new \DateTimeImmutable())->setDate($yearInt2, 12, 31)->setTime(23, 59, 59);
                    $qbArrivee->andWhere('c2.dateArrivee >= :yearStart2 AND c2.dateArrivee <= :yearEnd2')
                        ->setParameter('yearStart2', $startOfYear2)
                        ->setParameter('yearEnd2', $endOfYear2);
                }
            }
            if (!empty($priorite) && $priorite !== 'Toutes') {
                $qbArrivee->andWhere('c2.priorite = :priorite2')
                    ->setParameter('priorite2', $priorite);
            }
            if (!empty($categorie)) {
                $qbArrivee->andWhere('cat2.id = :categorie2')
                    ->setParameter('categorie2', $categorie);
            }
            if (!empty($typeCourrier)) {
                $qbArrivee->andWhere('t2.id IN (:type2)')
                    ->setParameter('type2', $typeCourrier);
            }
            if (!empty($classeCourrier)) {
                $qbArrivee->andWhere('LOWER(c2.classeCourrier) LIKE LOWER(:classeCourrier2)')
                    ->setParameter('classeCourrier2', '%' . trim((string) $classeCourrier) . '%');
            }
            if (!empty($provenance)) {
                $qbArrivee->andWhere('p2.id = :provenance2')
                    ->setParameter('provenance2', $provenance);
            }
            if (!empty($serviceIdFilter)) {
                $qbArrivee->andWhere('s2.id IN (:serviceIdFilter2)')
                    ->setParameter('serviceIdFilter2', $serviceIdFilter);
            }
            if ($isConfidentiel !== null) {
                $qbArrivee->andWhere('c2.isConfidentiel = :isConfidentiel2')
                    ->setParameter('isConfidentiel2', $isConfidentiel);
            }
            if (!empty($serviceTraitant)) {
                $subQuery2 = $this->courrierRepository->createQueryBuilder('c22')
                    ->select('MAX(t22.id)')
                    ->leftJoin('c22.transmissions', 't22')
                    ->where('c22.id = c2.id')
                    ->andWhere('t22.isDelete = false')
                    ->getDQL();
                $qbArrivee->andWhere(
                    $qbArrivee->expr()->exists(
                        $this->courrierRepository->createQueryBuilder('c32')
                            ->select('1')
                            ->leftJoin('c32.transmissions', 't32')
                            ->where('c32.id = c2.id')
                            ->andWhere('t32.id = (' . $subQuery2 . ')')
                            ->andWhere('t32.idServiceDestinataire = :serviceTraitant2')
                            ->getDQL()
                    )
                );
                $qbArrivee->setParameter('serviceTraitant2', $serviceTraitant);
            }
            if (!empty($createur)) {
                $qbArrivee->andWhere('u2.id = :createur2')
                    ->setParameter('createur2', $createur);
            }
            if (!empty($statut)) {
                $qbArrivee->andWhere('c2.statut = :statut2')
                    ->setParameter('statut2', $statut);
            }
            if ($hasCourrierDepart !== null) {
                if ($hasCourrierDepart === true) {
                    $qbArrivee->andWhere(
                        $qbArrivee->expr()->exists(
                            $this->courrierRepository->createQueryBuilder('c_dep2')
                                ->select('1')
                                ->from('App\\Entity\\Cour\\CourrierDepart', 'cd2')
                                ->where('cd2.idCourrier = c2.id')
                                ->getDQL()
                        )
                    );
                } else {
                    $qbArrivee->andWhere(
                        $qbArrivee->expr()->not(
                            $qbArrivee->expr()->exists(
                                $this->courrierRepository->createQueryBuilder('c_dep2')
                                    ->select('1')
                                    ->from('App\\Entity\\Cour\\CourrierDepart', 'cd2')
                                    ->where('cd2.idCourrier = c2.id')
                                    ->getDQL()
                            )
                        )
                    );
                }
            }
            if (count($idsDeja) > 0) {
                $qbArrivee->andWhere($qbArrivee->expr()->notIn('c2.id', ':idsDeja'));
                $qbArrivee->setParameter('idsDeja', $idsDeja);
            }
            $qbArrivee->orderBy('c2.createdAt', $orderBy);
            $qbArrivee->setMaxResults($limit - count($results));
            $complement = $qbArrivee->getQuery()->getResult();
            $results = array_merge($results, $complement);
        }

        $data = array_map(function ($c): array {
            $derniereTransmission = null;
            if ($c->getTransmissions()->count() > 0) {
                $transmissions = $c->getTransmissions()->toArray();
                usort($transmissions, static fn ($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
                $derniereTransmission = $transmissions[0];
            }

            return [
                'id' => $c->getId(),
                'numero' => $c->getNumero(),
                'reference' => $c->getReference(),
                'objet' => $c->getObjet(),
                'priorite' => $c->getPriorite(),
                'statut' => $c->getStatut(),
                'nom' => $c->getNom(),
                'civilite' => $c->getCivilite(),
                'matricule' => $c->getMatricule(),
                'telephone' => $c->getTelephone(),
                'email' => $c->getEmail(),
                'adresse' => $c->getAdresse(),
                'commentaire' => $c->getCommentaire(),
                'commentairePublic' => $c->getCommentairePublic(),
                'commentaireInterne' => $c->getCommentaireInterne(),
                'typeTransfert' => $c->getTypeTransfert(),
                'classeCourrier' => $c->getClasseCourrier(),
                'categorie' => $c->getCategorie(),
                'nombrePieceJointe' => $c->getNombrePieceJointe(),
                'is_geled' => $c->isGeled(),
                'dateArrivee' => $c->getDateArrivee()?->format('Y-m-d H:i:s'),
                'dateEnregistrement' => $c->getDateEnregistrement()?->format('Y-m-d H:i:s'),
                'createdAt' => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $c->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'idServiceTraitant' => $c->getIdServiceTraitant()?->getNom(),
                'categorieProvenance' => $c->getIdProvenance() && $c->getIdProvenance()->getCategories()->count() > 0
                    ? $c->getIdProvenance()->getCategories()->first()->getNom()
                    : null,
                'typeCourrier' => $c->getTypeCourrier()?->getNom(),
                'idProvenance' => $c->getIdProvenance() ? [
                    'id' => $c->getIdProvenance()->getId(),
                    'nom' => $c->getIdProvenance()->getNom(),
                ] : null,
                'createur' => $c->getIdCreateur() ? [
                    'id' => $c->getIdCreateur()->getId(),
                    'nom' => $c->getIdCreateur()->getFullName(),
                ] : null,
                'isConfidentiel' => $c->isConfidentiel(),
                'isArchive' => $c->isArchive(),
                'hasCourrierDepart' => $c->getCourrierDeparts()->count() > 0,
                'statut_transmission' => $derniereTransmission?->getStatut(),
                'service_traitement' => $derniereTransmission && $derniereTransmission->getIdServiceDestinataire() ? [
                    'id' => $derniereTransmission->getIdServiceDestinataire()->getId(),
                    'nom' => $derniereTransmission->getIdServiceDestinataire()->getNom(),
                ] : null,
            ];
        }, $results);

        return [
            'total' => $total,
            'data' => $data,
        ];
    }

    /**
     * Retourne les comptes de courriers arrivés groupés par service (service traitant) puis par type de courrier.
     *
     * @param array<int> $serviceIds
     * @param array<int> $typeIds
     * @param array<string,mixed> $filters
     * @return array<int, array<string,mixed>>
     */
    private function getCountsByServiceAndTypeForArrive(array $serviceIds, array $typeIds, array $filters): array
    {
        try {
            $effectiveTypeIds = $typeIds;
            if (empty($effectiveTypeIds)) {
                $effectiveTypeIds = $this->getAllTypeCourrierIds();
            }

            // Préparer map des types pour libellés
            $typeMap = [];
            if (!empty($effectiveTypeIds)) {
                $types = $this->entityManager->createQueryBuilder()
                    ->select('t')
                    ->from('App\\Entity\\Core\\TypeCourrier', 't')
                    ->where('t.id IN (:typeIds)')
                    ->setParameter('typeIds', $effectiveTypeIds)
                    ->getQuery()
                    ->getResult();

                foreach ($types as $t) {
                    $typeMap[(int) $t->getId()] = $t->getNom();
                }
            }

            $localFilters = $filters;
            // Ne pas contraindre la requête par le filtre global type_courrier
            $localFilters['type_courrier'] = null;

            // Baser l'agrégation sur les transmissions : compter les courriers distincts traités
            // $qb = $this->entityManager->createQueryBuilder()
            //     ->select('sd.id AS serviceId, sd.nom AS serviceNom, tc.id AS typeId, tc.nom AS typeNom, COUNT(DISTINCT c.id) AS total')
            //     ->from('App\\Entity\\Cour\\Transmission', 't')
            //     ->leftJoin('t.idServiceDestinataire', 'sd')
            //     ->leftJoin('t.idCourrier', 'c')
            //     ->leftJoin('c.typeCourrier', 'tc')
            //     ->andWhere('sd.id IN (:serviceIds)')
            //     ->setParameter('serviceIds', $serviceIds)
            //     ->andWhere('(t.dateReception IS NOT NULL OR t.traitePar IS NOT NULL)')
            //     ->groupBy('sd.id, sd.nom, tc.id, tc.nom')
            //     ->orderBy('sd.nom', 'ASC');

            // if (!empty($effectiveTypeIds)) {
            //     $qb->andWhere('tc.id IN (:typeIds)')->setParameter('typeIds', $effectiveTypeIds);
            // }

            // // Appliquer les autres filtres pertinents sur le courrier (alias c)
            // $this->applyCourrierFilters($qb, 'c', $serviceIds, $localFilters);

            // Baser l'agrégation sur les transmissions : compter les courriers distincts transmis à ce service
// $qb = $this->entityManager->createQueryBuilder()
//     ->select('sd.id AS serviceId, sd.nom AS serviceNom, tc.id AS typeId, tc.nom AS typeNom, COUNT(DISTINCT c.id) AS total')
//     ->from('App\\Entity\\Cour\\Transmission', 't')
//     ->leftJoin('t.idServiceDestinataire', 'sd')
//     ->leftJoin('t.idCourrier', 'c')
//     ->leftJoin('c.typeCourrier', 'tc')
//     ->andWhere('sd.id IN (:serviceIds)')
//     ->setParameter('serviceIds', $serviceIds)

$qb = $this->entityManager->createQueryBuilder()
    ->select('sd.id AS serviceId, sd.nom AS serviceNom, tc.id AS typeId, tc.nom AS typeNom, COUNT(t.id) AS total')  // ⚠️ COUNT(t.id) au lieu de COUNT(DISTINCT c.id)
    ->from('App\\Entity\\Cour\\Transmission', 't')
    ->leftJoin('t.idServiceDestinataire', 'sd')
    ->leftJoin('t.idCourrier', 'c')
    ->leftJoin('c.typeCourrier', 'tc')
    ->andWhere('sd.id IN (:serviceIds)')    
    ->setParameter('serviceIds', $serviceIds)
    ->andWhere('t.isDelete = false')
    ->groupBy('sd.id, sd.nom, tc.id, tc.nom')
    ->orderBy('sd.nom', 'ASC');
    // ⚠️ retiré : la condition (t.dateReception IS NOT NULL OR t.traitePar IS NOT NULL)
    // excluait les transmissions "en attente de traitement" — elle ne correspond pas
    // à ta requête SQL de référence, qui compte toutes les transmissions vers le service.
    // ->groupBy('sd.id, sd.nom, tc.id, tc.nom')
    // ->orderBy('sd.nom', 'ASC');

if (!empty($effectiveTypeIds)) {
    $qb->andWhere('tc.id IN (:typeIds)')->setParameter('typeIds', $effectiveTypeIds);
}

// Appliquer les autres filtres pertinents sur le courrier (alias c)
// ⚠️ ignoreServiceTraitantFilter: true — le filtre de service est déjà porté par
// "sd.id IN (:serviceIds)" (le service DESTINATAIRE de la transmission).
// Ajouter en plus c.idServiceTraitant IN (:serviceIds) exigeait que le courrier soit
// AUSSI actuellement "traité par" ce même service, ce qui exclut à tort les courriers
// transmis au service 283 mais pas encore repris par lui (ou déjà repassés ailleurs).
$this->applyCourrierFilters(
    $qb,
    'c',
    $serviceIds,
    $localFilters,
    includeArchiveFilter: true,
    ignorePriorityFilter: false,
    ignoreServiceTraitantFilter: true,
);

            $rows = $qb->getQuery()->getArrayResult();

            // Préparer structure initiale avec tous les services et types (nombre = 0)
            $services = $this->entityManager->createQueryBuilder()
                ->select('s')
                ->from(Service::class, 's')
                ->where('s.id IN (:serviceIds2)')
                ->setParameter('serviceIds2', $serviceIds)
                ->orderBy('s.nom', 'ASC')
                ->getQuery()
                ->getResult();

            $result = [];
            foreach ($services as $s) {
                $sid = (int) $s->getId();
                $entry = [
                    'service' => ['id' => $sid, 'nom' => $s->getNom()],
                    'types' => [],
                    'total' => 0,
                ];

                foreach ($effectiveTypeIds as $tid) {
                    $entry['types'][] = [
                        'id' => (int) $tid,
                        'libelle' => $typeMap[(int) $tid] ?? null,
                        'nombre' => 0,
                    ];
                }

                $result[$sid] = $entry;
            }

            // Remplir avec les valeurs retournées
            foreach ($rows as $row) {
                $sid = (int) ($row['serviceId'] ?? 0);
                $tid = (int) ($row['typeId'] ?? 0);
                $total = (int) ($row['total'] ?? 0);

                if (!isset($result[$sid])) {
                    // créer entrée au besoin
                    $result[$sid] = [
                        'service' => ['id' => $sid, 'nom' => $row['serviceNom'] ?? null],
                        'types' => [],
                        'total' => 0,
                    ];
                    foreach ($effectiveTypeIds as $tidInit) {
                        $result[$sid]['types'][] = ['id' => (int) $tidInit, 'libelle' => $typeMap[(int) $tidInit] ?? null, 'nombre' => 0];
                    }
                }

                // trouver le type dans la liste et mettre à jour
                foreach ($result[$sid]['types'] as &$typeEntry) {
                    if ($typeEntry['id'] === $tid) {
                        $typeEntry['nombre'] = $total;
                        break;
                    }
                }
                unset($typeEntry);

                $result[$sid]['total'] += $total;
            }

            return array_values($result);
        } catch (\Throwable $e) {
            // éviter de propager l'erreur de query et retourner structure vide
            return [];
        }
    }

    /**
     * Retourne les comptes de courriers départ groupés par service (service traitant du courrier) puis par type de courrier.
     *
     * @param array<int> $serviceIds
     * @param array<int> $typeIds
     * @param array<string,mixed> $filters
     * @return array<int, array<string,mixed>>
     */
//     private function getCountsByServiceAndTypeForDepart(array $serviceIds, array $typeIds, array $filters): array
//     {
//         try {
//             $effectiveTypeIds = $typeIds;
//             if (empty($effectiveTypeIds)) {
//                 $effectiveTypeIds = $this->getAllTypeCourrierIds();
//             }

//             // Préparer map des types pour libellés
//             $typeMap = [];
//             if (!empty($effectiveTypeIds)) {
//                 $types = $this->entityManager->createQueryBuilder()
//                     ->select('t')
//                     ->from('App\\Entity\\Core\\TypeCourrier', 't')
//                     ->where('t.id IN (:typeIds)')
//                     ->setParameter('typeIds', $effectiveTypeIds)
//                     ->getQuery()
//                     ->getResult();

//                 foreach ($types as $t) {
//                     $typeMap[(int) $t->getId()] = $t->getNom();
//                 }
//             }

//             $localFilters = $filters;
//             $localFilters['type_courrier'] = null;

//             // // Baser l'agrégation sur les transmissions : compter les courriers distincts traités
//             // // Utilise le service destinataire des transmissions comme service de traitement
//             // $qb = $this->entityManager->createQueryBuilder()
//             //     ->select('sd.id AS serviceId, sd.nom AS serviceNom, tc.id AS typeId, tc.nom AS typeNom, COUNT(DISTINCT c.id) AS total')
//             //     ->from('App\\Entity\\Cour\\Transmission', 't')
//             //     ->leftJoin('t.idServiceDestinataire', 'sd')
//             //     ->leftJoin('t.idCourrier', 'c')
//             //     ->leftJoin('c.typeCourrier', 'tc')
//             //     ->andWhere('sd.id IN (:serviceIds)')
//             //     ->setParameter('serviceIds', $serviceIds)
//             //     ->andWhere('(t.dateReception IS NOT NULL OR t.traitePar IS NOT NULL)')
//             //     ->groupBy('sd.id, sd.nom, tc.id, tc.nom')
//             //     ->orderBy('sd.nom', 'ASC');

//             // if (!empty($effectiveTypeIds)) {
//             //     $qb->andWhere('tc.id IN (:typeIds)')->setParameter('typeIds', $effectiveTypeIds);
//             // }

//             // // Appliquer les filtres pertinents sur le courrier (alias c)
//             // $this->applyCourrierFilters($qb, 'c', $serviceIds, $localFilters, false);

//             // Baser l'agrégation sur les transmissions : compter les courriers distincts transmis à ce service
// $qb = $this->entityManager->createQueryBuilder()
//     ->select('sd.id AS serviceId, sd.nom AS serviceNom, tc.id AS typeId, tc.nom AS typeNom, COUNT(DISTINCT c.id) AS total')
//     ->from('App\\Entity\\Cour\\Transmission', 't')
//     ->leftJoin('t.idServiceDestinataire', 'sd')
//     ->leftJoin('t.idCourrier', 'c')
//     ->leftJoin('c.typeCourrier', 'tc')
//     ->andWhere('sd.id IN (:serviceIds)')
//     ->setParameter('serviceIds', $serviceIds)
//     // ⚠️ retiré : la condition (t.dateReception IS NOT NULL OR t.traitePar IS NOT NULL)
//     // excluait les transmissions "en attente de traitement" — elle ne correspond pas
//     // à ta requête SQL de référence, qui compte toutes les transmissions vers le service.
//     ->groupBy('sd.id, sd.nom, tc.id, tc.nom')
//     ->orderBy('sd.nom', 'ASC');

// if (!empty($effectiveTypeIds)) {
//     $qb->andWhere('tc.id IN (:typeIds)')->setParameter('typeIds', $effectiveTypeIds);
// }

// // Appliquer les autres filtres pertinents sur le courrier (alias c)
// // ⚠️ ignoreServiceTraitantFilter: true — le filtre de service est déjà porté par
// // "sd.id IN (:serviceIds)" (le service DESTINATAIRE de la transmission).
// // Ajouter en plus c.idServiceTraitant IN (:serviceIds) exigeait que le courrier soit
// // AUSSI actuellement "traité par" ce même service, ce qui exclut à tort les courriers
// // transmis au service 283 mais pas encore repris par lui (ou déjà repassés ailleurs).
// $this->applyCourrierFilters(
//     $qb,
//     'c',
//     $serviceIds,
//     $localFilters,
//     includeArchiveFilter: true,
//     ignorePriorityFilter: false,
//     ignoreServiceTraitantFilter: true,
// );

//             $rows = $qb->getQuery()->getArrayResult();

//             $services = $this->entityManager->createQueryBuilder()
//                 ->select('s')
//                 ->from(Service::class, 's')
//                 ->where('s.id IN (:serviceIds2)')
//                 ->setParameter('serviceIds2', $serviceIds)
//                 ->orderBy('s.nom', 'ASC')
//                 ->getQuery()
//                 ->getResult();

//             $result = [];
//             foreach ($services as $s) {
//                 $sid = (int) $s->getId();
//                 $entry = [
//                     'service' => ['id' => $sid, 'nom' => $s->getNom()],
//                     'types' => [],
//                     'total' => 0,
//                 ];

//                 foreach ($effectiveTypeIds as $tid) {
//                     $entry['types'][] = [
//                         'id' => (int) $tid,
//                         'libelle' => $typeMap[(int) $tid] ?? null,
//                         'nombre' => 0,
//                     ];
//                 }

//                 $result[$sid] = $entry;
//             }

//             foreach ($rows as $row) {
//                 $sid = (int) ($row['serviceId'] ?? 0);
//                 $tid = (int) ($row['typeId'] ?? 0);
//                 $total = (int) ($row['total'] ?? 0);

//                 if (!isset($result[$sid])) {
//                     $result[$sid] = [
//                         'service' => ['id' => $sid, 'nom' => $row['serviceNom'] ?? null],
//                         'types' => [],
//                         'total' => 0,
//                     ];
//                     foreach ($effectiveTypeIds as $tidInit) {
//                         $result[$sid]['types'][] = ['id' => (int) $tidInit, 'libelle' => $typeMap[(int) $tidInit] ?? null, 'nombre' => 0];
//                     }
//                 }

//                 foreach ($result[$sid]['types'] as &$typeEntry) {
//                     if ($typeEntry['id'] === $tid) {
//                         $typeEntry['nombre'] = $total;
//                         break;
//                     }
//                 }
//                 unset($typeEntry);

//                 $result[$sid]['total'] += $total;
//             }

//             return array_values($result);
//         } catch (\Throwable $e) {
//             return [];
//         }
//     }

private function getCountsByServiceAndTypeForDepart(array $serviceIds, array $typeIds, array $filters): array
{
    try {
        $effectiveTypeIds = $typeIds;
        if (empty($effectiveTypeIds)) {
            $effectiveTypeIds = $this->getAllTypeCourrierIds();
        }

        $typeMap = [];
        if (!empty($effectiveTypeIds)) {
            $types = $this->entityManager->createQueryBuilder()
                ->select('t')
                ->from('App\\Entity\\Core\\TypeCourrier', 't')
                ->where('t.id IN (:typeIds)')
                ->setParameter('typeIds', $effectiveTypeIds)
                ->getQuery()
                ->getResult();

            foreach ($types as $t) {
                $typeMap[(int) $t->getId()] = $t->getNom();
            }
        }

        // Baser l'agrégation sur CourrierDepart -> idCourrier (service traitant + type fiables)
        $qb = $this->entityManager->createQueryBuilder()
            // ->select('sd.id AS serviceId, sd.nom AS serviceNom, tc.id AS typeId, tc.nom AS typeNom, COUNT(DISTINCT cd.id) AS total')
            // ->from('App\\Entity\\Cour\\CourrierDepart', 'cd')
            ->select('sd.id AS serviceId, sd.nom AS serviceNom, tc.id AS typeId, tc.nom AS typeNom, COUNT(DISTINCT cd.id) AS total')
            ->from('App\\Entity\\Cour\\CourrierDepart', 'cd')
            ->innerJoin('cd.idCourrier', 'c')
            ->leftJoin('c.idServiceTraitant', 'sd')
            ->leftJoin('c.typeCourrier', 'tc')
            ->andWhere('sd.id IN (:serviceIds)')
            ->setParameter('serviceIds', $serviceIds)
            ->andWhere('cd.isDelete = false')
            ->groupBy('sd.id, sd.nom, tc.id, tc.nom')
            ->orderBy('sd.nom', 'ASC');

        if (!empty($effectiveTypeIds)) {
            $qb->andWhere('tc.id IN (:typeIds)')->setParameter('typeIds', $effectiveTypeIds);
        }

        $rows = $qb->getQuery()->getArrayResult();

        $services = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Service::class, 's')
            ->where('s.id IN (:serviceIds2)')
            ->setParameter('serviceIds2', $serviceIds)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($services as $s) {
            $sid = (int) $s->getId();
            $entry = ['service' => ['id' => $sid, 'nom' => $s->getNom()], 'types' => [], 'total' => 0];
            foreach ($effectiveTypeIds as $tid) {
                $entry['types'][] = ['id' => (int) $tid, 'libelle' => $typeMap[(int) $tid] ?? null, 'nombre' => 0];
            }
            $result[$sid] = $entry;
        }

        foreach ($rows as $row) {
            $sid = (int) ($row['serviceId'] ?? 0);
            $tid = (int) ($row['typeId'] ?? 0);
            $total = (int) ($row['total'] ?? 0);

            if (!isset($result[$sid])) {
                $result[$sid] = ['service' => ['id' => $sid, 'nom' => $row['serviceNom'] ?? null], 'types' => [], 'total' => 0];
                foreach ($effectiveTypeIds as $tidInit) {
                    $result[$sid]['types'][] = ['id' => (int) $tidInit, 'libelle' => $typeMap[(int) $tidInit] ?? null, 'nombre' => 0];
                }
            }

            foreach ($result[$sid]['types'] as &$typeEntry) {
                if ($typeEntry['id'] === $tid) {
                    $typeEntry['nombre'] = $total;
                    break;
                }
            }
            unset($typeEntry);

            $result[$sid]['total'] += $total;
        }

        return array_values($result);
    } catch (\Throwable $e) {
        return [];
    }
}

    private function getAllTypeCourrierIds(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('t.id')
            ->from('App\\Entity\\Core\\TypeCourrier', 't')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_unique(array_map(static fn ($row) => (int) ($row['id'] ?? 0), $rows)));
    }

    /**
     * Retourne les comptes mensuels de courriers arrivés pour une année, par service.
     *
     * @param array<int> $serviceIds
     * @param array<string,mixed> $filters
     * @param int|null $year
     * @return array<string,mixed>
     */
    private function getMonthlyCountsByServiceForArrive(array $serviceIds, array $filters, ?int $year = null): array
    {
        try {
            $year = $year ?? (int) (new \DateTimeImmutable())->format('Y');
            $startOfYear = (new \DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
            $endOfYear = (new \DateTimeImmutable())->setDate($year, 12, 31)->setTime(23, 59, 59);

            $months = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];

            // Préparer filtres locaux pour limiter à l'année
            $localFilters = $filters;
            $localFilters['start_date'] = $startOfYear;
            $localFilters['end_date'] = $endOfYear;

            $qb = $this->entityManager->createQueryBuilder()
                ->select('s.id AS serviceId, s.nom AS serviceNom, c.dateArrivee AS dateArrivee')
                ->from('App\\Entity\\Cour\\Courrier', 'c')
                ->leftJoin('c.idServiceTraitant', 's')
                ->leftJoin('c.typeCourrier', 'tc')
                ->orderBy('s.nom', 'ASC');

            // Appliquer les filtres sur courrier (dateArrivee)
            $this->applyCourrierFilters($qb, 'c', $serviceIds, $localFilters, true, false, false, false, 'dateArrivee');

            // Forcer la restriction aux services sélectionnés
            $qb->andWhere('s.id IN (:serviceIds)')->setParameter('serviceIds', $serviceIds);

            $rows = $qb->getQuery()->getArrayResult();

            // Récupérer services et initialiser toutes les cases à 0
            $services = $this->entityManager->createQueryBuilder()
                ->select('s')
                ->from(Service::class, 's')
                ->where('s.id IN (:serviceIds2)')
                ->setParameter('serviceIds2', $serviceIds)
                ->orderBy('s.nom', 'ASC')
                ->getQuery()
                ->getResult();

            $resultServices = [];
            foreach ($services as $s) {
                $sid = (int) $s->getId();
                $data = array_fill(0, 12, 0);
                $resultServices[$sid] = [
                    'id' => $sid,
                    'nom' => $s->getNom(),
                    'total' => 0,
                    'data' => $data,
                ];
            }

            foreach ($rows as $row) {
                $sid = (int) ($row['serviceId'] ?? 0);
                if (!isset($resultServices[$sid])) {
                    continue;
                }

                $dateValue = $row['dateArrivee'] ?? null;
                $month = null;
                if ($dateValue instanceof \DateTimeInterface) {
                    $month = (int) $dateValue->format('m');
                } elseif (is_string($dateValue)) {
                    $month = (int) (new \DateTimeImmutable($dateValue))->format('m');
                }

                if ($month >= 1 && $month <= 12) {
                    $resultServices[$sid]['data'][$month - 1]++;
                    $resultServices[$sid]['total']++;
                }
            }

            return [
                'annee' => $year,
                'courrierArrivee' => [
                    'mois' => $months,
                    'services' => array_values($resultServices),
                ],
            ];
        } catch (\Throwable $e) {
            $this->actionLogger->logView('ServiceDashboardStatistics', null, 'Erreur getMonthlyCountsByServiceForArrive', ['exception' => $e->getMessage(), 'trace' => substr($e->getTraceAsString(), 0, 1000), 'serviceIds' => $serviceIds]);
            return ['annee' => $year ?? (int) (new \DateTimeImmutable())->format('Y'), 'courrierArrivee' => ['mois' => ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'], 'services' => []]];
        }
    }

    /**
     * Retourne les comptes mensuels de courriers départ pour une année, par service.
     *
     * @param array<int> $serviceIds
     * @param array<string,mixed> $filters
     * @param int|null $year
     * @return array<string,mixed>
     */
    private function getMonthlyCountsByServiceForDepart(array $serviceIds, array $filters, ?int $year = null): array
    {
        try {
            $year = $year ?? (int) (new \DateTimeImmutable())->format('Y');
            $startOfYear = (new \DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
            $endOfYear = (new \DateTimeImmutable())->setDate($year, 12, 31)->setTime(23, 59, 59);

            $months = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];

            $qb = $this->entityManager->createQueryBuilder()
                ->select('s.id AS serviceId, s.nom AS serviceNom, cd.createdAt AS createdAt')
                ->from('App\\Entity\\Cour\\CourrierDepart', 'cd')
                ->innerJoin('cd.idCourrier', 'c')
                ->leftJoin('c.idServiceTraitant', 's')
                ->leftJoin('c.typeCourrier', 'tc')
                ->where('cd.isDelete = :cdDel')
                ->andWhere('cd.isArchive = :cdArch')
                ->andWhere('cd.createdAt BETWEEN :start AND :end')
                ->setParameter('cdDel', false)
                ->setParameter('cdArch', false)
                ->setParameter('start', $startOfYear)
                ->setParameter('end', $endOfYear)
                ->orderBy('s.nom', 'ASC');

            // Appliquer quelques filtres provenant de $filters (type, priorite, confidentiel)
            if (!empty($filters['type_courrier'])) {
                $qb->andWhere('tc.id IN (:typeIds)')->setParameter('typeIds', $filters['type_courrier']);
            }
            if (!empty($filters['priorite'])) {
                $qb->andWhere('c.priorite = :priorite')->setParameter('priorite', $filters['priorite']);
            }
            if ($filters['is_confidentiel'] !== null) {
                $qb->andWhere('c.isConfidentiel = :isConf')->setParameter('isConf', $filters['is_confidentiel']);
            }

            $qb->andWhere('s.id IN (:serviceIds)')->setParameter('serviceIds', $serviceIds);

            $rows = $qb->getQuery()->getArrayResult();

            $services = $this->entityManager->createQueryBuilder()
                ->select('s')
                ->from(Service::class, 's')
                ->where('s.id IN (:serviceIds2)')
                ->setParameter('serviceIds2', $serviceIds)
                ->orderBy('s.nom', 'ASC')
                ->getQuery()
                ->getResult();

            $resultServices = [];
            foreach ($services as $s) {
                $sid = (int) $s->getId();
                $data = array_fill(0, 12, 0);
                $resultServices[$sid] = [
                    'id' => $sid,
                    'nom' => $s->getNom(),
                    'total' => 0,
                    'data' => $data,
                ];
            }

            foreach ($rows as $row) {
                $sid = (int) ($row['serviceId'] ?? 0);
                if (!isset($resultServices[$sid])) {
                    continue;
                }

                $dateValue = $row['createdAt'] ?? null;
                $month = null;
                if ($dateValue instanceof \DateTimeInterface) {
                    $month = (int) $dateValue->format('m');
                } elseif (is_string($dateValue)) {
                    $month = (int) (new \DateTimeImmutable($dateValue))->format('m');
                }

                if ($month >= 1 && $month <= 12) {
                    $resultServices[$sid]['data'][$month - 1]++;
                    $resultServices[$sid]['total']++;
                }
            }

            return [
                'annee' => $year,
                'courrierDepart' => [
                    'mois' => $months,
                    'services' => array_values($resultServices),
                ],
            ];
        } catch (\Throwable $e) {
            $this->actionLogger->logView('ServiceDashboardStatistics', null, 'Erreur getMonthlyCountsByServiceForDepart', ['exception' => $e->getMessage(), 'trace' => substr($e->getTraceAsString(), 0, 1000), 'serviceIds' => $serviceIds]);
            return ['annee' => $year ?? (int) (new \DateTimeImmutable())->format('Y'), 'courrierDepart' => ['mois' => ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'], 'services' => []]];
        }
    }

    private function applyCourrierArriveCollectionSearch(
        QueryBuilder $queryBuilder,
        ?string $search,
        string $courrierAlias,
        string $serviceAlias,
        string $userAlias,
        string $provenanceAlias,
        string $categorieAlias,
        string $typeAlias,
        string $transmissionAlias,
        string $transmissionServiceAlias,
        string $parameterName,
    ): void {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $isYearSearch = preg_match('/^\d{4}$/', $search) === 1;
        if ($isYearSearch) {
            $year = (int) $search;
            if ($year >= 1900 && $year <= 2100) {
                $startOfYear = (new \DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
                $endOfYear = (new \DateTimeImmutable())->setDate($year, 12, 31)->setTime(23, 59, 59);

                $queryBuilder
                    ->andWhere($queryBuilder->expr()->orX(
                        "LOWER({$courrierAlias}.numero) LIKE LOWER(:{$parameterName})",
                        "({$courrierAlias}.dateArrivee >= :{$parameterName}_year_start AND {$courrierAlias}.dateArrivee <= :{$parameterName}_year_end)",
                        "({$courrierAlias}.dateEnregistrement >= :{$parameterName}_year_start AND {$courrierAlias}.dateEnregistrement <= :{$parameterName}_year_end)"
                    ))
                    ->setParameter($parameterName, '%' . $search . '%')
                    ->setParameter("{$parameterName}_year_start", $startOfYear)
                    ->setParameter("{$parameterName}_year_end", $endOfYear);

                return;
            }
        }

        $searchFields = [
            "{$courrierAlias}.search",
            "{$courrierAlias}.numero",
            "{$courrierAlias}.objet",
            "{$courrierAlias}.commentaire",
            "{$courrierAlias}.commentairePublic",
            "{$courrierAlias}.commentaireInterne",
            "{$courrierAlias}.priorite",
            "{$courrierAlias}.statut",
            "{$courrierAlias}.telephone",
            "{$courrierAlias}.email",
            "{$courrierAlias}.adresse",
            "{$courrierAlias}.civilite",
            "{$courrierAlias}.nom",
            "{$courrierAlias}.matricule",
            "{$courrierAlias}.typeTransfert",
            "{$courrierAlias}.classeCourrier",
            "{$courrierAlias}.categorie",
            "{$courrierAlias}.document",
            "{$courrierAlias}.bordereauRemise",
            "{$serviceAlias}.search",
            "{$serviceAlias}.nom",
            "{$serviceAlias}.sigle",
            "{$userAlias}.search",
            "{$userAlias}.username",
            "{$userAlias}.email",
            "{$userAlias}.firstName",
            "{$userAlias}.lastName",
            "{$provenanceAlias}.search",
            "{$provenanceAlias}.nom",
            "{$provenanceAlias}.adresse",
            "{$provenanceAlias}.telephone",
            "{$provenanceAlias}.email",
            "{$provenanceAlias}.type",
            "{$provenanceAlias}.civilite",
            "{$provenanceAlias}.matricule",
            "{$categorieAlias}.search",
            "{$categorieAlias}.nom",
            "{$typeAlias}.search",
            "{$typeAlias}.nom",
            "{$typeAlias}.type",
            "{$typeAlias}.classeCourrier",
            "{$transmissionAlias}.search",
            "{$transmissionAlias}.instruction",
            "{$transmissionAlias}.typeTransfert",
            "{$transmissionAlias}.statut",
            "{$transmissionServiceAlias}.search",
            "{$transmissionServiceAlias}.nom",
            "{$transmissionServiceAlias}.sigle",
        ];

        $orX = $queryBuilder->expr()->orX();
        foreach ($searchFields as $field) {
            $orX->add("LOWER({$field}) LIKE LOWER(:{$parameterName})");
        }

        if (ctype_digit($search)) {
            $orX->add("{$courrierAlias}.id = :{$parameterName}_number");
            $orX->add("{$courrierAlias}.nombrePieceJointe = :{$parameterName}_number");
            $orX->add("{$serviceAlias}.id = :{$parameterName}_number");
            $orX->add("{$userAlias}.id = :{$parameterName}_number");
            $orX->add("{$provenanceAlias}.id = :{$parameterName}_number");
            $orX->add("{$categorieAlias}.id = :{$parameterName}_number");
            $orX->add("{$typeAlias}.id = :{$parameterName}_number");
            $orX->add("{$transmissionAlias}.id = :{$parameterName}_number");
            $orX->add("{$transmissionServiceAlias}.id = :{$parameterName}_number");
            $queryBuilder->setParameter("{$parameterName}_number", (int) $search);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $search);
        $dateErrors = \DateTimeImmutable::getLastErrors();
        $isValidDate = $date !== false && (
            $dateErrors === false ||
            ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0)
        );

        if ($isValidDate) {
            $startOfDay = $date->setTime(0, 0, 0);
            $endOfDay = $date->setTime(23, 59, 59);

            $orX->add("({$courrierAlias}.dateArrivee >= :{$parameterName}_date_start AND {$courrierAlias}.dateArrivee <= :{$parameterName}_date_end)");
            $orX->add("({$courrierAlias}.dateEnregistrement >= :{$parameterName}_date_start AND {$courrierAlias}.dateEnregistrement <= :{$parameterName}_date_end)");
            $orX->add("({$courrierAlias}.createdAt >= :{$parameterName}_date_start AND {$courrierAlias}.createdAt <= :{$parameterName}_date_end)");
            $orX->add("({$courrierAlias}.updatedAt >= :{$parameterName}_date_start AND {$courrierAlias}.updatedAt <= :{$parameterName}_date_end)");
            $orX->add("({$transmissionAlias}.dateInstruction >= :{$parameterName}_date_start AND {$transmissionAlias}.dateInstruction <= :{$parameterName}_date_end)");
            $orX->add("({$transmissionAlias}.dateReception >= :{$parameterName}_date_start AND {$transmissionAlias}.dateReception <= :{$parameterName}_date_end)");

            $queryBuilder
                ->setParameter("{$parameterName}_date_start", $startOfDay)
                ->setParameter("{$parameterName}_date_end", $endOfDay);
        }

        $queryBuilder
            ->andWhere($orX)
            ->setParameter($parameterName, '%' . $search . '%');
    }

    /**
     * @return array{total: int, data: array<int, array<string, mixed>>}
     */
    private function getCourriersDepartCollectionAllServices(array $filters, string $search, int $page, int $limit): array
    {
        $isDelete = (bool) ($filters['is_delete'] ?? false);
        $isArchive = (bool) ($filters['isarchive'] ?? false);
        $serviceIdFilter = $filters['service_id'] ?? null;
        $isConfidentiel = $filters['is_confidentiel'] ?? null;
        $startDate = $filters['start_date'] instanceof \DateTimeImmutable
            ? $filters['start_date']->format('Y-m-d')
            : $filters['start_date'];
        $endDate = $filters['end_date'] instanceof \DateTimeImmutable
            ? $filters['end_date']->format('Y-m-d')
            : $filters['end_date'];
        $date = $filters['date'] ?? null;
        $year = $filters['year'] ?? null;
        $priorite = $filters['priorite'] ?? null;
        $categorie = $filters['categorie'] ?? null;
        $typeCourrier = $filters['type_courrier'] ?? null;
        $classeCourrier = $filters['classe_courrier'] ?? null;
        $idDestinataire = $filters['id_destinataire'] ?? null;

        $qb = $this->courrierDepartRepository->createQueryBuilder('cd')
            ->leftJoin('cd.destinataire', 'd')
            ->leftJoin('cd.idSignataire', 's')
            ->leftJoin('cd.idCourrier', 'c')
            ->leftJoin('c.typeCourrier', 'tc')
            ->leftJoin('c.idProvenance', 'prov')
            ->leftJoin('prov.categories', 'cat')
            ->addSelect('d', 's', 'c', 'tc', 'prov', 'cat')
            ->where('cd.isDelete = :isDelete')
            ->setParameter('isDelete', $isDelete)
            ->andWhere('cd.isArchive = :isArchive')
            ->setParameter('isArchive', $isArchive)
            ->orderBy('cd.createdAt', 'DESC');

        $this->applyCourrierDepartCollectionSearch(
            $qb,
            $search,
            'cd',
            'd',
            's',
            'c',
            'tc',
            'prov',
            'cat',
            'search'
        );

        // Dates
        if (!empty($startDate) && !empty($endDate)) {
            $qb->andWhere('c.dateArrivee BETWEEN :start AND :end')
                ->setParameter('start', new \DateTime($startDate . ' 00:00:00'))
                ->setParameter('end', new \DateTime($endDate . ' 23:59:59'));
        } elseif (!empty($date)) {
            $qb->andWhere('c.dateArrivee BETWEEN :dateStart AND :dateEnd')
                ->setParameter('dateStart', new \DateTime($date . ' 00:00:00'))
                ->setParameter('dateEnd', new \DateTime($date . ' 23:59:59'));
        } elseif (!empty($year)) {
            $yearInt = (int) $year;
            if ($yearInt >= 1900 && $yearInt <= 2100) {
                $startOfYear = (new \DateTimeImmutable())->setDate($yearInt, 1, 1)->setTime(0, 0, 0);
                $endOfYear = (new \DateTimeImmutable())->setDate($yearInt, 12, 31)->setTime(23, 59, 59);
                $qb->andWhere('c.dateArrivee >= :yearStart AND c.dateArrivee <= :yearEnd')
                    ->setParameter('yearStart', $startOfYear)
                    ->setParameter('yearEnd', $endOfYear);
            }
        }

        if (!empty($priorite) && $priorite !== 'Toutes') {
            $qb->andWhere('c.priorite = :priorite')
                ->setParameter('priorite', $priorite);
        }

        if (!empty($categorie)) {
            $qb->andWhere('cat.id = :categorie')
                ->setParameter('categorie', (int) $categorie);
        }

        // Filtre type_courrier (tableau)
        if (!empty($typeCourrier)) {
            $qb->andWhere('tc.id IN (:typeCourrier)')
                ->setParameter('typeCourrier', $typeCourrier);
        }

        if (!empty($classeCourrier)) {
            $qb->andWhere('LOWER(cd.classeCourrier) LIKE LOWER(:classeCourrier)')
                ->setParameter('classeCourrier', '%' . trim((string) $classeCourrier) . '%');
        }

        if (!empty($idDestinataire)) {
            $qb->andWhere('d.id = :idDestinataire')
                ->setParameter('idDestinataire', (int) $idDestinataire);
        }
        // Filtre service_id (tableau)
        if (!empty($serviceIdFilter)) {
            $qb->andWhere('c.idServiceTraitant IN (:serviceIdFilter)')
                ->setParameter('serviceIdFilter', $serviceIdFilter);
        }
        if ($isConfidentiel !== null) {
            $qb->andWhere('c.isConfidentiel = :isConfidentiel')
                ->setParameter('isConfidentiel', $isConfidentiel);
        }

        $total = (int) (clone $qb)
            ->select('COUNT(cd.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        if ($limit > 0) {
            $qb->setMaxResults($limit)
                ->setFirstResult(($page - 1) * $limit);
        }

        $results = $qb->getQuery()->getResult();

        $courrierDepartIds = array_map(static fn ($cd) => $cd->getId(), $results);
        $piecesJointesGrouped = [];

        if (!empty($courrierDepartIds)) {
            $piecesJointes = $this->pieceJointeRepository->createQueryBuilder('pj')
                ->where('pj.typeParent = :typeParent')
                ->andWhere('pj.idParent IN (:ids)')
                ->andWhere('pj.isDelete = false')
                ->setParameter('typeParent', 'CourrierDepart')
                ->setParameter('ids', $courrierDepartIds)
                ->getQuery()
                ->getResult();

            foreach ($piecesJointes as $pj) {
                $piecesJointesGrouped[$pj->getIdParent()][] = $pj;
            }
        }

        $courrierArriveIds = [];
        foreach ($results as $cd) {
            $courrierId = $cd->getIdCourrier()?->getId();
            if ($courrierId !== null) {
                $courrierArriveIds[] = $courrierId;
            }
        }
        $courrierArriveIds = array_unique($courrierArriveIds);

        $piecesJointesCourrierGrouped = [];
        if (!empty($courrierArriveIds)) {
            $piecesJointesCourrier = $this->pieceJointeRepository->createQueryBuilder('pj')
                ->where('pj.typeParent = :typeParent')
                ->andWhere('pj.idParent IN (:ids)')
                ->andWhere('pj.isDelete = false')
                ->setParameter('typeParent', 'Courrier')
                ->setParameter('ids', $courrierArriveIds)
                ->orderBy('pj.id', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($piecesJointesCourrier as $pj) {
                $piecesJointesCourrierGrouped[$pj->getIdParent()][] = $pj;
            }
        }

        $transmissionsByCourrierId = [];
        $transmissionIds = [];
        if (!empty($courrierArriveIds)) {
            $transmissions = $this->transmissionRepository->createQueryBuilder('t')
                ->leftJoin('t.idServiceDestinataire', 'sd')
                ->leftJoin('t.idEmetteur', 'em')
                ->addSelect('sd', 'em')
                ->where('t.idCourrier IN (:ids)')
                ->andWhere('t.isDelete = false')
                ->setParameter('ids', $courrierArriveIds)
                ->orderBy('t.dateInstruction', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($transmissions as $t) {
                $courrierId = $t->getIdCourrier()?->getId();
                if ($courrierId !== null) {
                    $transmissionsByCourrierId[$courrierId][] = $t;
                }
                $transmissionIds[] = $t->getId();
            }
        }

        $piecesJointesTransmissionGrouped = [];
        if (!empty($transmissionIds)) {
            $piecesJointesTransmission = $this->pieceJointeRepository->createQueryBuilder('pj')
                ->where('pj.typeParent = :typeParent')
                ->andWhere('pj.idParent IN (:ids)')
                ->andWhere('pj.isDelete = false')
                ->setParameter('typeParent', 'Transmission')
                ->setParameter('ids', $transmissionIds)
                ->orderBy('pj.id', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($piecesJointesTransmission as $pj) {
                $piecesJointesTransmissionGrouped[$pj->getIdParent()][] = $pj;
            }
        }

        $allProvenancesCopieIds = [];
        foreach ($results as $cd) {
            if ($cd->getProvenancesCopie()) {
                $allProvenancesCopieIds = array_merge($allProvenancesCopieIds, $cd->getProvenancesCopie());
            }
        }
        $allProvenancesCopieIds = array_unique($allProvenancesCopieIds);

        $correspondantsMap = [];
        if (!empty($allProvenancesCopieIds)) {
            $correspondants = $this->correspondantRepository->createQueryBuilder('c')
                ->where('c.id IN (:ids)')
                ->setParameter('ids', $allProvenancesCopieIds)
                ->getQuery()
                ->getResult();

            foreach ($correspondants as $corr) {
                $correspondantsMap[$corr->getId()] = [
                    'id' => $corr->getId(),
                    'nom' => $corr->getNom(),
                ];
            }
        }

        $data = array_map(function ($cd) use (
            $piecesJointesGrouped,
            $piecesJointesCourrierGrouped,
            $correspondantsMap,
            $transmissionsByCourrierId,
            $piecesJointesTransmissionGrouped
        ): array {
            $piecesJointes = $piecesJointesGrouped[$cd->getId()] ?? [];
            $courrierArriveId = $cd->getIdCourrier()?->getId();
            $piecesJointesCourrier = $courrierArriveId !== null
                ? ($piecesJointesCourrierGrouped[$courrierArriveId] ?? [])
                : [];
            $transmissions = $courrierArriveId !== null
                ? ($transmissionsByCourrierId[$courrierArriveId] ?? [])
                : [];

            $provenancesCopieEnriched = [];
            if ($cd->getProvenancesCopie()) {
                foreach ($cd->getProvenancesCopie() as $corrId) {
                    if (isset($correspondantsMap[$corrId])) {
                        $provenancesCopieEnriched[] = $correspondantsMap[$corrId];
                    }
                }
            }

            return [
                'id' => $cd->getId(),
                'numeroReference' => $cd->getNumeroReference(),
                'numeroActe' => $cd->getNumeroActe(),
                'dateSignature' => $cd->getDateSignature()?->format('Y-m-d'),
                'typeCourrier' => $cd->getTypeCourrier(),
                'commentaire' => $cd->getCommentaire(),
                'classeCourrier' => $cd->getClasseCourrier(),
                'categorie' => $cd->getCategorie(),
                'document' => $cd->getDocument(),
                'email' => $cd->getEmail(),
                'numeroTelephone' => $cd->getNumeroTelephone(),
                'nombrePieceJointe' => $cd->getNombrePieceJointe(),
                'provenancesCopie' => $provenancesCopieEnriched,
                'piecesJointes' => array_map(static fn ($pj): array => [
                    'id' => $pj->getId(),
                    'nom' => $pj->getNom(),
                    'chemin' => $pj->getChemin(),
                    'type' => $pj->getType(),
                ], $piecesJointes),
                'destinataire' => [
                    'id' => $cd->getDestinataire()?->getId(),
                    'nom' => $cd->getDestinataire()?->getNom(),
                ],
                'signataire' => [
                    'id' => $cd->getIdSignataire()?->getId(),
                    'fullName' => $cd->getIdSignataire()?->getFullName(),
                ],
                'courrier' => $cd->getIdCourrier() ? [
                    'id' => $cd->getIdCourrier()->getId(),
                    'numero' => $cd->getIdCourrier()->getNumero(),
                    'reference' => $cd->getIdCourrier()->getNumero(),
                    'objet' => $cd->getIdCourrier()->getObjet(),
                    'is_geled' => $cd->getIdCourrier()->isGeled(),
                    'dateArrivee' => $cd->getIdCourrier()->getDateArrivee()?->format('Y-m-d'),
                    'dateEnregistrement' => $cd->getIdCourrier()->getDateEnregistrement()?->format('Y-m-d'),
                    'typeCourrier' => $cd->getIdCourrier()->getTypeCourrier()?->getNom(),
                    'provenance' => $cd->getIdCourrier()->getIdProvenance()?->getNom(),
                    'categorie' => $cd->getIdCourrier()->getIdProvenance() && $cd->getIdCourrier()->getIdProvenance()->getCategories()->count() > 0
                        ? $cd->getIdCourrier()->getIdProvenance()->getCategories()->first()->getNom()
                        : null,
                    'priorite' => $cd->getIdCourrier()->getPriorite(),
                    'document' => $cd->getIdCourrier()->getDocument(),
                    'piecesJointes' => array_map(static fn ($pj): array => [
                        'id' => $pj->getId(),
                        'nom' => $pj->getNom(),
                        'intitule' => $pj->getIntitule(),
                        'chemin' => $pj->getChemin(),
                        'type' => $pj->getType(),
                    ], $piecesJointesCourrier),
                ] : null,
                'transmissions' => array_map(function ($t) use ($piecesJointesTransmissionGrouped): array {
                    $transmissionPieces = $piecesJointesTransmissionGrouped[$t->getId()] ?? [];

                    return [
                        'id' => $t->getId(),
                        'serviceDestinataire' => $t->getIdServiceDestinataire() ? [
                            'id' => $t->getIdServiceDestinataire()->getId(),
                            'nom' => $t->getIdServiceDestinataire()->getNom(),
                            'sigle' => $t->getIdServiceDestinataire()->getSigle(),
                        ] : null,
                        'emetteur' => $t->getIdEmetteur() ? [
                            'id' => $t->getIdEmetteur()->getId(),
                            'fullName' => $t->getIdEmetteur()->getFullName(),
                            'email' => $t->getIdEmetteur()->getEmail(),
                        ] : null,
                        'structuresCopie' => $t->getStructuresCopie(),
                        'dateInstruction' => $t->getDateInstruction()?->format('Y-m-d H:i:s'),
                        'dateReception' => $t->getDateReception()?->format('Y-m-d H:i:s'),
                        'instruction' => $t->getInstruction(),
                        'delaiTraitement' => $t->getDelaiTraitement(),
                        'typeTransfert' => $t->getTypeTransfert(),
                        'accuseReception' => $t->isAccuseReception(),
                        'statut' => $t->getStatut(),
                        'isinstance' => $t->isinstance(),
                        'isArchive' => $t->isArchive(),
                        'nombrePieceJointe' => $t->getNombrePieceJointe(),
                        'piecesJointes' => array_map(static fn ($pj): array => [
                            'id' => $pj->getId(),
                            'nom' => $pj->getNom(),
                            'intitule' => $pj->getIntitule(),
                            'chemin' => $pj->getChemin(),
                            'type' => $pj->getType(),
                        ], $transmissionPieces),
                        'createdAt' => $t->getCreatedAt()?->format('Y-m-d H:i:s'),
                    ];
                }, $transmissions),
                'isDelete' => $cd->isDelete(),
                'isArchive' => $cd->isArchive(),
                'createdAt' => $cd->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $results);

        return [
            'total' => $total,
            'data' => $data,
        ];
    }

    private function applyCourrierDepartCollectionSearch(
        QueryBuilder $queryBuilder,
        ?string $search,
        string $courrierDepartAlias,
        string $destinataireAlias,
        string $signataireAlias,
        string $courrierAlias,
        string $typeAlias,
        string $provenanceAlias,
        string $categorieAlias,
        string $parameterName,
    ): void {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $orX = $queryBuilder->expr()->orX();

        $searchFields = [
            "{$courrierDepartAlias}.search",
            "{$courrierDepartAlias}.numeroReference",
            "{$courrierDepartAlias}.numeroActe",
            "{$courrierDepartAlias}.typeCourrier",
            "{$courrierDepartAlias}.commentaire",
            "{$courrierDepartAlias}.classeCourrier",
            "{$courrierDepartAlias}.categorie",
            "{$courrierDepartAlias}.document",
            "{$courrierDepartAlias}.email",
            "{$courrierDepartAlias}.numeroTelephone",
            "{$courrierDepartAlias}.statutArchive",
            "{$courrierAlias}.search",
            "{$courrierAlias}.numero",
            "{$courrierAlias}.objet",
            "{$courrierAlias}.commentaire",
            "{$courrierAlias}.commentairePublic",
            "{$courrierAlias}.commentaireInterne",
            "{$courrierAlias}.priorite",
            "{$courrierAlias}.statut",
            "{$courrierAlias}.classeCourrier",
            "{$courrierAlias}.categorie",
            "{$courrierAlias}.telephone",
            "{$courrierAlias}.email",
            "{$courrierAlias}.adresse",
            "{$courrierAlias}.civilite",
            "{$courrierAlias}.nom",
            "{$courrierAlias}.matricule",
            "{$courrierAlias}.typeTransfert",
            "{$typeAlias}.search",
            "{$typeAlias}.nom",
            "{$typeAlias}.type",
            "{$typeAlias}.classeCourrier",
            "{$provenanceAlias}.search",
            "{$provenanceAlias}.nom",
            "{$provenanceAlias}.adresse",
            "{$provenanceAlias}.telephone",
            "{$provenanceAlias}.email",
            "{$provenanceAlias}.type",
            "{$provenanceAlias}.civilite",
            "{$provenanceAlias}.matricule",
            "{$categorieAlias}.search",
            "{$categorieAlias}.nom",
            "{$destinataireAlias}.search",
            "{$destinataireAlias}.nom",
            "{$destinataireAlias}.adresse",
            "{$destinataireAlias}.telephone",
            "{$destinataireAlias}.email",
            "{$destinataireAlias}.type",
            "{$destinataireAlias}.civilite",
            "{$destinataireAlias}.matricule",
            "{$signataireAlias}.search",
            "{$signataireAlias}.username",
            "{$signataireAlias}.email",
            "{$signataireAlias}.firstName",
            "{$signataireAlias}.lastName",
            "{$signataireAlias}.phone",
        ];

        foreach ($searchFields as $field) {
            $orX->add("LOWER({$field}) LIKE LOWER(:{$parameterName})");
        }

        if (ctype_digit($search)) {
            $orX->add("{$courrierDepartAlias}.id = :{$parameterName}_number");
            $orX->add("{$courrierDepartAlias}.nombrePieceJointe = :{$parameterName}_number");
            $orX->add("{$courrierAlias}.id = :{$parameterName}_number");
            $orX->add("{$courrierAlias}.nombrePieceJointe = :{$parameterName}_number");
            $orX->add("{$typeAlias}.id = :{$parameterName}_number");
            $orX->add("{$provenanceAlias}.id = :{$parameterName}_number");
            $orX->add("{$categorieAlias}.id = :{$parameterName}_number");
            $orX->add("{$destinataireAlias}.id = :{$parameterName}_number");
            $orX->add("{$signataireAlias}.id = :{$parameterName}_number");
            $queryBuilder->setParameter("{$parameterName}_number", (int) $search);
        }

        $isYearSearch = preg_match('/^\d{4}$/', $search) === 1;
        if ($isYearSearch) {
            $year = (int) $search;
            if ($year >= 1900 && $year <= 2100) {
                $startOfYear = (new \DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
                $endOfYear = (new \DateTimeImmutable())->setDate($year, 12, 31)->setTime(23, 59, 59);

                $orX->add("({$courrierDepartAlias}.dateSignature >= :{$parameterName}_year_start AND {$courrierDepartAlias}.dateSignature <= :{$parameterName}_year_end)");
                $orX->add("({$courrierAlias}.dateArrivee >= :{$parameterName}_year_start AND {$courrierAlias}.dateArrivee <= :{$parameterName}_year_end)");
                $orX->add("({$courrierAlias}.dateEnregistrement >= :{$parameterName}_year_start AND {$courrierAlias}.dateEnregistrement <= :{$parameterName}_year_end)");
                $orX->add("({$courrierDepartAlias}.createdAt >= :{$parameterName}_year_start AND {$courrierDepartAlias}.createdAt <= :{$parameterName}_year_end)");
                $orX->add("({$courrierDepartAlias}.updatedAt >= :{$parameterName}_year_start AND {$courrierDepartAlias}.updatedAt <= :{$parameterName}_year_end)");

                $queryBuilder
                    ->setParameter("{$parameterName}_year_start", $startOfYear)
                    ->setParameter("{$parameterName}_year_end", $endOfYear);
            }
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $search);
        $dateErrors = \DateTimeImmutable::getLastErrors();
        $isValidDate = $date !== false && (
            $dateErrors === false ||
            ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0)
        );

        if ($isValidDate) {
            $startOfDay = $date->setTime(0, 0, 0);
            $endOfDay = $date->setTime(23, 59, 59);

            $orX->add("({$courrierDepartAlias}.dateSignature >= :{$parameterName}_date_start AND {$courrierDepartAlias}.dateSignature <= :{$parameterName}_date_end)");

            $queryBuilder
                ->setParameter("{$parameterName}_date_start", $startOfDay)
                ->setParameter("{$parameterName}_date_end", $endOfDay);
        }

        $queryBuilder
            ->andWhere($orX)
            ->setParameter($parameterName, '%' . $search . '%');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getReponsesData(array $serviceIds, array $filters, ?int $page, ?int $limit): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r, sd, red, tr')
            ->from('App\Entity\Cour\Reponse', 'r')
            ->leftJoin('r.courriers', 'c')
            ->leftJoin('r.idServiceDestinataire', 'sd')
            ->leftJoin('r.idRedacteur', 'red')
            ->leftJoin('r.typeReponse', 'tr')
            ->where('r.isDelete = :reponseDeletedData')
            ->setParameter('reponseDeletedData', false)
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->distinct();

        // Filtre sur les services destinataires
        if (!empty($filters['service_id'])) {
            $qb->andWhere('r.idServiceDestinataire IN (:serviceIds)')
                ->setParameter('serviceIds', $filters['service_id']);
        } elseif (empty($filters['all_services'])) {
            $qb->andWhere('r.idServiceDestinataire IN (:serviceIdsReponseData)')
                ->setParameter('serviceIdsReponseData', $serviceIds);
        }

        // Filtre type_courrier (tableau)
        if (!empty($filters['type_courrier'])) {
            $qb->andWhere('c.typeCourrier IN (:typeCourrier)')
                ->setParameter('typeCourrier', $filters['type_courrier']);
        }

        if ($page !== null && $limit !== null) {
            $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        }
        $rows = $qb->getQuery()->getResult();

        return array_map(static function ($reponse): array {
            return [
                'id' => $reponse->getId(),
                'objet' => $reponse->getObjet(),
                'priorite' => $reponse->getPriorite(),
                'statut' => $reponse->getStatut(),
                'dateReponse' => $reponse->getDateReponse()?->format('Y-m-d'),
                'classeCourrier' => $reponse->getClasseCourrier(),
                'typeTransmission' => $reponse->getTypeTransmission(),
                'serviceDestinataire' => $reponse->getIdServiceDestinataire() ? [
                    'id' => $reponse->getIdServiceDestinataire()->getId(),
                    'nom' => $reponse->getIdServiceDestinataire()->getNom(),
                    'sigle' => $reponse->getIdServiceDestinataire()->getSigle(),
                ] : null,
                'redacteur' => $reponse->getIdRedacteur() ? [
                    'id' => $reponse->getIdRedacteur()->getId(),
                    'fullName' => $reponse->getIdRedacteur()->getFullName(),
                ] : null,
                'typeReponse' => $reponse->getTypeReponse() ? [
                    'id' => $reponse->getTypeReponse()->getId(),
                    'nom' => $reponse->getTypeReponse()->getNom(),
                ] : null,
                'idReponses' => $reponse->getIdReponses(),
                'createdAt' => $reponse->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $rows);
    }

    /**
     * @return array<int, array{priorite: string, nombreCourriers: int}>
     */
    private function getRepartitionParPriorite(array $serviceIds, array $filters): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('c.priorite AS priorite, COUNT(c.id) AS total')
            ->from('App\Entity\Cour\Courrier', 'c')
            ->groupBy('c.priorite')
            ->orderBy('total', 'DESC');

        $this->applyCourrierFilters($qb, 'c', $serviceIds, $filters, true, true);
        $rows = $qb->getQuery()->getArrayResult();

        $mapped = [];
        $labels = [];
        foreach ($rows as $row) {
            $label = self::normalizePrioriteLabelForStats($row['priorite'] ?? null);
            $key = strtolower($label);
            $labels[$key] = $label;
            $mapped[$key] = ($mapped[$key] ?? 0) + (int) ($row['total'] ?? 0);
        }

        $result = [];
        foreach ($mapped as $key => $count) {
            $result[] = [
                'priorite' => $labels[$key] ?? 'Non defini',
                'nombreCourriers' => $count,
            ];
        }

        usort($result, static fn (array $a, array $b) => $b['nombreCourriers'] <=> $a['nombreCourriers']);

        return $result;
    }

    /**
     * @return array<int, array{statut: string, nombreCourriers: int}>
     */
    private function getRepartitionParStatut(array $serviceIds, array $filters): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('c.statut AS statut, COUNT(c.id) AS total')
            ->from('App\Entity\Cour\Courrier', 'c')
            ->groupBy('c.statut')
            ->orderBy('total', 'DESC');

        $this->applyCourrierFilters($qb, 'c', $serviceIds, $filters, true, true);
        $rows = $qb->getQuery()->getArrayResult();

        $mapped = [];
        foreach ($rows as $row) {
            $statut = trim((string) ($row['statut'] ?? ''));
            $key = $statut !== '' ? $statut : 'Non defini';
            $mapped[$key] = ($mapped[$key] ?? 0) + (int) ($row['total'] ?? 0);
        }

        $result = [];
        foreach ($mapped as $label => $count) {
            $result[] = [
                'statut' => $label,
                'nombreCourriers' => $count,
            ];
        }

        usort($result, static fn (array $a, array $b) => $b['nombreCourriers'] <=> $a['nombreCourriers']);

        return $result;
    }

    /**
     * @return array<int, array{serviceId: int, serviceNom: string|null, serviceSigle: string|null, nombreCourriers: int}>
     */
    private function getTopPostes(array $serviceIds, array $filters): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('s.id AS serviceId, s.nom AS serviceNom, s.sigle AS serviceSigle, COUNT(c.id) AS total')
            ->from('App\Entity\Cour\Courrier', 'c')
            ->innerJoin('c.idServiceTraitant', 's')
            ->andWhere('s.typeService = :typeServicePoste')
            ->setParameter('typeServicePoste', 'poste')
            ->groupBy('s.id, s.nom, s.sigle')
            ->orderBy('total', 'DESC')
            ->setMaxResults(10);

        $this->applyCourrierFilters($qb, 'c', $serviceIds, $filters, true, true);

        return array_map(static fn (array $row) => [
            'serviceId' => (int) ($row['serviceId'] ?? 0),
            'serviceNom' => $row['serviceNom'] ?? null,
            'serviceSigle' => $row['serviceSigle'] ?? null,
            'nombreCourriers' => (int) ($row['total'] ?? 0),
        ], $qb->getQuery()->getArrayResult());
    }

    private function applyCourrierFilters(
        QueryBuilder $qb,
        string $alias,
        array $serviceIds,
        array $filters,
        bool $includeArchiveFilter = true,
        bool $ignorePriorityFilter = false,
        bool $ignoreServiceTraitantFilter = false,
        bool $ignoreDeleteFilter = false,
        string $dateField = 'dateEnregistrement',
    ): void {
        if (!$ignoreDeleteFilter) {
            $qb->andWhere(sprintf('%s.isDelete = :%sIsDelete', $alias, $alias))
                ->setParameter(sprintf('%sIsDelete', $alias), false);
        }

        if ($includeArchiveFilter) {
            $qb->andWhere(sprintf('%s.isArchive = :%sIsArchive', $alias, $alias))
                ->setParameter(sprintf('%sIsArchive', $alias), false);
        }

        $isAllServicesMode = !empty($filters['all_services']);
        $serviceIdFilter = $filters['service_id'] ?? null;

        if (empty($serviceIds) && !$isAllServicesMode) {
            $qb->andWhere('1 = 0');
            return;
        }

        // Filtre type_courrier (tableau)
        if (!empty($filters['type_courrier'])) {
            $qb->andWhere(sprintf('%s.typeCourrier IN (:typeCourrier)', $alias))
                ->setParameter('typeCourrier', $filters['type_courrier']);
        }

        if (!$ignoreServiceTraitantFilter) {
            if (!empty($serviceIdFilter)) {
                $qb->andWhere(sprintf('%s.idServiceTraitant IN (:serviceIdFilter)', $alias))
                    ->setParameter('serviceIdFilter', $serviceIdFilter);
            } elseif (!$isAllServicesMode) {
                $qb->andWhere(sprintf('%s.idServiceTraitant IN (:serviceScopeIds)', $alias))
                    ->setParameter('serviceScopeIds', $serviceIds);
            }
        }

        if ($filters['start_date'] instanceof \DateTimeImmutable) {
            $qb->andWhere(sprintf('%s.%s >= :dateStart', $alias, $dateField))
                ->setParameter('dateStart', $filters['start_date']->setTime(0, 0, 0));
        }

        if ($filters['end_date'] instanceof \DateTimeImmutable) {
            $qb->andWhere(sprintf('%s.%s <= :dateEnd', $alias, $dateField))
                ->setParameter('dateEnd', $filters['end_date']->setTime(23, 59, 59));
        }

        if (!$ignorePriorityFilter && !empty($filters['priorite']) && $filters['priorite'] !== 'Toutes') {
            $qb->andWhere(sprintf('%s.priorite = :priorite', $alias))
                ->setParameter('priorite', $filters['priorite']);
        }

        if ($filters['is_confidentiel'] !== null) {
            $qb->andWhere(sprintf('%s.isConfidentiel = :isConfidentiel', $alias))
                ->setParameter('isConfidentiel', $filters['is_confidentiel']);
        }
    }

    private function hasCourrierBasedFilter(array $filters): bool
    {
        return $filters['start_date'] instanceof \DateTimeImmutable
            || $filters['end_date'] instanceof \DateTimeImmutable
            || !empty($filters['priorite'])
            || $filters['is_confidentiel'] !== null;
    }

    private function isNoFilterModeForCurrentUserScope(
        array $filters,
        string $search,
        bool $allServices,
        array $selectedServiceIds,
    ): bool {
        if ($allServices) {
            return false;
        }

        if (!empty($selectedServiceIds)) {
            return false;
        }

        if (trim($search) !== '') {
            return false;
        }

        if ($filters['start_date'] instanceof \DateTimeImmutable || $filters['end_date'] instanceof \DateTimeImmutable) {
            return false;
        }

        if (!empty($filters['priorite']) || $filters['is_confidentiel'] !== null) {
            return false;
        }

        foreach ([
            'date',
            'categorie',
            'type_courrier',
            'classe_courrier',
            'provenance',
            'service_traitant',
            'createur',
            'statut',
            'year',
            'id_destinataire',
        ] as $key) {
            if (!empty($filters[$key] ?? null)) {
                return false;
            }
        }

        if (($filters['has_courrier_depart'] ?? null) !== null) {
            return false;
        }

        if (!empty($filters['is_delete']) || !empty($filters['isarchive'])) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, Service>
     */
    private function getServiceScope(Service $rootService): array
    {
        $visited = [];
        $result = [];
        $stack = [$rootService];

        while (!empty($stack)) {
            /** @var Service $service */
            $service = array_pop($stack);
            $serviceId = $service->getId();

            if ($serviceId === null || isset($visited[$serviceId])) {
                continue;
            }
            $visited[$serviceId] = true;

            if ($service->isDelete()) {
                continue;
            }

            $result[] = $service;

            foreach ($service->getServiceEnfants() as $child) {
                $stack[] = $child;
            }
        }

        usort($result, static function (Service $a, Service $b): int {
            return strcmp((string) $a->getNom(), (string) $b->getNom());
        });

        return $result;
    }

    /**
     * @return array<int, Service>
     */
    private function getDirectChildrenServices(Service $parentService): array
    {
        $children = [];
        foreach ($parentService->getServiceEnfants() as $child) {
            if ($child instanceof Service && !$child->isDelete()) {
                $children[] = $child;
            }
        }

        usort($children, static function (Service $a, Service $b): int {
            return strcmp((string) $a->getNom(), (string) $b->getNom());
        });

        return $children;
    }

    /**
     * @return array<int, Service>
     */
    private function getAllActiveServices(): array
    {
        $services = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from('App\Entity\Core\Service', 's')
            ->where('s.isDelete = :serviceIsDelete')
            ->setParameter('serviceIsDelete', false)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($services, static fn ($service) => $service instanceof Service));
    }

    private function parseDate(mixed $value, string $field): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        $errors = \DateTimeImmutable::getLastErrors();
        $isValid = $date !== false && (
            $errors === false ||
            ($errors['warning_count'] === 0 && $errors['error_count'] === 0)
        );

        if (!$isValid) {
            throw new \InvalidArgumentException(sprintf('%s doit etre au format YYYY-MM-DD.', $field));
        }

        return $date->setTime(0, 0, 0);
    }

    private function parseConfidentialite(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return null;
        }

        if (in_array($raw, ['true', '1', 'oui', 'confidentiel'], true)) {
            return true;
        }

        if (in_array($raw, ['false', '0', 'non', 'non_confidentiel'], true)) {
            return false;
        }

        throw new \InvalidArgumentException('is_confidentiel doit valoir true/false ou confidentiel/non_confidentiel.');
    }

    private function parseBooleanQuery(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return $default;
        }

        if (in_array($raw, ['true', '1', 'oui', 'yes'], true)) {
            return true;
        }

        if (in_array($raw, ['false', '0', 'non', 'no'], true)) {
            return false;
        }

        throw new \InvalidArgumentException('all_services doit valoir true ou false.');
    }

    private static function normalizePrioriteLabelForStats(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return 'Non defini';
        }

        $lower = strtolower($raw);
        if ($lower === 'basse') {
            return 'Basse';
        }
        if (in_array($lower, ['normal', 'normale'], true)) {
            return 'Normal';
        }
        if ($lower === 'haute') {
            return 'Haute';
        }

        return $raw;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyGlobalSearch(array $rows, string $search): array
    {
        $needle = trim($search);
        if ($needle === '') {
            return $rows;
        }

        $needle = mb_strtolower($needle);

        return array_values(array_filter($rows, function (array $row) use ($needle): bool {
            return $this->matchesGlobalSearch($row, $needle);
        }));
    }

    private function matchesGlobalSearch(mixed $value, string $needle): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return str_contains($value ? 'true' : 'false', $needle);
        }

        if (is_scalar($value)) {
            return str_contains(mb_strtolower((string) $value), $needle);
        }

        if ($value instanceof \DateTimeInterface) {
            return str_contains(mb_strtolower($value->format('Y-m-d H:i:s')), $needle);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->matchesGlobalSearch($item, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function paginateList(array $rows, int $page, int $limit): array
    {
        $total = count($rows);
        if ($limit <= 0) {
            return [
                'page' => 1,
                'limit' => 0,
                'total' => $total,
                'data' => array_values($rows),
            ];
        }

        $offset = ($page - 1) * $limit;

        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'data' => array_values(array_slice($rows, $offset, $limit)),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function paginateServiceTypeAggregation(array $rows, int $page, int $limit): array
    {
        $paginatedServices = $this->paginateList($rows, $page, $limit);
        $paginatedData = [];

        foreach ($paginatedServices['data'] as $serviceEntry) {
            if (!is_array($serviceEntry)) {
                continue;
            }

            $types = $serviceEntry['types'] ?? [];
            $paginatedTypes = $this->paginateList($types, $page, $limit);

            $serviceEntry['types'] = $paginatedTypes['data'];
            $serviceEntry['typesPage'] = $paginatedTypes['page'];
            $serviceEntry['typesLimit'] = $paginatedTypes['limit'];
            $serviceEntry['typesTotal'] = $paginatedTypes['total'];

            $paginatedData[] = $serviceEntry;
        }

        $paginatedServices['data'] = $paginatedData;

        return $paginatedServices;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function formatGroupedSection(array $rows, int $page, int $limit, string $countField): array
    {
        $globalTotal = 0;
        foreach ($rows as $row) {
            $globalTotal += (int) ($row[$countField] ?? 0);
        }

        $paginated = $this->paginateList($rows, $page, $limit);
        $paginated['globalTotal'] = $globalTotal;

        return $paginated;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDetailedSection(int $total, array $data, int $page, int $limit): array
    {
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'data' => $data,
        ];
    }
}