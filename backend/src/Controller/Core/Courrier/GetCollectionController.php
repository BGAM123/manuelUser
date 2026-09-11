<?php

namespace App\Controller\Core\Courrier;

use App\Repository\Cour\CourrierRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\QueryBuilder;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierArrive")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    private function applyGlobalSearch(
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

    #[Route('/core/courrier', name: 'app_core_courrier_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/courrier',
        summary: 'Lister les courriers entrants avec filtres et pagination',
        description: 'Retourne la liste des courriers entrants filtrés par date, catégories, type, priorité, classe, année, etc.',
        tags: ['CourrierArrive'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'order_by', in: 'query', required: false, description: 'Specify the sort direction relative to the creation date. Use "ASC" or "DESC".', schema: new OA\Schema(type: 'string', enum: ['DESC', 'ASC'], default: 'DESC')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Filter from this date (format: YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Filter to this date (format: YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date', in: 'query', required: false, description: 'Filter on specific arrival date (format: YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'priorite', in: 'query', required: false, description: 'Filter by priority.', schema: new OA\Schema(type: 'string', enum: ['Toutes', 'Basse', 'Normal', 'Haute'])),
            new OA\Parameter(name: 'categorie', in: 'query', required: false, description: 'Filter by the category ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type_courrier', in: 'query', required: false, description: 'Filter by the courrier type ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'classe_courrier', in: 'query', required: false, description: 'Filter by courrier class (ex: Urgent, Normal, Confidentiel).', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'provenance', in: 'query', required: false, description: 'Filter by provenance ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'service_traitant', in: 'query', required: false, description: 'Filter by service traitant ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'createur', in: 'query', required: false, description: 'Filter by creator ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'statut', in: 'query', required: false, description: 'Filter by status.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'The page number for pagination.', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'The number of items per page. 0 to retrieve all.', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, description: 'Filter deleted courriers.', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'isarchive', in: 'query', required: false, description: 'Filter archived courriers. By default, returns only non-archived (false).', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Recherche globale sur les champs du courrier et des relations (provenance, service, créateur, type, transmission, etc.).', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Filter by year of arrival.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'has_courrier_depart', in: 'query', required: false, description: 'Filter by presence of courrier depart. true=with courrier depart, false=without courrier depart.', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des courriers récupérée avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 100),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'numero', type: 'string'),
                                    new OA\Property(property: 'reference', type: 'string'),
                                    new OA\Property(property: 'objet', type: 'string'),
                                    new OA\Property(property: 'priorite', type: 'string'),
                                    new OA\Property(property: 'statut', type: 'string'),
                                    new OA\Property(property: 'statut_transmission', type: 'string', nullable: true, description: 'Statut de la dernière transmission'),
                                    new OA\Property(
                                        property: 'service_traitement',
                                        type: 'object',
                                        nullable: true,
                                        description: 'Service destinataire de la dernière transmission',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'nom', type: 'string')
                                        ]
                                    ),
                                    new OA\Property(property: 'nom', type: 'string'),
                                    new OA\Property(property: 'civilite', type: 'string'),
                                    new OA\Property(property: 'matricule', type: 'string'),
                                    new OA\Property(property: 'telephone', type: 'string'),
                                    new OA\Property(property: 'email', type: 'string'),
                                    new OA\Property(property: 'adresse', type: 'string'),
                                    new OA\Property(property: 'commentaire', type: 'string'),
                                    new OA\Property(property: 'commentairePublic', type: 'string', nullable: true, description: 'Commentaire public visible par tous'),
                                    new OA\Property(property: 'commentaireInterne', type: 'string', nullable: true, description: 'Commentaire interne visible uniquement en interne'),
                                    new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 3, description: 'Nombre de piéces jointes'),
                                    new OA\Property(
                                        property: 'createur',
                                        type: 'object',
                                        nullable: true,
                                        description: 'Utilisateur créateur du courrier',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'nom', type: 'string')
                                        ]
                                    ),
                                    new OA\Property(property: 'isConfidentiel', type: 'boolean'),
                                    new OA\Property(property: 'isArchive', type: 'boolean', description: 'Indicates if the courrier is archived'),
                                    new OA\Property(property: 'hasCourrierDepart', type: 'boolean', description: 'Indicates if there are courrier depart linked to this courrier'),
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
            'priorite' => $request->query->get('priorite'),
            'categorie' => $request->query->get('categorie'),
            'type_courrier' => $request->query->get('type_courrier'),
            'classe_courrier' => $request->query->get('classe_courrier'),
            'provenance' => $request->query->get('provenance'),
            'service_traitant' => $request->query->get('service_traitant'),
            'createur' => $request->query->get('createur'),
            'statut' => $request->query->get('statut'),
            'is_delete' => filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN),
            'isarchive' => filter_var($request->query->get('isarchive', false), FILTER_VALIDATE_BOOLEAN),
            'search' => $request->query->get('search'),
            'year' => $request->query->get('year'),
            'has_courrier_depart' => $request->query->has('has_courrier_depart') ? filter_var($request->query->get('has_courrier_depart'), FILTER_VALIDATE_BOOLEAN) : null,
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => (int)$request->query->get('limit', 10),
        ];

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
            ->setParameter('isDelete', $filters['is_delete'])
            ->andWhere('c.isArchive = :isArchive')
            ->setParameter('isArchive', $filters['isarchive']);

        // ðŸ” Recherche texte
        $this->applyGlobalSearch($queryBuilder, $filters['search'], 'c', 's', 'u', 'p', 'cat', 't', 'trans', 'servTrans', 'search');

        // ðŸ“† Filtres de date (basÃ©s sur dateArrivee)
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
            $startOfYear = (new \DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
            $endOfYear = (new \DateTimeImmutable())->setDate($year, 12, 31)->setTime(23, 59, 59);
            $queryBuilder->andWhere('c.dateArrivee >= :yearStart AND c.dateArrivee <= :yearEnd')
                         ->setParameter('yearStart', $startOfYear)
                         ->setParameter('yearEnd', $endOfYear);
        }

        // ðŸ§© Filtres additionnels
        if (!empty($filters['priorite']) && $filters['priorite'] !== 'Toutes') {
            $queryBuilder->andWhere('c.priorite = :priorite')
                         ->setParameter('priorite', $filters['priorite']);
        }
        if (!empty($filters['categorie'])) {
            $queryBuilder->andWhere('cat.id = :categorie')
                         ->setParameter('categorie', $filters['categorie']);
        }
        if (!empty($filters['type_courrier'])) {
            $queryBuilder->andWhere('t.id = :type')
                         ->setParameter('type', $filters['type_courrier']);
        }
        if (!empty($filters['classe_courrier'])) {
            $queryBuilder->andWhere('LOWER(c.classeCourrier) LIKE LOWER(:classeCourrier)')
                         ->setParameter('classeCourrier', '%' . trim($filters['classe_courrier']) . '%');
        }
        if (!empty($filters['provenance'])) {
            $queryBuilder->andWhere('p.id = :provenance')
                         ->setParameter('provenance', $filters['provenance']);
        }
        if (!empty($filters['service_traitant'])) {
            // Filtrer par le service destinataire de la derniÃ¨re transmission
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
            $queryBuilder->setParameter('serviceTraitant', $filters['service_traitant']);
        }
        if (!empty($filters['createur'])) {
            $queryBuilder->andWhere('u.id = :createur')
                         ->setParameter('createur', $filters['createur']);
        }
        if (!empty($filters['statut'])) {
            $queryBuilder->andWhere('c.statut = :statut')
                         ->setParameter('statut', $filters['statut']);
        }

        // ï¿½ Filtre par prÃ©sence de courrier dÃ©part
        if ($filters['has_courrier_depart'] !== null) {
            if ($filters['has_courrier_depart'] === true) {
                // Courriers avec au moins un courrier dÃ©part
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
                // Courriers sans courrier dÃ©part
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

        $queryBuilder->orderBy('c.createdAt', $filters['order_by']);


        // Pagination personnalisÃ©e pour garantir exactement 'limit' courriers arrivÃ©s (par ID) (Alex)

        // $limit = $filters['limit'];
        // $page = $filters['page'];
        // $total = (clone $queryBuilder)->select('COUNT(DISTINCT c.id)')->getQuery()->getSingleScalarResult();

        // if ($limit > 0) {
        //     $queryBuilder->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        // }

        // $results = $queryBuilder->getQuery()->getResult();

        // // Si on a moins que 'limit', complÃ©ter avec d'autres courriers arrivÃ©s (par ID) qui respectent tous les filtres
        // if ($limit > 0 && count($results) < $limit) {
        //     $idsDeja = array_map(fn($c) => $c->getId(), $results);
        //     $qbArrivee = $this->courrierRepository->createQueryBuilder('c2')
        //         ->leftJoin('c2.idServiceTraitant', 's2')
        //         ->leftJoin('c2.idCreateur', 'u2')
        //         ->leftJoin('c2.idProvenance', 'p2')
        //         ->leftJoin('p2.categories', 'cat2')
        //         ->leftJoin('c2.typeCourrier', 't2')
        //         ->leftJoin('c2.transmissions', 'trans2')
        //         ->leftJoin('trans2.idServiceDestinataire', 'servTrans2')
        //         ->addSelect('s2', 'u2', 'p2', 'cat2', 't2', 'trans2', 'servTrans2')
        //         ->where('c2.isDelete = :isDelete2')
        //         ->andWhere('c2.isArchive = :isArchive2')
        //         ->setParameter('isDelete2', $filters['is_delete'])
        //         ->setParameter('isArchive2', $filters['isarchive']);

        //     // Appliquer tous les filtres secondaires
        //     $this->applyGlobalSearch($qbArrivee, $filters['search'], 'c2', 's2', 'u2', 'p2', 'cat2', 't2', 'trans2', 'servTrans2', 'search2');
        //     if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        //         $qbArrivee->andWhere('c2.dateArrivee >= :start2 AND c2.dateArrivee <= :end2')
        //             ->setParameter('start2', $filters['start_date'] . ' 00:00:00')
        //             ->setParameter('end2', $filters['end_date'] . ' 23:59:59');
        //     } elseif (!empty($filters['date'])) {
        //         $qbArrivee->andWhere('c2.dateArrivee >= :dateStart2 AND c2.dateArrivee <= :dateEnd2')
        //             ->setParameter('dateStart2', $filters['date'] . ' 00:00:00')
        //             ->setParameter('dateEnd2', $filters['date'] . ' 23:59:59');
        //     } elseif (!empty($filters['year'])) {
        //         $year2 = (int) $filters['year'];
        //         $startOfYear2 = (new \DateTimeImmutable())->setDate($year2, 1, 1)->setTime(0, 0, 0);
        //         $endOfYear2 = (new \DateTimeImmutable())->setDate($year2, 12, 31)->setTime(23, 59, 59);
        //         $qbArrivee->andWhere('c2.dateArrivee >= :yearStart2 AND c2.dateArrivee <= :yearEnd2')
        //             ->setParameter('yearStart2', $startOfYear2)
        //             ->setParameter('yearEnd2', $endOfYear2);
        //     }
        //     if (!empty($filters['priorite']) && $filters['priorite'] !== 'Toutes') {
        //         $qbArrivee->andWhere('c2.priorite = :priorite2');
        //         $qbArrivee->setParameter('priorite2', $filters['priorite']);
        //     }
        //     if (!empty($filters['categorie'])) {
        //         $qbArrivee->andWhere('cat2.id = :categorie2');
        //         $qbArrivee->setParameter('categorie2', $filters['categorie']);
        //     }
        //     if (!empty($filters['type_courrier'])) {
        //         $qbArrivee->andWhere('t2.id = :type2');
        //         $qbArrivee->setParameter('type2', $filters['type_courrier']);
        //     }
        //     if (!empty($filters['classe_courrier'])) {
        //         $qbArrivee->andWhere('LOWER(c2.classeCourrier) LIKE LOWER(:classeCourrier2)')
        //             ->setParameter('classeCourrier2', '%' . trim($filters['classe_courrier']) . '%');
        //     }
        //     if (!empty($filters['provenance'])) {
        //         $qbArrivee->andWhere('p2.id = :provenance2');
        //         $qbArrivee->setParameter('provenance2', $filters['provenance']);
        //     }
        //     if (!empty($filters['service_traitant'])) {
        //         // MÃªme logique que dans le queryBuilder principal pour le service traitant
        //         $subQuery2 = $this->courrierRepository->createQueryBuilder('c22')
        //             ->select('MAX(t22.id)')
        //             ->leftJoin('c22.transmissions', 't22')
        //             ->where('c22.id = c2.id')
        //             ->andWhere('t22.isDelete = false')
        //             ->getDQL();
        //         $qbArrivee->andWhere(
        //             $qbArrivee->expr()->exists(
        //                 $this->courrierRepository->createQueryBuilder('c32')
        //                     ->select('1')
        //                     ->leftJoin('c32.transmissions', 't32')
        //                     ->where('c32.id = c2.id')
        //                     ->andWhere('t32.id = (' . $subQuery2 . ')')
        //                     ->andWhere('t32.idServiceDestinataire = :serviceTraitant2')
        //                     ->getDQL()
        //             )
        //         );
        //         $qbArrivee->setParameter('serviceTraitant2', $filters['service_traitant']);
        //     }
        //     if (!empty($filters['createur'])) {
        //         $qbArrivee->andWhere('u2.id = :createur2');
        //         $qbArrivee->setParameter('createur2', $filters['createur']);
        //     }
        //     if (!empty($filters['statut'])) {
        //         $qbArrivee->andWhere('c2.statut = :statut2');
        //         $qbArrivee->setParameter('statut2', $filters['statut']);
        //     }
        //     if ($filters['has_courrier_depart'] !== null) {
        //         if ($filters['has_courrier_depart'] === true) {
        //             $qbArrivee->andWhere(
        //                 $qbArrivee->expr()->exists(
        //                     $this->courrierRepository->createQueryBuilder('c_dep2')
        //                         ->select('1')
        //                         ->from('App\\Entity\\Cour\\CourrierDepart', 'cd2')
        //                         ->where('cd2.idCourrier = c2.id')
        //                         ->getDQL()
        //                 )
        //             );
        //         } else {
        //             $qbArrivee->andWhere(
        //                 $qbArrivee->expr()->not(
        //                     $qbArrivee->expr()->exists(
        //                         $this->courrierRepository->createQueryBuilder('c_dep2')
        //                             ->select('1')
        //                             ->from('App\\Entity\\Cour\\CourrierDepart', 'cd2')
        //                             ->where('cd2.idCourrier = c2.id')
        //                             ->getDQL()
        //                     )
        //                 )
        //             );
        //         }
        //     }
        //     if (count($idsDeja) > 0) {
        //         $qbArrivee->andWhere($qbArrivee->expr()->notIn('c2.id', ':idsDeja'));
        //         $qbArrivee->setParameter('idsDeja', $idsDeja);
        //     }
        //     $qbArrivee->orderBy('c2.createdAt', $filters['order_by']);
        //     $qbArrivee->setMaxResults($limit - count($results));
        //     $complement = $qbArrivee->getQuery()->getResult();
        //     $results = array_merge($results, $complement);
        // }

        // Pagination propre : d'abord les IDs, ensuite les objets complets
        $limit = $filters['limit'];
        $page = $filters['page'];
        $total = (clone $queryBuilder)->select('COUNT(DISTINCT c.id)')->getQuery()->getSingleScalarResult();

        // Étape 1 : récupérer uniquement les IDs de la page demandée
        $idsQuery = (clone $queryBuilder)
            // ->select('DISTINCT c.id as id') (Alex)
            ->select('c.id as id', 'c.createdAt as createdAt')
            ->distinct()
            ->setMaxResults($limit > 0 ? $limit : null)
            ->setFirstResult($limit > 0 ? ($page - 1) * $limit : 0)
            ->getQuery()
            ->getScalarResult();

        $ids = array_column($idsQuery, 'id');

        // Étape 2 : charger les objets complets uniquement pour ces IDs
        if (empty($ids)) {
            $results = [];
        } else {
            $results = $this->courrierRepository->createQueryBuilder('c')
                ->leftJoin('c.idServiceTraitant', 's')
                ->leftJoin('c.idCreateur', 'u')
                ->leftJoin('c.idProvenance', 'p')
                ->leftJoin('p.categories', 'cat')
                ->leftJoin('c.typeCourrier', 't')
                ->leftJoin('c.transmissions', 'trans')
                ->leftJoin('trans.idServiceDestinataire', 'servTrans')
                ->addSelect('s', 'u', 'p', 'cat', 't', 'trans', 'servTrans')
                ->where('c.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->orderBy('c.createdAt', $filters['order_by'])
                ->getQuery()
                ->getResult();
        }

        $data = array_map(function($c) {
            // ...existing code...
            $derniereTransmission = null;
            if ($c->getTransmissions()->count() > 0) {
                $transmissions = $c->getTransmissions()->toArray();
                usort($transmissions, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
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
                    'nom' => $c->getIdProvenance()->getNom()
                ] : null,
                'createur' => $c->getIdCreateur() ? [
                    'id' => $c->getIdCreateur()->getId(),
                    'nom' => $c->getIdCreateur()->getFullName()
                ] : null,
                'isConfidentiel' => $c->isConfidentiel(),
                'isArchive' => $c->isArchive(),
                'hasCourrierDepart' => $c->getCourrierDeparts()->count() > 0,
                'statut_transmission' => $derniereTransmission?->getStatut(),
                'service_traitement' => $derniereTransmission && $derniereTransmission->getIdServiceDestinataire() ? [
                    'id' => $derniereTransmission->getIdServiceDestinataire()->getId(),
                    'nom' => $derniereTransmission->getIdServiceDestinataire()->getNom()
                ] : null,
            ];
        }, $results);

        // Logger la consultation de la liste avec TOUTES les donnÃ©es
        $this->actionLogger->logView(
            'Courrier',
            null,
            'Consultation de la liste des courriers entrants',
            [
                'total' => (int)$total,
                'page' => $filters['page'],
                'limit' => $filters['limit'],
                'search' => $filters['search'] ?: null,
                'filters' => $filters,
                'courriers' => array_slice($data, 0, 100) // Limiter Ã  100 pour les logs
            ]
        );

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'data' => $data
        ], 200);
    }
}
