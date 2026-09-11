<?php

namespace App\Controller\Core\Transmission;

use App\Entity\Core\User;
use App\Entity\Cour\Transmission;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\ReponseRepository;
use App\Repository\Core\NotificationRepository;
use App\Repository\Core\UserRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\QueryBuilder;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Transmission")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private TransmissionRepository $transmissionRepository,
        private ReponseRepository $reponseRepository,
        private UserRepository $userRepository,
        private AccessCheckerService $accessChecker,
        private PieceJointeRepository $pieceJointeRepository,
        private NotificationRepository $notificationRepository,
    ) {}

    private function getNotificationIdByTransmissionId(array $transmissions, ?int $serviceId): array
    {
        if (!$serviceId) {
            return [];
        }

        $transmissionIds = [];
        foreach ($transmissions as $t) {
            if ($t instanceof Transmission && $t->getId()) {
                $transmissionIds[] = (int) $t->getId();
            }
        }
        $transmissionIds = array_values(array_unique($transmissionIds));

        if (empty($transmissionIds)) {
            return [];
        }

        $queryBuilder = $this->notificationRepository->createQueryBuilder('n')
            ->where('IDENTITY(n.service) = :serviceId')
            ->andWhere('n.type IN (:types)')
            ->setParameter('serviceId', $serviceId)
            ->setParameter('types', ['transmission', 'transmission_add', 'transmission_copie']);

        $orX = $queryBuilder->expr()->orX();
        foreach ($transmissionIds as $idx => $id) {
            // data est stocké en JSON : éviter les collisions (ex: 12 dans 123) en cherchant une fin `,` ou `}`.
            $orX->add("n.data LIKE :tid_{$idx}_num_comma");
            $orX->add("n.data LIKE :tid_{$idx}_num_end");
            $orX->add("n.data LIKE :tid_{$idx}_str_comma");
            $orX->add("n.data LIKE :tid_{$idx}_str_end");

            $queryBuilder
                // MySQL affiche souvent le JSON avec des espaces (ex: "transmission_id": 123).
                // Le % après ":" rend la recherche tolérante aux espaces/tabs.
                ->setParameter("tid_{$idx}_num_comma", '%"transmission_id":%' . $id . ',%')
                ->setParameter("tid_{$idx}_num_end", '%"transmission_id":%' . $id . '}%')
                ->setParameter("tid_{$idx}_str_comma", '%"transmission_id":%"' . $id . '",%')
                ->setParameter("tid_{$idx}_str_end", '%"transmission_id":%"' . $id . '"}%');
        }

        $queryBuilder
            ->andWhere($orX)
            ->orderBy('n.createdAt', 'DESC');

        $notifications = $queryBuilder->getQuery()->getResult();

        $map = [];
        foreach ($notifications as $notification) {
            if (!$notification instanceof \App\Entity\Core\Notification) {
                continue;
            }
            $data = $notification->getData() ?? [];
            $transmissionId = $data['transmission_id'] ?? null;
            if (!is_numeric($transmissionId)) {
                continue;
            }
            $transmissionId = (int) $transmissionId;
            if (!in_array($transmissionId, $transmissionIds, true)) {
                continue;
            }
            // On garde la plus récente (ORDER BY createdAt DESC)
            $map[$transmissionId] ??= [
                'id' => $notification->getId(),
                'is_read' => $notification->isRead(),
            ];
        }

        return $map;
    }

    private function applyGlobalSearch(
        QueryBuilder $queryBuilder,
        ?string $search,
        string $transmissionAlias,
        string $courrierAlias,
        string $typeCourrierAlias,
        string $provenanceAlias,
        string $categorieAlias,
        string $serviceDestAlias,
        string $emetteurAlias,
        string $emetteurServiceAlias,
        string $parameterName,
    ): void {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        // Si la recherche est une annÃ©e (YYYY), rÃ©utiliser la logique du filtre `year`
        // existant: filtrage sur la date d'arrivÃ©e du courrier.
        $isYearSearch = preg_match('/^\d{4}$/', $search) === 1;
        if ($isYearSearch) {
            $year = (int) $search;
            if ($year >= 1900 && $year <= 2100) {
                $startOfYear = (new \DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
                $endOfYear = (new \DateTimeImmutable())->setDate($year, 12, 31)->setTime(23, 59, 59);

                $queryBuilder
                    ->andWhere("{$courrierAlias}.dateArrivee >= :{$parameterName}_year_start AND {$courrierAlias}.dateArrivee <= :{$parameterName}_year_end")
                    ->setParameter("{$parameterName}_year_start", $startOfYear)
                    ->setParameter("{$parameterName}_year_end", $endOfYear);

                return;
            }
        }

        $orX = $queryBuilder->expr()->orX();

        $searchFields = [
            "{$transmissionAlias}.search",
            "{$transmissionAlias}.instruction",
            "{$transmissionAlias}.typeTransfert",
            "{$transmissionAlias}.statut",
            "{$transmissionAlias}.statutArchive",
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
            "{$typeCourrierAlias}.search",
            "{$typeCourrierAlias}.nom",
            "{$typeCourrierAlias}.type",
            "{$typeCourrierAlias}.classeCourrier",
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
            "{$serviceDestAlias}.search",
            "{$serviceDestAlias}.nom",
            "{$serviceDestAlias}.sigle",
            "{$emetteurAlias}.search",
            "{$emetteurAlias}.username",
            "{$emetteurAlias}.email",
            "{$emetteurAlias}.firstName",
            "{$emetteurAlias}.lastName",
            "{$emetteurAlias}.phone",
            "{$emetteurServiceAlias}.search",
            "{$emetteurServiceAlias}.nom",
            "{$emetteurServiceAlias}.sigle",
        ];

        foreach ($searchFields as $field) {
            $orX->add("LOWER({$field}) LIKE LOWER(:{$parameterName})");
        }

        if (ctype_digit($search)) {
            $orX->add("{$transmissionAlias}.id = :{$parameterName}_number");
            $orX->add("{$transmissionAlias}.delaiTraitement = :{$parameterName}_number");
            $orX->add("{$transmissionAlias}.nombrePieceJointe = :{$parameterName}_number");
            $orX->add("{$courrierAlias}.id = :{$parameterName}_number");
            $orX->add("{$serviceDestAlias}.id = :{$parameterName}_number");
            $orX->add("{$emetteurAlias}.id = :{$parameterName}_number");
            $orX->add("{$provenanceAlias}.id = :{$parameterName}_number");
            $orX->add("{$categorieAlias}.id = :{$parameterName}_number");
            $orX->add("{$typeCourrierAlias}.id = :{$parameterName}_number");
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
            $orX->add("({$transmissionAlias}.dateInstruction >= :{$parameterName}_date_start AND {$transmissionAlias}.dateInstruction <= :{$parameterName}_date_end)");
            $orX->add("({$transmissionAlias}.dateReception >= :{$parameterName}_date_start AND {$transmissionAlias}.dateReception <= :{$parameterName}_date_end)");
            $orX->add("({$transmissionAlias}.createdAt >= :{$parameterName}_date_start AND {$transmissionAlias}.createdAt <= :{$parameterName}_date_end)");
            $orX->add("({$transmissionAlias}.updatedAt >= :{$parameterName}_date_start AND {$transmissionAlias}.updatedAt <= :{$parameterName}_date_end)");

            $queryBuilder
                ->setParameter("{$parameterName}_date_start", $startOfDay)
                ->setParameter("{$parameterName}_date_end", $endOfDay);
        }

        $queryBuilder
            ->andWhere($orX)
            ->setParameter($parameterName, '%' . $search . '%');
    }

    /**
     * Paginate une requête Doctrine qui peut contenir des jointures "to-many" (ex: catégories),
     * afin d'éviter les écarts entre COUNT et résultats paginés.
     *
     * @return array{total:int, results: Transmission[]}
     */
    private function paginateTransmissionQuery(QueryBuilder $queryBuilder, int $page, int $limit, string $orderByDirection): array
    {
        $orderByDirection = strtoupper($orderByDirection) === 'ASC' ? 'ASC' : 'DESC';
        $page = max(1, $page);

        $total = (int) (clone $queryBuilder)
            ->select('COUNT(DISTINCT t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total <= 0) {
            return ['total' => 0, 'results' => []];
        }

        // MySQL 8+ (error 3065): avec DISTINCT, toutes les colonnes du ORDER BY doivent être dans le SELECT.
        // Le queryBuilder d'entrée est trié par `t.dateInstruction`, donc on l'ajoute en HIDDEN pour garder
        // l'ordre et permettre la pagination sans casser l'hydratation.
        $idQueryBuilder = (clone $queryBuilder)
            ->select('DISTINCT t.id')
            ->addSelect('t.dateInstruction AS HIDDEN sortDateInstruction');
        if ($limit > 0) {
            $idQueryBuilder->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }

        $idRows = $idQueryBuilder->getQuery()->getScalarResult();
        $ids = [];
        foreach ($idRows as $row) {
            $value = is_array($row) ? (array_values($row)[0] ?? null) : null;
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }
        $ids = array_values(array_filter($ids, static fn (int $id) => $id > 0));

        if (empty($ids)) {
            return ['total' => $total, 'results' => []];
        }

        $fetchQueryBuilder = $this->transmissionRepository->createQueryBuilder('t')
            ->leftJoin('t.idCourrier', 'c')
            ->leftJoin('c.typeCourrier', 'tc')
            ->leftJoin('c.idProvenance', 'prov')
            ->leftJoin('prov.categories', 'cat')
            ->leftJoin('t.idServiceDestinataire', 'sd')
            ->leftJoin('t.idEmetteur', 'e')
            ->leftJoin('e.idService', 'es')
            ->addSelect('c', 'tc', 'prov', 'cat', 'sd', 'e', 'es')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('t.dateInstruction', $orderByDirection);

        $entities = $fetchQueryBuilder->getQuery()->getResult();
        $byId = [];
        foreach ($entities as $entity) {
            if ($entity instanceof Transmission && $entity->getId()) {
                $byId[(int) $entity->getId()] = $entity;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return ['total' => $total, 'results' => $ordered];
    }

    #[Route('/core/transmission', name: 'app_core_transmission_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/transmission',
        summary: 'Lister les transmissions avec filtres et pagination',
        description: 'Retourne la liste des transmissions filtrées par courrier, service, date, statut, etc. Par défaut, filtre les transmissions du service principal de l\'utilisateur connecté ET des services additionnels. Inclut également les transmissions où le service est en copie (structuresCopie). La réponse inclut un bloc séparé "data_services_additionel" pour les transmissions des services additionnels.
        
        CHAMP "canTransmit" : Chaque transmission contient un champ booléen `canTransmit` qui indique si l\'utilisateur connecté peut créer une nouvelle transmission pour ce courrier :
        - `canTransmit = false` : Si l\'utilisateur connecté a DÉJÀ transmis ce courrier (où il est émetteur) → Bouton masqué côté frontend
        - `canTransmit = true` : Si l\'utilisateur connecté n`\'a JAMAIS transmis ce courrier → Bouton affiché côté frontend
        
        CHAMP "reponses" : Chaque transmission contient un tableau `reponses` qui liste toutes les réponses associées à cette transmission avec leurs détails complets (objet, date, rédacteur, service destinataire, courriers liés, etc.).',
        tags: ['Transmission'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'order_by', in: 'query', required: false, description: 'Sort direction (ASC or DESC).', schema: new OA\Schema(type: 'string', enum: ['DESC', 'ASC'], default: 'DESC')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Filter from this date (format: YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Filter to this date (format: YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date', in: 'query', required: false, description: 'Filter on specific date (format: YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'priorite', in: 'query', required: false, description: 'Filter by priority.', schema: new OA\Schema(type: 'string', enum: ['Toutes', 'Basse', 'Normal', 'Haute'])),
            new OA\Parameter(name: 'categorie', in: 'query', required: false, description: 'Filter by category ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type_courrier', in: 'query', required: false, description: 'Filter by courrier type ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'courrier', in: 'query', required: false, description: 'Filter by courrier ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'provenance', in: 'query', required: false, description: 'Filter by provenance ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'service_destinataire', in: 'query', required: false, description: 'Filter by destination service ID. Par défaut, utilise le service de l\'utilisateur connecté. Inclut les transmissions où le service est destinataire principal OU en copie (structuresCopie).', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'emetteur', in: 'query', required: false, description: 'Filter by emetteur (user) ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'statut', in: 'query', required: false, description: 'Filter by status.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type_transfert', in: 'query', required: false, description: 'Filter by type of transfer.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'accuse_reception', in: 'query', required: false, description: 'Filter by acknowledgment status.', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'isinstance', in: 'query', required: false, description: 'Filter by instance status (true = en instance, false = pas en instance).', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'isgeled', in: 'query', required: false, description: 'Filter by frozen status (true = courriers gélés, false = courriers non gelés).', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'dernier_poste', in: 'query', required: false, description: 'Filter by last position (service ID of the last transmission).', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number.', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Items per page. 0 to retrieve all.', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, description: 'Filter deleted transmissions.', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'isarchive', in: 'query', required: false, description: 'Filter archived transmissions. By default, returns only non-archived (false).', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Recherche globale sur tous les champs pertinents des transmissions et des tables liées (courrier, type, provenance, service, émetteur), incluant le numéro, la date d\'arrivée, la date d\'enregistrement, et la recherche par année.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Filter by year of courrier arrival.', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200, 
                description: 'Liste des transmissions récupérée avec succès. Inclut les transmissions du service principal et des services additionnels.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 50),
                        new OA\Property(property: 'total_transmis', type: 'integer', example: 25, description: 'Nombre total de transmissions avec le statut "Transmis"'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object'), description: 'Transmissions du service principal'),
                        new OA\Property(
                            property: 'data_services_additionel',
                            type: 'array',
                            description: 'Transmissions des services additionnels de l\'utilisateur avec pagination indépendante (vide si aucun service additionnel ou si filtre service_destinataire est spécifié)',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'service_id', type: 'integer', example: 5),
                                    new OA\Property(property: 'page', type: 'integer', example: 1),
                                    new OA\Property(property: 'limit', type: 'integer', example: 10),
                                    new OA\Property(property: 'total', type: 'integer', example: 150, description: 'Nombre total de transmissions pour ce service additionnel'),
                                    new OA\Property(property: 'total_transmis_add', type: 'integer', example: 7, description: 'Nombre de transmissions avec le statut "Transmis" dans la page actuelle'),
                                    new OA\Property(property: 'transmissions', type: 'array', items: new OA\Items(type: 'object'))
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'data_transmissions_copie', 
                            type: 'array',
                            description: 'Transmissions  les services de l\'utilisateur (principal + additionnels) sont EN COPIE avec pagination indépendante (vide si filtre service_destinataire est spécifié)',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'service_id', type: 'integer', example: 179),
                                    new OA\Property(property: 'page', type: 'integer', example: 1),
                                    new OA\Property(property: 'limit', type: 'integer', example: 10),
                                    new OA\Property(property: 'total', type: 'integer', example: 85, description: 'Nombre total de transmissions en copie pour ce service'),
                                    new OA\Property(property: 'total_transmis_cp', type: 'integer', example: 3, description: 'Nombre de transmissions avec le statut "Transmis" dans la page actuelle'),
                                    new OA\Property(property: 'transmissions', type: 'array', items: new OA\Items(type: 'object'))
                                ]
                            )
                        ),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 0, description: 'Nombre de pièces jointes')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTransmissionCollection');

        
        // RÃ©cupÃ©rer l'ID du service de l'utilisateur connectÃ©
        $user = $this->getUser();
        if ($user instanceof User) {
        
            $userServiceId = null;
            $userServicesAdditionel = [];
            if ($user && method_exists($user, 'getIdService') && $user->getIdService()) {
                $userServiceId = $user->getIdService()->getId();
            }
            // RÃ©cupÃ©rer les services additionnels de l'utilisateur
            if ($user && method_exists($user, 'getServicesAdditionel') && $user->getServicesAdditionel()) {
                $userServicesAdditionel = $user->getServicesAdditionel();
            }
        }
        // Si service_destinataire n'est pas fourni dans la requÃªte, utiliser le service de l'utilisateur par dÃ©faut
        $serviceDestinataireParam = $request->query->get('service_destinataire');
        $defaultServiceDestinataire = $serviceDestinataireParam ?? $userServiceId;

        $filters = [
            'order_by' => $request->query->get('order_by', 'DESC'),
            'start_date' => $request->query->get('start_date'),
            'end_date' => $request->query->get('end_date'),
            'date' => $request->query->get('date'),
            'priorite' => $request->query->get('priorite'),
            'categorie' => $request->query->get('categorie'),
            'type_courrier' => $request->query->get('type_courrier'),
            'courrier' => $request->query->get('courrier'),
            'provenance' => $request->query->get('provenance'),
            'service_destinataire' => $defaultServiceDestinataire,
            'emetteur' => $request->query->get('emetteur'),
            'statut' => $request->query->get('statut'),
            'type_transfert' => $request->query->get('type_transfert'),
            'accuse_reception' => $request->query->has('accuse_reception') ? filter_var($request->query->get('accuse_reception'), FILTER_VALIDATE_BOOLEAN) : null,
            'isinstance' => $request->query->has('isinstance') ? filter_var($request->query->get('isinstance'), FILTER_VALIDATE_BOOLEAN) : null,
            'isgeled' => $request->query->has('isgeled') ? filter_var($request->query->get('isgeled'), FILTER_VALIDATE_BOOLEAN) : null,
            'dernier_poste' => $request->query->get('dernier_poste'),
            'is_delete' => filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN),
            'isarchive' => filter_var($request->query->get('isarchive', false), FILTER_VALIDATE_BOOLEAN),
            'search' => $request->query->get('search'),
            'year' => $request->query->get('year'),
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => (int)$request->query->get('limit', 10),
        ];

        $queryBuilder = $this->transmissionRepository->createQueryBuilder('t')
            ->leftJoin('t.idCourrier', 'c')
            ->leftJoin('c.typeCourrier', 'tc')
            ->leftJoin('c.idProvenance', 'prov')
            ->leftJoin('prov.categories', 'cat')
            ->leftJoin('t.idServiceDestinataire', 'sd')
            ->leftJoin('t.idEmetteur', 'e')
            ->leftJoin('e.idService', 'es')
            ->addSelect('c', 'tc', 'prov', 'cat', 'sd', 'e', 'es')
            ->where('t.isDelete = :isDelete')
            ->setParameter('isDelete', $filters['is_delete'])
            ->andWhere('t.isArchive = :isArchive')
            ->setParameter('isArchive', $filters['isarchive']);

        // ðŸ” Recherche globale transmission + courrier + relations
        $this->applyGlobalSearch($queryBuilder, $filters['search'], 't', 'c', 'tc', 'prov', 'cat', 'sd', 'e', 'es', 'search_main');

        // ðŸ“† Filtres de date (basÃ©s sur dateArrivee du COURRIER)
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $queryBuilder->andWhere('c.dateArrivee >= :start AND c.dateArrivee <= :end')
                         ->setParameter('start', $filters['start_date'] . ' 00:00:00')
                         ->setParameter('end', $filters['end_date'] . ' 23:59:59');
        } elseif (!empty($filters['date'])) {
            $queryBuilder->andWhere('c.dateArrivee >= :dateStart AND c.dateArrivee <= :dateEnd')
                         ->setParameter('dateStart', $filters['date'] . ' 00:00:00')
                         ->setParameter('dateEnd', $filters['date'] . ' 23:59:59');
        } elseif (!empty($filters['year'])) {
            $year = (int) $filters['year'];
            if ($year >= 1900 && $year <= 2100) {
                $startOfYear = (new \DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
                $endOfYear = (new \DateTimeImmutable())->setDate($year, 12, 31)->setTime(23, 59, 59);
                $queryBuilder->andWhere('c.dateArrivee >= :yearStart AND c.dateArrivee <= :yearEnd')
                    ->setParameter('yearStart', $startOfYear)
                    ->setParameter('yearEnd', $endOfYear);
            }
        }

        // ðŸ§© Filtres additionnels sur le COURRIER
        if (!empty($filters['priorite']) && $filters['priorite'] !== 'Toutes') {
            $queryBuilder->andWhere('c.priorite = :priorite')
                         ->setParameter('priorite', $filters['priorite']);
        }
        if (!empty($filters['categorie'])) {
            $queryBuilder->andWhere('cat.id = :categorie')
                         ->setParameter('categorie', $filters['categorie']);
        }
        if (!empty($filters['type_courrier'])) {
            $queryBuilder->andWhere('tc.id = :typeCourrier')
                         ->setParameter('typeCourrier', $filters['type_courrier']);
        }
        if (!empty($filters['courrier'])) {
            $queryBuilder->andWhere('t.idCourrier = :courrier')
                         ->setParameter('courrier', $filters['courrier']);
        }
        if (!empty($filters['provenance'])) {
            $queryBuilder->andWhere('prov.id = :provenance')
                         ->setParameter('provenance', $filters['provenance']);
        }
        
        // ðŸ§© Filtres sur la TRANSMISSION
        if (!empty($filters['service_destinataire'])) {
            // âœ… CORRECTION : Filtrer par TOUTES les transmissions oÃ¹ le service est destinataire
            // (pas seulement la derniÃ¨re transmission du courrier)
            $queryBuilder->andWhere('t.idServiceDestinataire = :serviceDestinataire')
                         ->setParameter('serviceDestinataire', $filters['service_destinataire']);
        }
        if (!empty($filters['emetteur'])) {
            $queryBuilder->andWhere('t.idEmetteur = :emetteur')
                         ->setParameter('emetteur', $filters['emetteur']);
        }
        if (!empty($filters['statut'])) {
            // Filtrer par le statut de la DERNIÃˆRE transmission du courrier
            $queryBuilder->andWhere('t.id IN (
                SELECT t2.id FROM App\\Entity\\Cour\\Transmission t2
                WHERE t2.idCourrier = t.idCourrier
                AND t2.isDelete = false
                AND t2.statut = :statut
                AND t2.dateInstruction = (
                    SELECT MAX(t3.dateInstruction)
                    FROM App\\Entity\\Cour\\Transmission t3
                    WHERE t3.idCourrier = t2.idCourrier
                    AND t3.isDelete = false
                )
            )')
            ->setParameter('statut', $filters['statut']);
            // Exclure les courriers gelÃ©s SAUF pour le statut "ClassÃ©"
            if ($filters['statut'] !== 'ClassÃ©') {
                $queryBuilder->andWhere('c.isGeled = :isGeled')
                             ->setParameter('isGeled', false);
            }
        }
        if (!empty($filters['type_transfert'])) {
            $queryBuilder->andWhere('t.typeTransfert = :typeTransfert')
                         ->setParameter('typeTransfert', $filters['type_transfert']);
        }
        if ($filters['accuse_reception'] !== null) {
            $queryBuilder->andWhere('t.accuseReception = :accuseReception')
                         ->setParameter('accuseReception', $filters['accuse_reception']);
        }
        if ($filters['isinstance'] !== null) {
            $queryBuilder->andWhere('t.isinstance = :isinstance')
                         ->setParameter('isinstance', $filters['isinstance']);
        }
        if ($filters['isgeled'] !== null) {
            $queryBuilder->andWhere('c.isGeled = :isGeled')
                         ->setParameter('isGeled', $filters['isgeled']);
        }
        if (!empty($filters['dernier_poste'])) {
            // Filtrer uniquement les courriers dont la DERNIÃˆRE transmission a comme destinataire le service spÃ©cifiÃ©
            $queryBuilder->andWhere('EXISTS (
                SELECT 1 FROM App\\Entity\\Cour\\Transmission t_last
                WHERE t_last.idCourrier = t.idCourrier
                AND t_last.isDelete = false
                AND t_last.idServiceDestinataire = :dernierPoste
                AND t_last.dateInstruction = (
                    SELECT MAX(t_max.dateInstruction)
                    FROM App\\Entity\\Cour\\Transmission t_max
                    WHERE t_max.idCourrier = t.idCourrier
                    AND t_max.isDelete = false
                )
            )')
            ->setParameter('dernierPoste', $filters['dernier_poste']);
        }

        // ðŸ”„ Tri
        $queryBuilder->orderBy('t.dateInstruction', $filters['order_by']);

        // ðŸ“„ Pagination
        $limit = $filters['limit'];
        $page = $filters['page'];
        $paginationMain = $this->paginateTransmissionQuery($queryBuilder, $page, $limit, (string) $filters['order_by']);
        $total = $paginationMain['total'];
        $results = $paginationMain['results'];

        $notificationIdByTransmissionId = $this->getNotificationIdByTransmissionId($results, is_numeric($filters['service_destinataire']) ? (int) $filters['service_destinataire'] : null);
        $data = array_map(fn($t) => $this->formatTransmissionData($t, $this->getUser(), null, $notificationIdByTransmissionId), $results);

        // ðŸ”¢ Compter les transmissions avec statut "Transmis" pour le service principal (data)
        $countTransmisData = 0;
        foreach ($results as $t) {
            if ($t->getStatut() === 'Transmis') {
                $countTransmisData++;
            }
        }

        // Extraire les IDs des services additionnels
        $serviceIdsAdditionels = [];
        if (!empty($userServicesAdditionel)) {
            foreach ($userServicesAdditionel as $service) {
                if (is_array($service) && isset($service['serviceId'])) {
                    $serviceIdsAdditionels[] = (int)$service['serviceId'];
                } elseif (is_array($service) && isset($service['id'])) {
                    $serviceIdsAdditionels[] = (int)$service['id'];
                } elseif (is_int($service) || is_numeric($service)) {
                    $serviceIdsAdditionels[] = (int)$service;
                }
            }
        }

        // ðŸ†• RÃ©cupÃ©rer les transmissions EN COPIE pour le service principal et les services additionnels
        $dataTransmissionsCopie = [];
        if (!$serviceDestinataireParam) {
            // Collecter tous les IDs de services (principal + additionnels)
            $allServiceIds = array_filter(array_merge(
                [$userServiceId],
                $serviceIdsAdditionels
            ));

            if (!empty($allServiceIds)) {
                foreach ($allServiceIds as $serviceId) {
                    $queryBuilderCopie = $this->transmissionRepository->createQueryBuilder('t')
                        ->leftJoin('t.idCourrier', 'c')
                        ->leftJoin('c.typeCourrier', 'tc')
                        ->leftJoin('c.idProvenance', 'prov')
                        ->leftJoin('prov.categories', 'cat')
                        ->leftJoin('t.idServiceDestinataire', 'sd')
                        ->leftJoin('t.idEmetteur', 'e')
                        ->leftJoin('e.idService', 'es')
                        ->addSelect('c', 'tc', 'prov', 'cat', 'sd', 'e', 'es')
                        ->where('t.isDelete = :isDelete')
                         ->setParameter('isDelete', $filters['is_delete'])
                         ->andWhere('t.isArchive = :isArchive')
                         ->setParameter('isArchive', $filters['isarchive'])
                         // UNIQUEMENT les transmissions EN COPIE (pas destinataire principal)
                         ->andWhere('t.idServiceDestinataire != :serviceId')
                         // structuresCopie est stocké en JSON : parfois [283] (int), parfois ["283"] (string)
                         // On couvre les deux formats via plusieurs LIKE pour éviter les faux positifs (ex: 1283).
                         ->andWhere('(
                            t.structuresCopie LIKE :serviceIdLikeStr
                            OR t.structuresCopie LIKE :serviceIdLikeNumExact
                            OR t.structuresCopie LIKE :serviceIdLikeNumStart
                            OR t.structuresCopie LIKE :serviceIdLikeNumMid
                            OR t.structuresCopie LIKE :serviceIdLikeNumEnd
                         )')
                         ->setParameter('serviceId', $serviceId)
                         ->setParameter('serviceIdLikeStr', '%"' . $serviceId . '"%')
                         ->setParameter('serviceIdLikeNumExact', '%[' . $serviceId . ']%')
                         ->setParameter('serviceIdLikeNumStart', '%[' . $serviceId . ',%')
                         ->setParameter('serviceIdLikeNumMid', '%,' . $serviceId . ',%')
                         ->setParameter('serviceIdLikeNumEnd', '%,' . $serviceId . ']%');

                    // Appliquer les mÃªmes filtres
                    $this->applyGlobalSearch($queryBuilderCopie, $filters['search'], 't', 'c', 'tc', 'prov', 'cat', 'sd', 'e', 'es', 'search_copie');
                    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                        $queryBuilderCopie->andWhere('c.dateArrivee >= :start AND c.dateArrivee <= :end')
                                     ->setParameter('start', $filters['start_date'] . ' 00:00:00')
                                     ->setParameter('end', $filters['end_date'] . ' 23:59:59');
                    } elseif (!empty($filters['date'])) {
                        $queryBuilderCopie->andWhere('c.dateArrivee >= :dateStart AND c.dateArrivee <= :dateEnd')
                                     ->setParameter('dateStart', $filters['date'] . ' 00:00:00')
                                     ->setParameter('dateEnd', $filters['date'] . ' 23:59:59');
                    } elseif (!empty($filters['year'])) {
                        $year = (int) $filters['year'];
                        if ($year >= 1900 && $year <= 2100) {
                            $startOfYear = (new \DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
                            $endOfYear = (new \DateTimeImmutable())->setDate($year, 12, 31)->setTime(23, 59, 59);
                            $queryBuilderCopie->andWhere('c.dateArrivee >= :yearStart AND c.dateArrivee <= :yearEnd')
                                ->setParameter('yearStart', $startOfYear)
                                ->setParameter('yearEnd', $endOfYear);
                        }
                    }
                    if (!empty($filters['priorite']) && $filters['priorite'] !== 'Toutes') {
                        $queryBuilderCopie->andWhere('c.priorite = :priorite')
                                     ->setParameter('priorite', $filters['priorite']);
                    }
                    if (!empty($filters['categorie'])) {
                        $queryBuilderCopie->andWhere('cat.id = :categorie')
                                     ->setParameter('categorie', $filters['categorie']);
                    }
                    if (!empty($filters['type_courrier'])) {
                        $queryBuilderCopie->andWhere('tc.id = :typeCourrier')
                                     ->setParameter('typeCourrier', $filters['type_courrier']);
                    }
                    if (!empty($filters['courrier'])) {
                        $queryBuilderCopie->andWhere('t.idCourrier = :courrier')
                                     ->setParameter('courrier', $filters['courrier']);
                    }
                    if (!empty($filters['provenance'])) {
                        $queryBuilderCopie->andWhere('prov.id = :provenance')
                                     ->setParameter('provenance', $filters['provenance']);
                    }
                    if (!empty($filters['emetteur'])) {
                        $queryBuilderCopie->andWhere('t.idEmetteur = :emetteur')
                                     ->setParameter('emetteur', $filters['emetteur']);
                    }
                    if (!empty($filters['statut'])) {
                        // Filtrer par le statut de la DERNIÃˆRE transmission du courrier
                        $queryBuilderCopie->andWhere('t.id IN (
                            SELECT t2.id FROM App\\Entity\\Cour\\Transmission t2
                            WHERE t2.idCourrier = t.idCourrier
                            AND t2.isDelete = false
                            AND t2.statut = :statut
                            AND t2.dateInstruction = (
                                SELECT MAX(t3.dateInstruction)
                                FROM App\\Entity\\Cour\\Transmission t3
                                WHERE t3.idCourrier = t2.idCourrier
                                AND t3.isDelete = false
                            )
                        )')
                        ->setParameter('statut', $filters['statut']);
                        // Exclure les courriers gelÃ©s SAUF pour le statut "ClassÃ©"
                        if ($filters['statut'] !== 'ClassÃ©') {
                            $queryBuilderCopie->andWhere('c.isGeled = :isGeled')
                                         ->setParameter('isGeled', false);
                        }
                    }
                    if (!empty($filters['type_transfert'])) {
                        $queryBuilderCopie->andWhere('t.typeTransfert = :typeTransfert')
                                     ->setParameter('typeTransfert', $filters['type_transfert']);
                    }
                    if ($filters['accuse_reception'] !== null) {
                        $queryBuilderCopie->andWhere('t.accuseReception = :accuseReception')
                                     ->setParameter('accuseReception', $filters['accuse_reception']);
                    }
                    if ($filters['isinstance'] !== null) {
                        $queryBuilderCopie->andWhere('t.isinstance = :isinstance')
                                     ->setParameter('isinstance', $filters['isinstance']);
                    }
                    if ($filters['isgeled'] !== null) {
                        $queryBuilderCopie->andWhere('c.isGeled = :isGeled')
                                     ->setParameter('isGeled', $filters['isgeled']);
                    }
                    if (!empty($filters['dernier_poste'])) {
                        // Filtrer uniquement les courriers dont la DERNIÃˆRE transmission a comme destinataire le service spÃ©cifiÃ©
                        $queryBuilderCopie->andWhere('EXISTS (
                            SELECT 1 FROM App\\Entity\\Cour\\Transmission t_last
                            WHERE t_last.idCourrier = t.idCourrier
                            AND t_last.isDelete = false
                            AND t_last.idServiceDestinataire = :dernierPoste
                            AND t_last.dateInstruction = (
                                SELECT MAX(t_max.dateInstruction)
                                FROM App\\Entity\\Cour\\Transmission t_max
                                WHERE t_max.idCourrier = t.idCourrier
                                AND t_max.isDelete = false
                            )
                        )')
                        ->setParameter('dernierPoste', $filters['dernier_poste']);
                    }

                    $queryBuilderCopie->orderBy('t.dateInstruction', $filters['order_by']);

                    // ðŸ“Š Calculer le total AVANT pagination
                    $paginationCopie = $this->paginateTransmissionQuery($queryBuilderCopie, $page, $limit, (string) $filters['order_by']);
                    $totalCopie = $paginationCopie['total'];

                    // âœ… Appliquer la pagination indÃ©pendante
                    $resultsCopie = $paginationCopie['results'];

                    if ($totalCopie > 0) {
                        // Compter les transmissions avec statut "Transmis" pour ce service en copie
                        $countTransmisCopie = 0;
                        foreach ($resultsCopie as $tc) {
                            if ($tc->getStatut() === 'Transmis') {
                                $countTransmisCopie++;
                            }
                        }

                        $notificationIdByTransmissionIdCopie = $this->getNotificationIdByTransmissionId($resultsCopie, (int) $serviceId);
                        $dataTransmissionsCopie[] = [
                            'service_id' => $serviceId,
                            'page' => $page,
                            'limit' => $limit,
                            'total' => (int)$totalCopie,
                            'total_transmis_cp' => $countTransmisCopie,
                            'transmissions' => array_map(fn($t) => $this->formatTransmissionData($t, $this->getUser(), null, $notificationIdByTransmissionIdCopie), $resultsCopie)
                        ];
                    }
                }
            }
        }

        // ðŸ†• RÃ©cupÃ©rer les transmissions des services additionnels
        $dataServicesAdditionel = [];
        if (!empty($serviceIdsAdditionels) && !$serviceDestinataireParam) {
            // Ne rÃ©cupÃ©rer les services additionnels que si aucun filtre service_destinataire n'est spÃ©cifiÃ©
            foreach ($serviceIdsAdditionels as $serviceId) {
                $queryBuilderAdditionel = $this->transmissionRepository->createQueryBuilder('t')
                    ->leftJoin('t.idCourrier', 'c')
                    ->leftJoin('c.typeCourrier', 'tc')
                    ->leftJoin('c.idProvenance', 'prov')
                    ->leftJoin('prov.categories', 'cat')
                    ->leftJoin('t.idServiceDestinataire', 'sd')
                    ->leftJoin('t.idEmetteur', 'e')
                    ->leftJoin('e.idService', 'es')
                    ->addSelect('c', 'tc', 'prov', 'cat', 'sd', 'e', 'es')
                    ->where('t.isDelete = :isDelete')
                    ->setParameter('isDelete', $filters['is_delete'])
                    ->andWhere('t.isArchive = :isArchive')
                    ->setParameter('isArchive', $filters['isarchive'])
                    // Pour les services additionnels : UNIQUEMENT destinataire principal (pas les copies)
                    ->andWhere('t.idServiceDestinataire = :serviceId')
                    ->setParameter('serviceId', $serviceId);

                // Appliquer les mÃªmes filtres que pour le service principal
                $this->applyGlobalSearch($queryBuilderAdditionel, $filters['search'], 't', 'c', 'tc', 'prov', 'cat', 'sd', 'e', 'es', 'search_additionel');
                if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                    $queryBuilderAdditionel->andWhere('c.dateArrivee >= :start AND c.dateArrivee <= :end')
                                 ->setParameter('start', $filters['start_date'] . ' 00:00:00')
                                 ->setParameter('end', $filters['end_date'] . ' 23:59:59');
                } elseif (!empty($filters['date'])) {
                    $queryBuilderAdditionel->andWhere('c.dateArrivee >= :dateStart AND c.dateArrivee <= :dateEnd')
                                 ->setParameter('dateStart', $filters['date'] . ' 00:00:00')
                                 ->setParameter('dateEnd', $filters['date'] . ' 23:59:59');
                } elseif (!empty($filters['year'])) {
                    $year = (int) $filters['year'];
                    if ($year >= 1900 && $year <= 2100) {
                        $startOfYear = (new \DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
                        $endOfYear = (new \DateTimeImmutable())->setDate($year, 12, 31)->setTime(23, 59, 59);
                        $queryBuilderAdditionel->andWhere('c.dateArrivee >= :yearStart AND c.dateArrivee <= :yearEnd')
                            ->setParameter('yearStart', $startOfYear)
                            ->setParameter('yearEnd', $endOfYear);
                    }
                }
                if (!empty($filters['priorite']) && $filters['priorite'] !== 'Toutes') {
                    $queryBuilderAdditionel->andWhere('c.priorite = :priorite')
                                 ->setParameter('priorite', $filters['priorite']);
                }
                if (!empty($filters['categorie'])) {
                    $queryBuilderAdditionel->andWhere('cat.id = :categorie')
                                 ->setParameter('categorie', $filters['categorie']);
                }
                if (!empty($filters['type_courrier'])) {
                    $queryBuilderAdditionel->andWhere('tc.id = :typeCourrier')
                                 ->setParameter('typeCourrier', $filters['type_courrier']);
                }
                if (!empty($filters['courrier'])) {
                    $queryBuilderAdditionel->andWhere('t.idCourrier = :courrier')
                                 ->setParameter('courrier', $filters['courrier']);
                }
                if (!empty($filters['provenance'])) {
                    $queryBuilderAdditionel->andWhere('prov.id = :provenance')
                                 ->setParameter('provenance', $filters['provenance']);
                }
                if (!empty($filters['emetteur'])) {
                    $queryBuilderAdditionel->andWhere('t.idEmetteur = :emetteur')
                                 ->setParameter('emetteur', $filters['emetteur']);
                }
                if (!empty($filters['statut'])) {
                    // Filtrer par le statut de la DERNIÃˆRE transmission du courrier
                    $queryBuilderAdditionel->andWhere('t.id IN (
                        SELECT t2.id FROM App\\Entity\\Cour\\Transmission t2
                        WHERE t2.idCourrier = t.idCourrier
                        AND t2.isDelete = false
                        AND t2.statut = :statut
                        AND t2.dateInstruction = (
                            SELECT MAX(t3.dateInstruction)
                            FROM App\\Entity\\Cour\\Transmission t3
                            WHERE t3.idCourrier = t2.idCourrier
                            AND t3.isDelete = false
                        )
                    )')
                    ->setParameter('statut', $filters['statut']);
                    // Exclure les courriers gelÃ©s SAUF pour le statut "ClassÃ©"
                    if ($filters['statut'] !== 'ClassÃ©') {
                        $queryBuilderAdditionel->andWhere('c.isGeled = :isGeled')
                                     ->setParameter('isGeled', false);
                    }
                }
                if (!empty($filters['type_transfert'])) {
                    $queryBuilderAdditionel->andWhere('t.typeTransfert = :typeTransfert')
                                 ->setParameter('typeTransfert', $filters['type_transfert']);
                }
                if ($filters['accuse_reception'] !== null) {
                    $queryBuilderAdditionel->andWhere('t.accuseReception = :accuseReception')
                                 ->setParameter('accuseReception', $filters['accuse_reception']);
                }
                if ($filters['isinstance'] !== null) {
                    $queryBuilderAdditionel->andWhere('t.isinstance = :isinstance')
                                 ->setParameter('isinstance', $filters['isinstance']);
                }
                if ($filters['isgeled'] !== null) {
                    $queryBuilderAdditionel->andWhere('c.isGeled = :isGeled')
                                 ->setParameter('isGeled', $filters['isgeled']);
                }
                if (!empty($filters['dernier_poste'])) {
                    // Filtrer uniquement les courriers dont la DERNIÃˆRE transmission a comme destinataire le service spÃ©cifiÃ©
                    $queryBuilderAdditionel->andWhere('EXISTS (
                        SELECT 1 FROM App\\Entity\\Cour\\Transmission t_last
                        WHERE t_last.idCourrier = t.idCourrier
                        AND t_last.isDelete = false
                        AND t_last.idServiceDestinataire = :dernierPoste
                        AND t_last.dateInstruction = (
                            SELECT MAX(t_max.dateInstruction)
                            FROM App\\Entity\\Cour\\Transmission t_max
                            WHERE t_max.idCourrier = t.idCourrier
                            AND t_max.isDelete = false
                        )
                    )')
                    ->setParameter('dernierPoste', $filters['dernier_poste']);
                }

                $queryBuilderAdditionel->orderBy('t.dateInstruction', $filters['order_by']);

                // ðŸ“Š Calculer le total AVANT pagination
                $paginationAdditionel = $this->paginateTransmissionQuery($queryBuilderAdditionel, $page, $limit, (string) $filters['order_by']);
                $totalAdditionel = $paginationAdditionel['total'];

                // âœ… Appliquer la pagination indÃ©pendante
                $resultsAdditionel = $paginationAdditionel['results'];

                if ($totalAdditionel > 0) {
                    // Compter les transmissions avec statut "Transmis" pour ce service additionnel
                    $countTransmisAdditionel = 0;
                    foreach ($resultsAdditionel as $ta) {
                        if ($ta->getStatut() === 'Transmis') {
                            $countTransmisAdditionel++;
                        }
                    }

                    // Déterminer l'utilisateur effectif à utiliser pour le calcul de `canTransmit`
                    // pour les transmissions de ce service additionnel. Par défaut, utiliser
                    // l'utilisateur connecté. Si l'utilisateur connecté a un service
                    // additionnel correspondant à ce `serviceId` et que cet objet contient
                    // un `userId`, on charge cet utilisateur et on l'utilise.
                    $effectiveUser = $this->getUser();
                    if (!empty($userServicesAdditionel) && is_array($userServicesAdditionel)) {
                        foreach ($userServicesAdditionel as $svc) {
                            $svcId = null;
                            if (is_array($svc) && isset($svc['serviceId'])) {
                                $svcId = (int)$svc['serviceId'];
                            } elseif (is_array($svc) && isset($svc['id'])) {
                                $svcId = (int)$svc['id'];
                            } elseif (is_int($svc) || is_numeric($svc)) {
                                $svcId = (int)$svc;
                            }

                            if ($svcId === (int)$serviceId) {
                                $svcUserId = null;
                                if (is_array($svc) && isset($svc['userId'])) {
                                    $svcUserId = (int)$svc['userId'];
                                } elseif (is_array($svc) && isset($svc['user_id'])) {
                                    $svcUserId = (int)$svc['user_id'];
                                }

                    if ($svcUserId) {
                        $found = $this->userRepository->find($svcUserId);
                        if ($found instanceof \App\Entity\Core\User) {
                            $effectiveUser = $found;
                        }
                    }
                    break;
                }
            }
        }

        $notificationIdByTransmissionIdAdditionel = $this->getNotificationIdByTransmissionId($resultsAdditionel, (int) $serviceId);
        $dataServicesAdditionel[] = [
            'service_id' => $serviceId,
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$totalAdditionel,
            'total_transmis_add' => $countTransmisAdditionel,
            'transmissions' => array_map(fn($t) => $this->formatTransmissionData($t, $effectiveUser, $serviceId, $notificationIdByTransmissionIdAdditionel), $resultsAdditionel)
        ];
                }
            }
        }

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'total_transmis' => $countTransmisData,
            'data' => $data,
            'data_services_additionel' => $dataServicesAdditionel,
            'data_transmissions_copie' => $dataTransmissionsCopie
        ], 200);
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
     * ðŸ” DÃ©termine si la transmission provient d'un collaborateur ou d'un supÃ©rieur
     * 
     * @param Transmission $t La transmission
     * @return string 'collaborateur', 'superieur' ou 'autre'
     */
    private function determineProvenanceType(Transmission $t): string
    {
        // RÃ©cupÃ©rer le service Ã©metteur et le service destinataire
        $serviceEmetteur = $t->getIdEmetteur()?->getIdService();
        $serviceDestinataire = $t->getIdServiceDestinataire();

        if (!$serviceEmetteur || !$serviceDestinataire) {
            return 'autre';
        }

        // VÃ©rifier si le service Ã©metteur est un enfant du service destinataire (collaborateur)
        if ($serviceEmetteur->getIdServiceParent() && 
            $serviceEmetteur->getIdServiceParent()->getId() === $serviceDestinataire->getId()) {
            return 'collaborateur';
        }

        // VÃ©rifier si le service Ã©metteur est le parent du service destinataire (supÃ©rieur)
        if ($serviceDestinataire->getIdServiceParent() && 
            $serviceDestinataire->getIdServiceParent()->getId() === $serviceEmetteur->getId()) {
            return 'superieur';
        }

        // Si aucune relation directe parent/enfant, retourner 'autre'
        return 'collaborateur';
    }

    /**
     * ðŸ” RÃ©cupÃ¨re toutes les transmissions pour un service destinataire donnÃ©
     * avec les IDs des courriers associÃ©s, le statut, l'accusÃ© de rÃ©ception et la date de crÃ©ation
     * 
     * @param int $serviceId L'ID du service destinataire
     * @return array Tableau contenant les IDs des transmissions, leurs courriers associÃ©s, statuts, accusÃ© de rÃ©ception et date de crÃ©ation
     */
    private function getTransmissionsForService(int $serviceId): array
    {
        // RÃ©cupÃ©rer toutes les transmissions oÃ¹ ce service est le destinataire
        $transmissions = $this->transmissionRepository->createQueryBuilder('t')
            ->select('t.id as transmission_id, IDENTITY(t.idCourrier) as courrier_id, t.statut, t.accuseReception, t.createdAt')
            ->where('t.idServiceDestinataire = :serviceId')
            ->andWhere('t.isDelete = :isDelete')
            ->setParameter('serviceId', $serviceId)
            ->setParameter('isDelete', false)
            ->orderBy('t.dateInstruction', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($transmissions as $transmission) {
            $result[] = [
                'transmission_id' => $transmission['transmission_id'],
                'courrier_id' => $transmission['courrier_id'],
                'statut' => $transmission['statut'],
                'accuse_reception' => $transmission['accuseReception'],
                'created_at' => $transmission['createdAt'] ? $transmission['createdAt']->format('Y-m-d H:i:s') : null,
            ];
        }

        return $result;
    }

    /**
     * ðŸ“‹ Formate les donnÃ©es d'une transmission avec toutes les informations nÃ©cessaires
     * 
     * @param Transmission $t La transmission Ã  formater
     * @return array Les donnÃ©es formatÃ©es de la transmission
     */
    private function formatTransmissionData($t, $currentUser = null, ?int $overrideServiceId = null, ?array $notificationIdByTransmissionId = null): array
    {
        // RÃ©cupÃ©rer la derniÃ¨re transmission du courrier pour obtenir le dernier statut et le dernier poste
        $derniereTransmission = null;
        if ($t->getIdCourrier()) {
            $derniereTransmission = $this->transmissionRepository->findLastTransmissionByCourrier($t->getIdCourrier()->getId());
        }

        // ðŸ†• CALCULER SI L'UTILISATEUR CONNECTÃ‰ PEUT TRANSMETTRE CE COURRIER
        // Logique : canTransmit = false si l'utilisateur a DÃ‰JÃ€ transmis CE courrier
        //           canTransmit = true si l'utilisateur n'a JAMAIS transmis CE courrier
        $canTransmit = true; // Par dÃ©faut, on peut transmettre
        $userTransmissions = []; // Liste des transmissions envoyÃ©es par l'utilisateur connectÃ© pour CE courrier
        $canModify = false; // Par dÃ©faut, on ne peut pas modifier
        $modifiableTransmissionId = null; // ID de la transmission modifiable
        
        if ($currentUser instanceof \App\Entity\Core\User && $t->getIdCourrier()) {
            // RÃ©cupÃ©rer TOUTES les transmissions de CE COURRIER oÃ¹ l'utilisateur connectÃ© est Ã©metteur
            $transmissionsEnvoyees = $this->transmissionRepository->findBy([
                'idCourrier' => $t->getIdCourrier(),
                'idEmetteur' => $currentUser,
                'isDelete' => false,
            ], ['dateInstruction' => 'DESC']);
            
            // Si des transmissions existent pour ce courrier â†’ BLOCAGE (canTransmit = false)
            if (!empty($transmissionsEnvoyees)) {
                $canTransmit = false;
                
                // Formater les transmissions envoyÃ©es par l'utilisateur pour CE courrier
                foreach ($transmissionsEnvoyees as $trans) {
                    $userTransmissions[] = [
                        'transmission_id' => $trans->getId(),
                        'courrier_id' => $trans->getIdCourrier()?->getId(),
                        'courrier_numero' => $trans->getIdCourrier()?->getNumero(),
                        'courrier_objet' => $trans->getIdCourrier()?->getObjet(),
                        'service_destinataire' => $trans->getIdServiceDestinataire() ? [
                            'id' => $trans->getIdServiceDestinataire()->getId(),
                            'nom' => $trans->getIdServiceDestinataire()->getNom(),
                            'sigle' => $trans->getIdServiceDestinataire()->getSigle(),
                        ] : null,
                        'statut' => $trans->getStatut(),
                        'date_instruction' => $trans->getDateInstruction()?->format('Y-m-d H:i:s'),
                        'instruction' => $trans->getInstruction(),
                        'type_transfert' => $trans->getTypeTransfert(),
                        'accuse_reception' => $trans->isAccuseReception(),
                        'created_at' => $trans->getCreatedAt()?->format('Y-m-d H:i:s'),
                    ];
                    
                    // ðŸ†• VÃ©rifier s'il existe une transmission avec accusÃ© de rÃ©ception = false
                    if (!$trans->isAccuseReception()) {
                        $canModify = true;
                        $modifiableTransmissionId = $trans->getId();
                    }
                }

                // Autoriser une nouvelle transmission si la derniere transmission est revenue au service courant
                // Pour le calcul dans `data_services_additionel`, on peut fournir un
                // `$overrideServiceId` (le service additionnel) afin de comparer la
                // dernière transmission avec ce service au lieu du service de
                // l'utilisateur effectif.
                $currentServiceId = $overrideServiceId ?? $currentUser->getIdService()?->getId();
                $lastServiceId = $derniereTransmission?->getIdServiceDestinataire()?->getId();
                $lastStatut = $derniereTransmission?->getStatut();
                $lastStatutNormalized = is_string($lastStatut)
                    ? (function_exists('mb_strtolower') ? mb_strtolower($lastStatut, 'UTF-8') : strtolower($lastStatut))
                    : null;
                if (is_string($lastStatutNormalized)) {
                    $lastStatutNormalized = strtr($lastStatutNormalized, [
                        'é' => 'e',
                        'è' => 'e',
                        'ê' => 'e',
                        'ë' => 'e',
                        'à' => 'a',
                        'â' => 'a',
                        'î' => 'i',
                        'ï' => 'i',
                        'ô' => 'o',
                        'ù' => 'u',
                        'û' => 'u',
                        'ü' => 'u',
                        'ç' => 'c',
                    ]);
                }
                $isReturned = $lastStatutNormalized !== null && in_array($lastStatutNormalized, ['recu', 'retourne', 'retournee', 'retour'], true);

                if ($currentServiceId && $lastServiceId && $currentServiceId === $lastServiceId && $isReturned) {
                    $canTransmit = true;
                }
            }
        }

        $reponses = $this->getReponsesByTransmission($t->getId());

        $notificationMeta = is_array($notificationIdByTransmissionId)
            ? ($notificationIdByTransmissionId[$t->getId()] ?? null)
            : null;
        $notificationId = is_array($notificationMeta) ? ($notificationMeta['id'] ?? null) : $notificationMeta;
        $notificationIsRead = is_array($notificationMeta) ? ($notificationMeta['is_read'] ?? null) : null;

        return [
            'id' => $t->getId(),
            'notification' => [
                'id' => $notificationId,
                'is_read' => $notificationIsRead,
            ],
            'courrier' => $t->getIdCourrier() ? [
                'id' => $t->getIdCourrier()->getId(),
                'numero' => $t->getIdCourrier()->getNumero(),
                'reference' => $t->getIdCourrier()->getNumero(),
                'objet' => $t->getIdCourrier()->getObjet(),
                'document' => $t->getIdCourrier()->getDocument(),
                'commentairePublic' => $t->getIdCourrier()->getCommentairePublic(),
                'commentaireInterne' => $t->getIdCourrier()->getCommentaireInterne(),
                'is_geled' => $t->getIdCourrier()->isGeled(),
                'dateArrivee' => $t->getIdCourrier()->getDateArrivee()?->format('Y-m-d H:i:s'),
                'classeCourrier' => $t->getIdCourrier()->getClasseCourrier(),
                'dateEnregistrement' => $t->getIdCourrier()->getDateEnregistrement()?->format('Y-m-d H:i:s'),
                'typeCourrier' => $t->getIdCourrier()->getTypeCourrier()?->getNom(),
                'provenance' => $t->getIdCourrier()->getIdProvenance()?->getNom(),
                'categorie' => $t->getIdCourrier()->getIdProvenance() && $t->getIdCourrier()->getIdProvenance()->getCategories()->count() > 0 
                    ? $t->getIdCourrier()->getIdProvenance()->getCategories()->first()->getNom()
                    : null,
                'priorite' => $t->getIdCourrier()->getPriorite(),
                'piecesJointes' => $this->getPiecesJointesCourrier($t->getIdCourrier()->getId()),
            ] : null,
            'serviceDestinataire' => $t->getIdServiceDestinataire() ? [
                'id' => $t->getIdServiceDestinataire()->getId(),
                'nom' => $t->getIdServiceDestinataire()->getNom(),
                'sigle' => $t->getIdServiceDestinataire()->getSigle(),
                // 'transmissions' => $this->getTransmissionsForService($t->getIdServiceDestinataire()->getId()),
            ] : null,
            'emetteur' => $t->getIdEmetteur() ? [
                'id' => $t->getIdEmetteur()->getId(),
                'fullName' => $t->getIdEmetteur()->getFullName(),
                'email' => $t->getIdEmetteur()->getEmail(),
                'service' => $t->getIdEmetteur()->getIdService() ? [
                    'id' => $t->getIdEmetteur()->getIdService()->getId(),
                    'nom' => $t->getIdEmetteur()->getIdService()->getNom(),
                    'sigle' => $t->getIdEmetteur()->getIdService()->getSigle(),
                ] : null,
            ] : null,
            // ðŸ†• Type de provenance : Indique si la transmission vient d'un collaborateur ou d'un supÃ©rieur
            'provenanceType' => $this->determineProvenanceType($t),
            'structuresCopie' => $t->getStructuresCopie(),
            'dateInstruction' => $t->getDateInstruction()?->format('Y-m-d H:i:s'),
            'dateReception' => $t->getDateReception()?->format('Y-m-d H:i:s'),
            'instruction' => $t->getInstruction(),
            'delaiTraitement' => $t->getDelaiTraitement(),
            'typeTransfert' => $t->getTypeTransfert(),
            'accuseReception' => $t->isAccuseReception(),
            'statut' => $t->getStatut(),
            'isinstance' => $t->isinstance(),
            'nombrePieceJointe' => $t->getNombrePieceJointe(),
            'isArchive' => $t->isArchive(),
            'traitePar' => $this->enrichTraitePar($t->getTraitePar()),
            'piecesJointes' => $this->getPiecesJointes($t->getId()),
            // ðŸ†• Nouvelles clÃ©s demandÃ©es
            'dernier_statut' => $derniereTransmission?->getStatut(),
            'dernier_poste' => $derniereTransmission?->getIdServiceDestinataire() ? [
                'id' => $derniereTransmission->getIdServiceDestinataire()->getId(),
                'nom' => $derniereTransmission->getIdServiceDestinataire()->getNom(),
                'sigle' => $derniereTransmission->getIdServiceDestinataire()->getSigle(),
            ] : null,
            'createdAt' => $t->getCreatedAt()?->format('Y-m-d H:i:s'),
            // ðŸ†• CHAMP CALCULÃ‰ : Indique si l'utilisateur connectÃ© peut transmettre ce courrier
            'canTransmit' => $canTransmit,
            // ðŸ†• CHAMP CALCULÃ‰ : Indique si l'utilisateur connectÃ© peut modifier une transmission de ce courrier
            'canModify' => $canModify,
            // ðŸ†• ID de la transmission modifiable (celle avec accusÃ© de rÃ©ception = false)
            'modifiableTransmissionId' => $modifiableTransmissionId,
            // ðŸ†• LISTE DES TRANSMISSIONS : Toutes les transmissions que l'utilisateur connectÃ© a envoyÃ©es pour ce courrier
            'userTransmissions' => $userTransmissions,
            // ðŸ†• RÃ‰PONSES : Toutes les rÃ©ponses associÃ©es Ã  cette transmission
            'reponses' => $reponses,
            'is_reponse' => !empty($reponses),
        ];
    }

    /**
     * ðŸ“‹ RÃ©cupÃ¨re toutes les rÃ©ponses associÃ©es Ã  une transmission
     * 
     * @param int $transmissionId L'ID de la transmission
     * @return array Les rÃ©ponses formatÃ©es
     */
    private function getReponsesByTransmission(int $transmissionId): array
    {
        try {
            // Rechercher toutes les rÃ©ponses oÃ¹ idTransmission contient cet ID de transmission
            // Utilisation de LIKE car JSON_CONTAINS peut causer des problÃ¨mes selon la version de MySQL
            $allReponses = $this->reponseRepository->createQueryBuilder('r')
                ->where('r.isDelete = :isDelete')
                ->setParameter('isDelete', false)
                ->andWhere('r.idTransmission IS NOT NULL')
                ->orderBy('r.dateReponse', 'DESC')
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
                        'courriers' => $reponse->getCourriers()->map(function($courrier) {
                            return [
                                'id' => $courrier->getId(),
                                'numero' => $courrier->getNumero(),
                                'objet' => $courrier->getObjet(),
                            ];
                        })->toArray(),
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
     * ðŸ“Ž RÃ©cupÃ¨re les piÃ¨ces jointes d'une transmission depuis la base de donnÃ©es
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
}
