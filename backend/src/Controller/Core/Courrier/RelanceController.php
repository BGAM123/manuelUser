<?php

namespace App\Controller\Core\Courrier;

use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\ReponseRepository;
use App\Repository\Cour\CourrierDepartRepository;
use App\Repository\Core\ServiceRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierArrive")]
class RelanceController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private TransmissionRepository $transmissionRepository,
        private CourrierDepartRepository $courrierDepartRepository,
        private ServiceRepository $serviceRepository,
        private AccessCheckerService $accessChecker,
        private ReponseRepository $reponseRepository,
    ) {}

    #[Route('/core/courrier/relances', name: 'app_core_courrier_relances', methods: ['GET'])]
    #[OA\Get(
        path: '/core/courrier/relances',
        summary: 'Lister les courriers entrants avec filtres et pagination (pour relances)',
        description: 'Retourne la liste des courriers entrants filtrés par date, catégories, type, priorité, année, service, etc. Identique à /core/courrier avec filtres supplémentaires pour les relances et détection automatique des dépassements de délai (délai par défaut: 7 jours). Le filtre "periode" permet de cibler une période exacte de retard basée sur la date d\'arrivée (dateArrivee).',
        tags: ['CourrierArrive'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'order_by', in: 'query', required: false, description: 'Specify the sort direction relative to the creation date. Use "ASC" or "DESC".', schema: new OA\Schema(type: 'string', enum: ['DESC', 'ASC'], default: 'DESC')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Filtre sur la date d\'arrivée - Date de début (format: YYYY-MM-DD HH:MM:SS). Filtre sur dateArrivee.', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Filtre sur la date d\'arrivée - Date de fin (format: YYYY-MM-DD HH:MM:SS). Filtre sur dateArrivee.', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date', in: 'query', required: false, description: 'Filtre sur une date d\'arrivée spécifique (format: YYYY-MM-DD). Filtre sur dateArrivee.', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'categorie', in: 'query', required: false, description: 'Filter by the category ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type_courrier', in: 'query', required: false, description: 'Filter by the courrier type ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'priorite', in: 'query', required: false, description: 'Filter by priority.', schema: new OA\Schema(type: 'string', enum: ['Basse', 'Normal', 'Haute'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'The page number for pagination.', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'The number of items per page. 0 to retrieve all.', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, description: 'Filter deleted courriers.', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Search string in reference, objet, commentaire, etc.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Filtre par année de date d\'arrivée (dateArrivee).', schema: new OA\Schema(type: 'integer')),
            // Filtres supplémentaires pour les relances
            new OA\Parameter(name: 'service', in: 'query', required: false, description: 'Filter by service ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'dateDebut', in: 'query', required: false, description: 'Filtre sur la date d\'arrivée - Date de début (format: Y-m-d). Filtre sur dateArrivee.', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateFin', in: 'query', required: false, description: 'Filtre sur la date d\'arrivée - Date de fin (format: Y-m-d). Filtre sur dateArrivee.', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'periode', in: 'query', required: false, description: 'Filtre les courriers selon une période exacte de retard (3j, 7j, 15j, 30j, 60j, 90j). Ex: "7j" retourne les courriers arrivés (dateArrivee) il y a exactement 7 jours.', schema: new OA\Schema(type: 'string', enum: ['3j', '7j', '15j', '30j', '60j', '90j'])),
            new OA\Parameter(name: 'delai_traitement', in: 'query', required: false, description: 'Délai de traitement en jours pour calculer les dépassements (défaut: 7 jours). Utilisé avec dateArrivee pour filtrer les courriers en dépassement.', schema: new OA\Schema(type: 'integer', default: 7)),
            new OA\Parameter(name: 'en_depassement', in: 'query', required: false, description: 'Filtrer uniquement les courriers en dépassement de délai (basé sur dateArrivee) (true) ou non (false).', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'delaiEcoule', in: 'query', required: false, description: 'Filtrer par delai ecoule exact en jours depuis createdAt.', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des courriers récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 45, description: 'Nombre total de courriers correspondant aux filtres appliquÃ©s'),
                        new OA\Property(
                            property: 'statistiques',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'retard_3_jours', type: 'integer', example: 12, description: 'Nombre total de courriers en retard de plus de 3 jours (arrivés + transmissions + départs)'),
                                new OA\Property(property: 'retard_7_jours', type: 'integer', example: 8, description: 'Nombre total de courriers en retard de plus de 7 jours (arrivés + transmissions + départs)'),
                                new OA\Property(property: 'retard_15_jours', type: 'integer', example: 5, description: 'Nombre total de courriers en retard de plus de 15 jours (arrivés + transmissions + départs)'),
                                new OA\Property(property: 'retard_30_jours', type: 'integer', example: 2, description: 'Nombre total de courriers en retard de plus de 30 jours (arrivés + transmissions + départs)'),
                                new OA\Property(
                                    property: 'detail',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'courriers_arrives',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'retard_3_jours', type: 'integer', example: 5),
                                                new OA\Property(property: 'retard_7_jours', type: 'integer', example: 3),
                                                new OA\Property(property: 'retard_15_jours', type: 'integer', example: 2),
                                                new OA\Property(property: 'retard_30_jours', type: 'integer', example: 1),
                                            ]
                                        ),
                                        new OA\Property(
                                            property: 'transmissions',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'retard_3_jours', type: 'integer', example: 4),
                                                new OA\Property(property: 'retard_7_jours', type: 'integer', example: 3),
                                                new OA\Property(property: 'retard_15_jours', type: 'integer', example: 2),
                                                new OA\Property(property: 'retard_30_jours', type: 'integer', example: 1),
                                            ]
                                        ),
                                        new OA\Property(
                                            property: 'courriers_depart',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'retard_3_jours', type: 'integer', example: 3),
                                                new OA\Property(property: 'retard_7_jours', type: 'integer', example: 2),
                                                new OA\Property(property: 'retard_15_jours', type: 'integer', example: 1),
                                                new OA\Property(property: 'retard_30_jours', type: 'integer', example: 0),
                                            ]
                                        )
                                    ]
                                )
                            ]
                        ),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'numero', type: 'string', example: 'C-2025-001'),
                                    new OA\Property(property: 'reference', type: 'string', example: 'CA-00045/2025'),
                                    new OA\Property(property: 'objet', type: 'string', example: 'Demande de subvention'),
                                    new OA\Property(property: 'priorite', type: 'string', example: 'Haute'),
                                    new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                                    new OA\Property(property: 'dateArrivee', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                                    new OA\Property(property: 'dateEnregistrement', type: 'string', format: 'date-time', example: '2025-02-14T08:15:00+00:00'),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T08:15:00+00:00'),
                                    new OA\Property(property: 'idServiceTraitant', type: 'string', example: 'Service Financier'),
                                    new OA\Property(property: 'categorieProvenance', type: 'string', example: 'Ministère'),
                                    new OA\Property(property: 'typeCourrier', type: 'string', example: 'Correspondance officielle'),
                                    new OA\Property(property: 'IdProvenance', type: 'string', example: 'Ministère des Finances'),
                                    new OA\Property(property: 'idCreateur', type: 'string', example: 'Jean Dupont'),
                                    new OA\Property(property: 'isConfidentiel', type: 'boolean', example: false),
                                    new OA\Property(property: 'delaiEcoule', type: 'integer', example: 10, description: 'Nombre de jours ecoules depuis createdAt'),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCourrierCollection');

        $filters = [
            'order_by' => $request->query->get('order_by', 'DESC'),
            'start_date' => $request->query->get('start_date'),
            'end_date' => $request->query->get('end_date'),
            'date' => $request->query->get('date'),
            'categorie' => $request->query->get('categorie'),
            'type_courrier' => $request->query->get('type_courrier'),
            'priorite' => $request->query->get('priorite'),
            'is_delete' => filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN),
            'search' => $request->query->get('search'),
            'year' => $request->query->get('year'),
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => (int)$request->query->get('limit', 10),
            // Filtres supplÃ©mentaires pour les relances
            'service' => $request->query->get('service'),
            'dateDebut' => $request->query->get('dateDebut'),
            'dateFin' => $request->query->get('dateFin'),
            'periode' => $request->query->get('periode'),
            'delai_traitement' => (int)$request->query->get('delai_traitement', 7), // DÃ©lai par dÃ©faut: 7 jours
            'en_depassement' => $request->query->has('en_depassement') 
                ? filter_var($request->query->get('en_depassement'), FILTER_VALIDATE_BOOLEAN) 
                : null,
            'delaiEcoule' => $request->query->has('delaiEcoule') ? (int)$request->query->get('delaiEcoule') : null,
        ];

        // Gestion de la pÃ©riode prÃ©dÃ©finie pour les dÃ©passements de dÃ©lai (pÃ©riode exacte)
        if (!empty($filters['periode'])) {
            $now = new \DateTime();
            switch ($filters['periode']) {
                case '3j':
                    // Exactement 3 jours de retard
                    $dateExacte = (clone $now)->modify('-3 days');
                    $filters['start_date'] = $dateExacte->format('Y-m-d 00:00:00');
                    $filters['end_date'] = $dateExacte->format('Y-m-d 23:59:59');
                    break;
                case '7j':
                    // Exactement 7 jours de retard
                    $dateExacte = (clone $now)->modify('-7 days');
                    $filters['start_date'] = $dateExacte->format('Y-m-d 00:00:00');
                    $filters['end_date'] = $dateExacte->format('Y-m-d 23:59:59');
                    break;
                case '15j':
                    // Exactement 15 jours de retard
                    $dateExacte = (clone $now)->modify('-15 days');
                    $filters['start_date'] = $dateExacte->format('Y-m-d 00:00:00');
                    $filters['end_date'] = $dateExacte->format('Y-m-d 23:59:59');
                    break;
                case '30j':
                    // Exactement 30 jours de retard
                    $dateExacte = (clone $now)->modify('-30 days');
                    $filters['start_date'] = $dateExacte->format('Y-m-d 00:00:00');
                    $filters['end_date'] = $dateExacte->format('Y-m-d 23:59:59');
                    break;
                case '60j':
                    // Exactement 60 jours de retard
                    $dateExacte = (clone $now)->modify('-60 days');
                    $filters['start_date'] = $dateExacte->format('Y-m-d 00:00:00');
                    $filters['end_date'] = $dateExacte->format('Y-m-d 23:59:59');
                    break;
                case '90j':
                    // Exactement 90 jours de retard
                    $dateExacte = (clone $now)->modify('-90 days');
                    $filters['start_date'] = $dateExacte->format('Y-m-d 00:00:00');
                    $filters['end_date'] = $dateExacte->format('Y-m-d 23:59:59');
                    break;
            }
        }

        // Ne pas Ã©craser start_date/end_date avec dateDebut/dateFin
        // Tous les autres filtres utilisent dateArrivee
        // start_date/end_date -> createdAt (avec heure)
        // dateDebut/dateFin -> dateArrivee (sans heure)
        // date -> dateArrivee (jour spÃ©cifique)
        // year -> dateArrivee (annÃ©e spÃ©cifique)
        // periode -> dateArrivee (X jours avant aujourd'hui)
        // en_depassement -> dateArrivee (selon delai_traitement)

        $queryBuilder = $this->courrierRepository->createQueryBuilder('c')
            ->leftJoin('c.idServiceTraitant', 's')
            ->leftJoin('c.idCreateur', 'u')
            ->addSelect('s', 'u')
            ->where('c.isDelete = :isDelete')
            ->setParameter('isDelete', $filters['is_delete']);

        // ðŸ” Recherche texte
        if (!empty($filters['search'])) {
            $queryBuilder->andWhere('LOWER(c.search) LIKE LOWER(:search)')
                         ->setParameter('search', '%' . trim($filters['search']) . '%');
        }

        // Filtres sur createdAt (start_date, end_date) et dateArrivee (date)
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $queryBuilder->andWhere('c.createdAt BETWEEN :startCreated AND :endCreated')
                         ->setParameter('startCreated', new \DateTime($filters['start_date']))
                         ->setParameter('endCreated', new \DateTime($filters['end_date']));
        } elseif (!empty($filters['date'])) {
            $start = new \DateTime($filters['date'] . ' 00:00:00');
            $end = new \DateTime($filters['date'] . ' 23:59:59');
            $queryBuilder->andWhere('c.dateArrivee BETWEEN :startArrivee AND :endArrivee')
                         ->setParameter('startArrivee', $start)
                         ->setParameter('endArrivee', $end);
        }

        // ðŸ“… Filtres sur dateArrivee (dateDebut, dateFin, year, periode)
        if (!empty($filters['dateDebut']) && !empty($filters['dateFin'])) {
            $startCreated = new \DateTime($filters['dateDebut'] . ' 00:00:00');
            $endCreated = new \DateTime($filters['dateFin'] . ' 23:59:59');
            $queryBuilder->andWhere('c.dateArrivee BETWEEN :startCreated AND :endCreated')
                         ->setParameter('startCreated', $startCreated)
                         ->setParameter('endCreated', $endCreated);
        } elseif (!empty($filters['year'])) {
            $start = new \DateTime($filters['year'] . '-01-01 00:00:00');
            $end = new \DateTime($filters['year'] . '-12-31 23:59:59');
            $queryBuilder->andWhere('c.dateArrivee BETWEEN :startCreated AND :endCreated')
                         ->setParameter('startCreated', $start)
                         ->setParameter('endCreated', $end);
        }

        // ðŸš¨ Le filtre par pÃ©riode est maintenant gÃ©rÃ© par start_date/end_date (pÃ©riode exacte)

        // ðŸš¨ Filtre par dÃ©passement de dÃ©lai
        if ($filters['en_depassement'] === true) {
            $delaiDate = new \DateTime();
            $delaiDate->modify('-' . $filters['delai_traitement'] . ' days');
            $queryBuilder->andWhere('c.dateArrivee <= :delaiDate')
                         ->setParameter('delaiDate', $delaiDate);
        } elseif ($filters['en_depassement'] === false) {
            $delaiDate = new \DateTime();
            $delaiDate->modify('-' . $filters['delai_traitement'] . ' days');
            $queryBuilder->andWhere('c.dateArrivee > :delaiDate')
                         ->setParameter('delaiDate', $delaiDate);
        }

        // ðŸš¨ Filtre par dÃ©lai Ã©coulÃ© exact
        if ($filters['delaiEcoule'] !== null) {
            $now = new \DateTime();
            $dateExacteDebut = (clone $now)->modify('-' . $filters['delaiEcoule'] . ' days')->format('Y-m-d 00:00:00');
            $dateExacteFin = (clone $now)->modify('-' . $filters['delaiEcoule'] . ' days')->format('Y-m-d 23:59:59');
            $queryBuilder->andWhere('c.dateArrivee BETWEEN :dateExacteDebut AND :dateExacteFin')
                         ->setParameter('dateExacteDebut', new \DateTime($dateExacteDebut))
                         ->setParameter('dateExacteFin', new \DateTime($dateExacteFin));
        }

        // ðŸ§© Filtres additionnels
        if (!empty($filters['priorite'])) {
            // Case-insensitive comparison for priority
            $queryBuilder->andWhere('LOWER(c.priorite) = LOWER(:priorite)')
                         ->setParameter('priorite', $filters['priorite']);
        }
        if (!empty($filters['categorie'])) {
            $queryBuilder->andWhere('c.idCategorie = :categorie')
                         ->setParameter('categorie', $filters['categorie']);
        }
        if (!empty($filters['type_courrier'])) {
            $queryBuilder->andWhere('c.idTypeCourrier = :type')
                         ->setParameter('type', $filters['type_courrier']);
        }

        // ðŸ¢ Filtre par service (spÃ©cifique aux relances)
        if (!empty($filters['service'])) {
            $queryBuilder->andWhere('s.id = :service')
                         ->setParameter('service', $filters['service']);
        }

        // ðŸ”„ Tri
        $queryBuilder->orderBy('c.dateArrivee', $filters['order_by']);

        // ðŸ“„ Pagination - Calculer le total avec les filtres appliquÃ©s
        $limit = $filters['limit'];
        $page = $filters['page'];
        
        // ðŸ”„ Calculer le TOTAL avec les filtres appliquÃ©s
        $totalQueryBuilder = clone $queryBuilder;
        $total = $totalQueryBuilder->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        
        // ðŸ”„ Calculer les statistiques globales uniquement sur les transmissions
        $now = new \DateTime();

        // Preparation iteration transmissions (accuse reception non recu)
        $transmissionQueryBuilder = $this->transmissionRepository->createQueryBuilder('t')
            ->leftJoin('t.idCourrier', 'c')
            ->addSelect('c')
            ->where('t.isDelete = :isDelete')
            ->setParameter('isDelete', false)
            ->orderBy('t.id', 'ASC');

        $transmissionsIterable = $transmissionQueryBuilder->getQuery()->toIterable();

        // PrÃ©-calculs pour limiter les allocations dans la boucle
        $searchNeedle = !empty($filters['search']) ? strtolower(trim($filters['search'])) : null;
        $prioriteNeedle = !empty($filters['priorite']) ? strtolower((string)$filters['priorite']) : null;
        $startCreated = !empty($filters['start_date']) ? new \DateTime($filters['start_date']) : null;
        $endCreated = !empty($filters['end_date']) ? new \DateTime($filters['end_date']) : null;

        $limit = $filters['limit'];
        $page = $filters['page'];
        $startIndex = max(0, ($page - 1) * $limit);
        $endIndex = $limit > 0 ? ($startIndex + $limit - 1) : PHP_INT_MAX;

        // Calculer les compteurs exclusifs et prÃ©parer la page sans stocker tout en mÃ©moire
        $totalRetard3j = 0;
        $totalRetard7j = 0;
        $totalRetard15j = 0;
        $totalRetard30j = 0;
        $totalMatched = 0;
        $data = [];

        $em = $this->transmissionRepository->getEntityManager();
        $i = 0;
        foreach ($transmissionsIterable as $t) {
            $tid = $t->getId();
            if ($tid === null) continue;
            if ($t->isAccuseReception()) continue;

            $createdAtT = $t->getCreatedAt();
            $delaiT = $t->getDelaiTraitement();
            if (!$createdAtT) continue;

            // effective delay: use transmission's delaiTraitement or fallback to request filter
            $effectiveDelai = $delaiT !== null ? (int)$delaiT : (int)$filters['delai_traitement'];

            $dueDate = (clone $createdAtT)->modify('+' . $effectiveDelai . ' days');

            // Compute overdue flag and days
            $isOverdue = $now > $dueDate;
            $overdueDays = $isOverdue ? (int)$dueDate->diff($now)->days : 0;
            $daysElapsed = (int)$createdAtT->diff($now)->days;

            // Apply en_depassement filter if provided
            if ($filters['en_depassement'] === true && !$isOverdue) continue;
            if ($filters['en_depassement'] === false && $isOverdue) continue;

            // Apply delaiEcoule filter (exact days since createdAt)
            if ($filters['delaiEcoule'] !== null && $daysElapsed !== (int)$filters['delaiEcoule']) continue;

            $c = $t->getIdCourrier();
            if (!$c) continue;

            // Apply service filter
            if (!empty($filters['service'])) {
                $serviceId = $c->getIdServiceTraitant()?->getId();
                if ($serviceId === null || (int)$serviceId !== (int)$filters['service']) continue;
            }

            // Apply priorite filter (case-insensitive)
            if ($prioriteNeedle !== null) {
                $courrierPriorite = strtolower((string)$c->getPriorite());
                if ($courrierPriorite !== $prioriteNeedle) continue;
            }

            // Apply search filter (check numero, reference, objet)
            if ($searchNeedle !== null) {
                $hay = strtolower(implode(' ', [
                    $c->getNumero() ?? '',
                    $c->getReference() ?? '',
                    $c->getObjet() ?? ''
                ]));
                if (strpos($hay, $searchNeedle) === false) continue;
            }

            // Apply createdAt period filters if present (start_date/end_date)
            if ($startCreated !== null && $endCreated !== null) {
                $createdAtC = $c->getCreatedAt();
                if (!$createdAtC) continue;
                if ($createdAtC < $startCreated || $createdAtC > $endCreated) continue;
            }

            // Mettre a jour les statistiques globales (delai ecoule en jours)
            // 0-6 jours, 7-14 jours, 15-29 jours, 30 jours et plus
            if ($daysElapsed <= 6) {
                $totalRetard3j++;
            } elseif ($daysElapsed <= 14) {
                $totalRetard7j++;
            } elseif ($daysElapsed <= 29) {
                $totalRetard15j++;
            } else {
                $totalRetard30j++;
            }

            $currentIndex = $totalMatched;
            $totalMatched++;

            // Pagination sur les transmissions sans stocker tout le tableau
            if ($limit === 0 || ($currentIndex >= $startIndex && $currentIndex <= $endIndex)) {
                $data[] = [
                    'transmissionId' => $t->getId(),
                    'id' => $c->getId(),
                    'numero' => $c->getNumero(),
                    'reference' => $c->getReference(),
                    'objet' => $c->getObjet(),
                    'priorite' => $c->getPriorite(),
                    'statut' => $c->getStatut(),
                    'dateArrivee' => $c->getDateArrivee()?->format('Y-m-d H:i:s'),
                    'dateEnregistrement' => $c->getDateEnregistrement()?->format('Y-m-d H:i:s'),
                    'createdAt' => $createdAtT?->format('Y-m-d H:i:s'),
                    'idServiceTraitant' => $c->getIdServiceTraitant()?->getNom(),
                    'service_id' => $c->getIdServiceTraitant()?->getId(),
                    'categorieProvenance' => $c->getIdProvenance() && $c->getIdProvenance()->getCategories()->count() > 0 
                        ? $c->getIdProvenance()->getCategories()->first()->getNom()
                        : null,
                    'typeCourrier' => $c->getTypeCourrier()?->getNom(),
                    'IdProvenance' => $c->getIdProvenance()?->getNom(),
                    'idCreateur' => $c->getIdCreateur()?->getFullName(),
                    'isConfidentiel' => $c->isConfidentiel(),
                    // delaiEcoule = nombre de jours ecoules depuis createdAt
                    'delaiEcoule' => $daysElapsed,
                ];
            }

            // LibÃ©rer pÃ©riodiquement la mÃ©moire des entitÃ©s Doctrine
            $i++;
            if ($i % 100 === 0) {
                $em->clear();
            }
        }

        // total = nombre de transmissions correspondantes
        $total = $totalMatched;

        // Courriers depart transmis (non supprimes / non archives)
        $courriersDepartTransmisQb = $this->courrierDepartRepository->createQueryBuilder('cd')
            ->leftJoin('cd.destinataire', 'cd_dest')
            ->leftJoin('cd.idSignataire', 'cd_sign')
            ->leftJoin('cd.idCourrier', 'cd_courrier')
            ->addSelect('cd_dest', 'cd_sign', 'cd_courrier')
            ->where('cd.isDelete = :cdIsDelete')
            ->andWhere('cd.isArchive = :cdIsArchive')
            ->andWhere('LOWER(cd.statut) = LOWER(:cdStatut)')
            ->setParameter('cdIsDelete', false)
            ->setParameter('cdIsArchive', false)
            ->setParameter('cdStatut', 'Transmis')
            ->orderBy('cd.createdAt', 'DESC');

        $courriersDepartTransmisTotal = (int) (clone $courriersDepartTransmisQb)
            ->select('COUNT(cd.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        if ($limit > 0) {
            $courriersDepartTransmisQb
                ->setMaxResults($limit)
                ->setFirstResult(($page - 1) * $limit);
        }

        $courriersDepartTransmis = $courriersDepartTransmisQb->getQuery()->getResult();
        $courriersDepartTransmisData = array_map(function ($cd) {
            return [
                'id' => $cd->getId(),
                'numeroReference' => $cd->getNumeroReference(),
                'numeroActe' => $cd->getNumeroActe(),
                'statut' => $cd->getStatut(),
                'dateSignature' => $cd->getDateSignature()?->format('Y-m-d'),
                'typeCourrier' => $cd->getTypeCourrier(),
                'classeCourrier' => $cd->getClasseCourrier(),
                'categorie' => $cd->getCategorie(),
                'document' => $cd->getDocument(),
                'email' => $cd->getEmail(),
                'numeroTelephone' => $cd->getNumeroTelephone(),
                'nombrePieceJointe' => $cd->getNombrePieceJointe(),
                'destinataire' => $cd->getDestinataire() ? [
                    'id' => $cd->getDestinataire()?->getId(),
                    'nom' => $cd->getDestinataire()?->getNom(),
                ] : null,
                'signataire' => $cd->getIdSignataire() ? [
                    'id' => $cd->getIdSignataire()?->getId(),
                    'fullName' => $cd->getIdSignataire()?->getFullName(),
                ] : null,
                'courrier' => $cd->getIdCourrier() ? [
                    'id' => $cd->getIdCourrier()?->getId(),
                    'numero' => $cd->getIdCourrier()?->getNumero(),
                    'reference' => $cd->getIdCourrier()?->getNumero(),
                    'objet' => $cd->getIdCourrier()?->getObjet(),
                    'dateArrivee' => $cd->getIdCourrier()?->getDateArrivee()?->format('Y-m-d'),
                    'priorite' => $cd->getIdCourrier()?->getPriorite(),
                ] : null,
                'createdAt' => $cd->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $courriersDepartTransmis);
        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total, // Total avec filtres appliquÃ©s
            'statistiques' => [
                // Statistiques globales basÃ©es uniquement sur les transmissions non rÃ©pondues
                'retard_3_jours' => (int)$totalRetard3j,
                'retard_7_jours' => (int)$totalRetard7j,
                'retard_15_jours' => (int)$totalRetard15j,
                'retard_30_jours' => (int)$totalRetard30j,
            ],
            'courriers_depart_transmis_count' => $courriersDepartTransmisTotal,
            'courriers_depart_transmis' => $courriersDepartTransmisData,
            'data' => $data
        ], 200);
    }

    #[Route('/core/courrier/relances/statistiques', name: 'app_core_courrier_relances_stats', methods: ['GET'])]
    #[OA\Get(
        path: '/core/courrier/relances/statistiques',
        summary: 'Statistiques des relances par période de dépassement',
        description: 'Retourne le nombre de courriers en dépassement selon différentes périodes (7j, 15j, 30j, 60j, 90j) basé sur la date d\'arrivée (dateArrivee).',
        tags: ['CourrierArrive'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'service', in: 'query', required: false, description: 'Filter by service ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'dateDebut', in: 'query', required: false, description: 'Filtre sur la date d\'arrivée - Date de début (format: Y-m-d).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateFin', in: 'query', required: false, description: 'Filtre sur la date d\'arrivée - Date de fin (format: Y-m-d).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'categorie', in: 'query', required: false, description: 'Filter by the category ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type_courrier', in: 'query', required: false, description: 'Filter by the courrier type ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'priorite', in: 'query', required: false, description: 'Filter by priority.', schema: new OA\Schema(type: 'string', enum: ['Basse', 'Normal', 'Haute'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistiques des relances récupérées avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total_en_depassement', type: 'integer', example: 125, description: 'Nombre total de courriers en dépassement (> 3 jours)'),
                                new OA\Property(
                                    property: 'par_periode',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: '3_jours',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'label', type: 'string', example: '3 à 7 jours'),
                                                new OA\Property(property: 'nombre', type: 'integer', example: 30),
                                                new OA\Property(property: 'pourcentage', type: 'number', format: 'float', example: 24.0)
                                            ]
                                        ),
                                        new OA\Property(
                                            property: '7_jours',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'label', type: 'string', example: '7 à 15 jours'),
                                                new OA\Property(property: 'nombre', type: 'integer', example: 45),
                                                new OA\Property(property: 'pourcentage', type: 'number', format: 'float', example: 36.0)
                                            ]
                                        ),
                                        new OA\Property(
                                            property: '15_jours',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'label', type: 'string', example: '15 à 30 jours'),
                                                new OA\Property(property: 'nombre', type: 'integer', example: 35),
                                                new OA\Property(property: 'pourcentage', type: 'number', format: 'float', example: 28.0)
                                            ]
                                        ),
                                        new OA\Property(
                                            property: '30_jours',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'label', type: 'string', example: '30 à 60 jours'),
                                                new OA\Property(property: 'nombre', type: 'integer', example: 25),
                                                new OA\Property(property: 'pourcentage', type: 'number', format: 'float', example: 20.0)
                                            ]
                                        ),
                                        new OA\Property(
                                            property: '60_jours',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'label', type: 'string', example: '60 à 90 jours'),
                                                new OA\Property(property: 'nombre', type: 'integer', example: 15),
                                                new OA\Property(property: 'pourcentage', type: 'number', format: 'float', example: 12.0)
                                            ]
                                        ),
                                        new OA\Property(
                                            property: '90_jours_plus',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'label', type: 'string', example: 'Plus de 90 jours'),
                                                new OA\Property(property: 'nombre', type: 'integer', example: 5),
                                                new OA\Property(property: 'pourcentage', type: 'number', format: 'float', example: 4.0)
                                            ]
                                        )
                                    ]
                                ),
                                new OA\Property(
                                    property: 'repartition_par_service',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'service_id', type: 'integer', example: 1),
                                            new OA\Property(property: 'service_nom', type: 'string', example: 'Service Financier'),
                                            new OA\Property(property: 'nombre_relances', type: 'integer', example: 15)
                                        ]
                                    )
                                )
                            ]
                        ),
                        new OA\Property(property: 'message', type: 'string', example: 'Statistiques récupérées avec succès')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function statistiques(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCourrierCollection');

        $filters = [
            'service' => $request->query->get('service'),
            'dateDebut' => $request->query->get('dateDebut'),
            'dateFin' => $request->query->get('dateFin'),
            'categorie' => $request->query->get('categorie'),
            'type_courrier' => $request->query->get('type_courrier'),
            'priorite' => $request->query->get('priorite'),
        ];

        // CrÃ©er la requÃªte de base
        $queryBuilder = $this->courrierRepository->createQueryBuilder('c')
            ->leftJoin('c.idServiceTraitant', 's')
            ->addSelect('s')
            ->where('c.isDelete = :isDelete')
            ->setParameter('isDelete', false);

        // Appliquer les filtres
        if (!empty($filters['dateDebut']) && !empty($filters['dateFin'])) {
            $startDate = new \DateTime($filters['dateDebut'] . ' 00:00:00');
            $endDate = new \DateTime($filters['dateFin'] . ' 23:59:59');
            $queryBuilder->andWhere('c.dateArrivee BETWEEN :startDate AND :endDate')
                         ->setParameter('startDate', $startDate)
                         ->setParameter('endDate', $endDate);
        }

        if (!empty($filters['service'])) {
            $queryBuilder->andWhere('s.id = :service')
                         ->setParameter('service', $filters['service']);
        }

        if (!empty($filters['priorite'])) {
            $queryBuilder->andWhere('c.priorite = :priorite')
                         ->setParameter('priorite', $filters['priorite']);
        }

        if (!empty($filters['categorie'])) {
            $queryBuilder->andWhere('c.idCategorie = :categorie')
                         ->setParameter('categorie', $filters['categorie']);
        }

        if (!empty($filters['type_courrier'])) {
            $queryBuilder->andWhere('c.idTypeCourrier = :type')
                         ->setParameter('type', $filters['type_courrier']);
        }

        // DÃ©finir les dates limites pour chaque pÃ©riode
        $now = new \DateTime();
        $date3j = (clone $now)->modify('-3 days');
        $date7j = (clone $now)->modify('-7 days');
        $date15j = (clone $now)->modify('-15 days');
        $date30j = (clone $now)->modify('-30 days');
        $date60j = (clone $now)->modify('-60 days');
        $date90j = (clone $now)->modify('-90 days');

        // Compter les courriers par pÃ©riode
        // 3 Ã  7 jours
        $qb3j = clone $queryBuilder;
        $count3j = $qb3j
            ->select('COUNT(c.id)')
            ->andWhere('c.dateArrivee <= :date7j')
            ->andWhere('c.dateArrivee > :date3j')
            ->setParameter('date7j', $date7j)
            ->setParameter('date3j', $date3j)
            ->getQuery()
            ->getSingleScalarResult();

        // 7 Ã  15 jours
        $qb7j = clone $queryBuilder;
        $count7j = $qb7j
            ->select('COUNT(c.id)')
            ->andWhere('c.dateArrivee <= :date15j')
            ->andWhere('c.dateArrivee > :date7j')
            ->setParameter('date15j', $date15j)
            ->setParameter('date7j', $date7j)
            ->getQuery()
            ->getSingleScalarResult();

        // 15 Ã  30 jours
        $qb15j = clone $queryBuilder;
        $count15j = $qb15j
            ->select('COUNT(c.id)')
            ->andWhere('c.dateArrivee <= :date30j')
            ->andWhere('c.dateArrivee > :date15j')
            ->setParameter('date30j', $date30j)
            ->setParameter('date15j', $date15j)
            ->getQuery()
            ->getSingleScalarResult();

        // 30 Ã  60 jours
        $qb30j = clone $queryBuilder;
        $count30j = $qb30j
            ->select('COUNT(c.id)')
            ->andWhere('c.dateArrivee <= :date60j')
            ->andWhere('c.dateArrivee > :date30j')
            ->setParameter('date60j', $date60j)
            ->setParameter('date30j', $date30j)
            ->getQuery()
            ->getSingleScalarResult();

        // 60 Ã  90 jours
        $qb60j = clone $queryBuilder;
        $count60j = $qb60j
            ->select('COUNT(c.id)')
            ->andWhere('c.dateArrivee <= :date90j')
            ->andWhere('c.dateArrivee > :date60j')
            ->setParameter('date90j', $date90j)
            ->setParameter('date60j', $date60j)
            ->getQuery()
            ->getSingleScalarResult();

        // Plus de 90 jours
        $qb90jPlus = clone $queryBuilder;
        $count90jPlus = $qb90jPlus
            ->select('COUNT(c.id)')
            ->andWhere('c.dateArrivee <= :date90j')
            ->setParameter('date90j', $date90j)
            ->getQuery()
            ->getSingleScalarResult();

        // Total en dÃ©passement (> 3 jours)
        $totalEnDepassement = $count3j + $count7j + $count15j + $count30j + $count60j + $count90jPlus;

        // Calculer les pourcentages
        $calculatePercentage = function($count) use ($totalEnDepassement) {
            return $totalEnDepassement > 0 
                ? round(($count / $totalEnDepassement) * 100, 1) 
                : 0;
        };

        // RÃ©partition par service
        $qbServices = clone $queryBuilder;
        $servicesData = $qbServices
            ->select('s.id as service_id, s.nom as service_nom, COUNT(c.id) as nombre_relances')
            ->andWhere('c.dateArrivee <= :date3j')
            ->setParameter('date3j', $date3j)
            ->groupBy('s.id, s.nom')
            ->orderBy('nombre_relances', 'DESC')
            ->getQuery()
            ->getResult();

        $repartitionParService = array_map(function($row) {
            return [
                'service_id' => $row['service_id'],
                'service_nom' => $row['service_nom'],
                'nombre_relances' => (int) $row['nombre_relances']
            ];
        }, $servicesData);

        return $this->json([
            'success' => true,
            'data' => [
                'total_en_depassement' => (int) $totalEnDepassement,
                'par_periode' => [
                    '3_jours' => [
                        'label' => '3 Ã  7 jours',
                        'nombre' => (int) $count3j,
                        'pourcentage' => $calculatePercentage($count3j)
                    ],
                    '7_jours' => [
                        'label' => '7 Ã  15 jours',
                        'nombre' => (int) $count7j,
                        'pourcentage' => $calculatePercentage($count7j)
                    ],
                    '15_jours' => [
                        'label' => '15 Ã  30 jours',
                        'nombre' => (int) $count15j,
                        'pourcentage' => $calculatePercentage($count15j)
                    ],
                    '30_jours' => [
                        'label' => '30 Ã  60 jours',
                        'nombre' => (int) $count30j,
                        'pourcentage' => $calculatePercentage($count30j)
                    ],
                    '60_jours' => [
                        'label' => '60 Ã  90 jours',
                        'nombre' => (int) $count60j,
                        'pourcentage' => $calculatePercentage($count60j)
                    ],
                    '90_jours_plus' => [
                        'label' => 'Plus de 90 jours',
                        'nombre' => (int) $count90jPlus,
                        'pourcentage' => $calculatePercentage($count90jPlus)
                    ]
                ],
                'repartition_par_service' => $repartitionParService
            ],
            'message' => 'Statistiques récupérées avec succès'
        ], 200);
    }
}





