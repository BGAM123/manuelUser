<?php

namespace App\Controller\Core\CourrierDepart;

use App\Repository\Cour\CourrierDepartRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\QueryBuilder;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierDepart")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private CourrierDepartRepository $courrierDepartRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private TransmissionRepository $transmissionRepository,
        private CorrespondantRepository $correspondantRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    private function applyGlobalSearch(
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

    #[Route('/core/courrier-depart', name: 'app_core_courrier_depart_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/courrier-depart',
        summary: 'Lister les courriers de départ',
        description: 'Retourne la liste paginée des courriers de départ avec recherche, filtrage, document principal et pièces jointes.',
        tags: ['CourrierDepart'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Recherche globale sur tous les champs du courrier de départ et des tables liées (courrier, type, provenance, catégorie, destinataire, signataire), incluant date de signature et recherche par année.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, description: 'Inclure les éléments supprimés (true/false)', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'isarchive', in: 'query', required: false, description: 'Filter archived courriers de départ. By default, returns only non-archived (false).', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'date', in: 'query', required: false, description: 'Filtrer par date précise (YYYY-MM-DD) sur la date d\'arrivée du courrier lié', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Date de début (YYYY-MM-DD) sur la date d\'arrivée du courrier lié', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Date de fin (YYYY-MM-DD) sur la date d\'arrivée du courrier lié', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Filtrer par année (sur c.dateArrivee)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'priorite', in: 'query', required: false, description: 'Filtrer par priorité du courrier lié', schema: new OA\Schema(type: 'string', enum: ['Toutes', 'Basse', 'Normal', 'Haute'])),
            new OA\Parameter(name: 'statut', in: 'query', required: false, description: 'Filtrer par statut du courrier départ', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'categorie', in: 'query', required: false, description: 'Filtrer par catégorie du correspondant (ID)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type_courrier', in: 'query', required: false, description: 'Filtrer par type de courrier (ID)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'classe_courrier', in: 'query', required: false, description: 'Filtrer par classe de courrier (ex: Urgent, Normal, Confidentiel)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'id_destinataire', in: 'query', required: false, description: 'Filtrer par ID du destinataire (correspondant)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Numéro de page', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Taille de page (0 pour tout)', schema: new OA\Schema(type: 'integer', default: 10))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste récupérée avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 50),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'numeroReference', type: 'string', example: 'CD-2025-001'),
                                    new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                                    new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                                    new OA\Property(property: 'categorie', type: 'string', example: 'Administrative'),
                                    new OA\Property(property: 'document', type: 'string', example: '/uploads/courrier_depart/document/65ff44c4a8b1f.pdf'),
                                    new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2),
                                    new OA\Property(property: 'isArchive', type: 'boolean', example: false, description: 'Indicates if the courrier départ is archived'),
                                    new OA\Property(
                                        property: 'provenancesCopie',
                                        type: 'array',
                                        items: new OA\Items(
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                                new OA\Property(property: 'nom', type: 'string', example: 'Ministère de l\'Agriculture'),
                                            ]
                                        ),
                                        description: 'Liste des correspondants en copie avec leurs détails'
                                    ),
                                     new OA\Property(
                                         property: 'piecesJointes',
                                         type: 'array',
                                         items: new OA\Items(
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                                new OA\Property(property: 'nom', type: 'string', example: 'annexe.pdf'),
                                                new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier_depart/pieces/65ff44c4a8b1f.pdf'),
                                                new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                                            ]
                                        )
                                    ),
                                    new OA\Property(
                                        property: 'courrierInterne',
                                        type: 'object',
                                        nullable: true,
                                        description: 'Courrier interne lié (si disponible)',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', nullable: true, example: 5),
                                            new OA\Property(property: 'numero', type: 'string', nullable: true, example: 'CI-2025-003'),
                                            new OA\Property(property: 'objet', type: 'string', nullable: true, example: 'Demande d\'avis'),
                                            new OA\Property(property: 'dateReponse', type: 'string', nullable: true, example: '2025-02-14'),
                                            new OA\Property(property: 'typeReponse', type: 'string', nullable: true, example: 'Avis'),
                                            new OA\Property(property: 'priorite', type: 'string', nullable: true, example: 'Haute'),
                                            new OA\Property(property: 'statut', type: 'string', nullable: true, example: 'En cours'),
                                            new OA\Property(property: 'isGeled', type: 'boolean', nullable: true, example: false),
                                            new OA\Property(property: 'isinstance', type: 'boolean', nullable: true, example: false),
                                            new OA\Property(property: 'nombrePieceJointe', type: 'integer', nullable: true, example: 2),
                                            new OA\Property(
                                                property: 'piecesJointes',
                                                type: 'array',
                                                items: new OA\Items(
                                                    type: 'object',
                                                    properties: [
                                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                                        new OA\Property(property: 'nom', type: 'string', example: 'annexe.pdf'),
                                                        new OA\Property(property: 'intitule', type: 'string', nullable: true, example: 'Annexe'),
                                                        new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier_interne/pieces/65ff44c4a8b1f.pdf'),
                                                        new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                                                        new OA\Property(property: 'createdAt', type: 'string', nullable: true, example: '2025-12-18 10:30:00'),
                                                    ]
                                                )
                                            ),
                                        ]
                                    ),
                                     new OA\Property(
                                         property: 'transmissions',
                                         type: 'array',
                                         description: 'Transmissions liees au courrier associe',
                                        items: new OA\Items(
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 12),
                                                new OA\Property(property: 'serviceDestinataire', type: 'object'),
                                                new OA\Property(property: 'emetteur', type: 'object'),
                                                new OA\Property(property: 'structuresCopie', type: 'array', items: new OA\Items(type: 'integer')),
                                                new OA\Property(property: 'dateInstruction', type: 'string', example: '2025-12-18 10:30:00'),
                                                new OA\Property(property: 'dateReception', type: 'string', nullable: true, example: '2025-12-18 12:05:00'),
                                                new OA\Property(property: 'instruction', type: 'string', example: 'A traiter en urgence'),
                                                new OA\Property(property: 'delaiTraitement', type: 'integer', example: 5),
                                                new OA\Property(property: 'typeTransfert', type: 'string', example: 'Pour traitement'),
                                                new OA\Property(property: 'accuseReception', type: 'boolean', example: false),
                                                new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                                                new OA\Property(property: 'isinstance', type: 'boolean', example: false),
                                                new OA\Property(property: 'isArchive', type: 'boolean', example: false),
                                                new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2),
                                                new OA\Property(
                                                    property: 'piecesJointes',
                                                    type: 'array',
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
                                            ]
                                        )
                                    ),
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
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCourrierDepartCollection');

        $search         = (string) $request->query->get('search', '');
        $isDelete       = filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN);
        $isArchive      = filter_var($request->query->get('isarchive', false), FILTER_VALIDATE_BOOLEAN);
        $date           = $request->query->get('date');
        $startDate      = $request->query->get('start_date');
        $endDate        = $request->query->get('end_date');
        $year           = $request->query->get('year');
        $priorite       = $request->query->get('priorite');
        $statut         = $request->query->get('statut');
        $categorie      = $request->query->get('categorie');
        $typeCourrier   = $request->query->get('type_courrier');
        $classeCourrier = $request->query->get('classe_courrier');
        $idDestinataire = $request->query->get('id_destinataire');
        $page           = max(1, (int) $request->query->get('page', 1));
        $limit          = (int) $request->query->get('limit', 10);

        $qb = $this->courrierDepartRepository->createQueryBuilder('cd')
            ->leftJoin('cd.destinataire', 'd')
            ->leftJoin('cd.idSignataire', 's')
            ->leftJoin('cd.idCourrier', 'c')
            ->leftJoin('cd.idCourrierInterne', 'ci')
            ->leftJoin('ci.typeReponse', 'tr')
            ->leftJoin('c.typeCourrier', 'tc')
            ->leftJoin('c.idProvenance', 'prov')
            ->leftJoin('prov.categories', 'cat')
            ->addSelect('d', 's', 'c', 'ci', 'tr', 'tc', 'prov', 'cat')
            ->where('cd.isDelete = :isDelete')
            ->setParameter('isDelete', $isDelete)
            ->andWhere('cd.isArchive = :isArchive')
            ->setParameter('isArchive', $isArchive)
            ->orderBy('cd.createdAt', 'DESC');

        // ðŸ” Recherche globale courrier dÃ©part + tables liÃ©es (+ dates)
        $this->applyGlobalSearch($qb, $search, 'cd', 'd', 's', 'c', 'tc', 'prov', 'cat', 'search');

        // ðŸ“† Filtres de date sur c.dateArrivee
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

        // ðŸ§© Autres filtres
        if (!empty($priorite) && $priorite !== 'Toutes') {
            $qb->andWhere('c.priorite = :priorite')
               ->setParameter('priorite', $priorite);
        }

        if (!empty($statut)) {
            $qb->andWhere('LOWER(cd.statut) = LOWER(:statut)')
               ->setParameter('statut', trim((string) $statut));
        }

        if (!empty($categorie)) {
            $qb->andWhere('cat.id = :categorie')
               ->setParameter('categorie', (int) $categorie);
        }

        if (!empty($typeCourrier)) {
            $qb->andWhere('tc.id = :typeCourrier')
               ->setParameter('typeCourrier', (int) $typeCourrier);
        }

        if (!empty($classeCourrier)) {
            $qb->andWhere('LOWER(cd.classeCourrier) LIKE LOWER(:classeCourrier)')
               ->setParameter('classeCourrier', '%' . trim($classeCourrier) . '%');
        }

        if (!empty($idDestinataire)) {
            $qb->andWhere('d.id = :idDestinataire')
               ->setParameter('idDestinataire', (int) $idDestinataire);
        }

        // Total
        $total = (int) (clone $qb)
            ->select('COUNT(cd.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        // Pagination
        if ($limit > 0) {
            $qb->setMaxResults($limit)
               ->setFirstResult(($page - 1) * $limit);
        }

        $results = $qb->getQuery()->getResult();

        // ðŸ“Ž RÃ©cupÃ©rer toutes les piÃ¨ces jointes en une seule requÃªte (optimisation)
        $courrierDepartIds = array_map(fn($cd) => $cd->getId(), $results);
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

        // ðŸ“Ž RÃ©cupÃ©rer les piÃ¨ces jointes des courriers arrivÃ©s liÃ©s (en une seule requÃªte)
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
        // Récupérer les pièces jointes des courriers internes liés (en une seule requête)
        $courrierInterneIds = [];
        foreach ($results as $cd) {
            $cid = $cd->getIdCourrierInterne()?->getId();
            if ($cid !== null) {
                $courrierInterneIds[] = $cid;
            }
        }
        $courrierInterneIds = array_unique($courrierInterneIds);

        $piecesJointesCourrierInterneGrouped = [];
        if (!empty($courrierInterneIds)) {
            $piecesJointesCourrierInterne = $this->pieceJointeRepository->createQueryBuilder('pj')
                ->where('pj.typeParent = :typeParent')
                ->andWhere('pj.idParent IN (:ids)')
                ->andWhere('pj.isDelete = false')
                ->setParameter('typeParent', 'CourrierInterne')
                ->setParameter('ids', $courrierInterneIds)
                ->orderBy('pj.id', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($piecesJointesCourrierInterne as $pj) {
                $piecesJointesCourrierInterneGrouped[$pj->getIdParent()][] = $pj;
            }
        }
        // Recuperer les transmissions liees aux courriers (en une seule requete)
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
        // Recuperer les pieces jointes des transmissions (en une seule requete)
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

        // âœ… RÃ©cupÃ©rer tous les IDs de correspondants en copie pour optimisation
        $allProvenancesCopieIds = [];
        foreach ($results as $cd) {
            if ($cd->getProvenancesCopie()) {
                $allProvenancesCopieIds = array_merge($allProvenancesCopieIds, $cd->getProvenancesCopie());
            }
        }
        $allProvenancesCopieIds = array_unique($allProvenancesCopieIds);

        // RÃ©cupÃ©rer tous les correspondants en une seule requÃªte
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

        $data = array_map(function ($cd) use ($piecesJointesGrouped, $piecesJointesCourrierGrouped, $piecesJointesCourrierInterneGrouped, $correspondantsMap, $transmissionsByCourrierId, $piecesJointesTransmissionGrouped) {
            $piecesJointes = $piecesJointesGrouped[$cd->getId()] ?? [];
            $courrierArriveId = $cd->getIdCourrier()?->getId();
            $piecesJointesCourrier = $courrierArriveId !== null
                ? ($piecesJointesCourrierGrouped[$courrierArriveId] ?? [])
                : [];
            $transmissions = $courrierArriveId !== null
                ? ($transmissionsByCourrierId[$courrierArriveId] ?? [])
                : [];

            $courrierInterneId = $cd->getIdCourrierInterne()?->getId();
            $piecesJointesCourrierInterne = $courrierInterneId !== null
                ? ($piecesJointesCourrierInterneGrouped[$courrierInterneId] ?? [])
                : [];
            
            // âœ… Enrichir provenancesCopie avec id et nom
            $provenancesCopieEnriched = [];
            if ($cd->getProvenancesCopie()) {
                foreach ($cd->getProvenancesCopie() as $corrId) {
                    if (isset($correspondantsMap[$corrId])) {
                        $provenancesCopieEnriched[] = $correspondantsMap[$corrId];
                    }
                }
            }
            
            return [
                'id'               => $cd->getId(),
                'numeroReference'  => $cd->getNumeroReference(),
                'numeroActe'       => $cd->getNumeroActe(),
                'statut'           => $cd->getStatut()??'Transmis',
                'dateSignature'    => $cd->getDateSignature()?->format('Y-m-d'),
                'typeCourrier'     => $cd->getTypeCourrier(),
                'commentaire'      => $cd->getCommentaire(),
                'classeCourrier'   => $cd->getClasseCourrier(),
                'categorie'        => $cd->getCategorie(),
                'document'         => $cd->getDocument(),
                'email'            => $cd->getEmail(),
                'numeroTelephone'  => $cd->getNumeroTelephone(),
                'nombrePieceJointe' => $cd->getNombrePieceJointe(),
                'provenancesCopie' => $provenancesCopieEnriched, 
                'piecesJointes'    => array_map(fn($pj) => [
                    'id'      => $pj->getId(),
                    'nom'     => $pj->getNom(),
                    'chemin'  => $pj->getChemin(),
                    'type'    => $pj->getType(),
                ], $piecesJointes),
                'destinataire'     => [
                    'id'  => $cd->getDestinataire()?->getId(),
                    'nom' => $cd->getDestinataire()?->getNom(),
                ],
                'signataire'       => [
                    'id'       => $cd->getIdSignataire()?->getId(),
                    'fullName' => $cd->getIdSignataire()?->getFullName(),
                ],
                'courrier'         => $cd->getIdCourrier() ? [
                    'id'                 => $cd->getIdCourrier()->getId(),
                    'numero'             => $cd->getIdCourrier()->getNumero(),
                    'reference'          => $cd->getIdCourrier()->getNumero(),
                    'objet'              => $cd->getIdCourrier()->getObjet(),
                    'is_geled'           => $cd->getIdCourrier()->isGeled(),
                    'dateArrivee'        => $cd->getIdCourrier()->getDateArrivee()?->format('Y-m-d'),
                    'dateEnregistrement' => $cd->getIdCourrier()->getDateEnregistrement()?->format('Y-m-d'),
                    'typeCourrier'       => $cd->getIdCourrier()->getTypeCourrier()?->getNom(),
                    'provenance'         => $cd->getIdCourrier()->getIdProvenance()?->getNom(),
                    'categorie'          => $cd->getIdCourrier()->getIdProvenance() && $cd->getIdCourrier()->getIdProvenance()->getCategories()->count() > 0 
                        ? $cd->getIdCourrier()->getIdProvenance()->getCategories()->first()->getNom()
                        : null,
                    'priorite'           => $cd->getIdCourrier()->getPriorite(),
                    'document'           => $cd->getIdCourrier()->getDocument(),
                    'piecesJointes'      => array_map(fn($pj) => [
                        'id'       => $pj->getId(),
                        'nom'      => $pj->getNom(),
                        'intitule' => $pj->getIntitule(),
                        'chemin'   => $pj->getChemin(),
                        'type'     => $pj->getType(),
                    ], $piecesJointesCourrier),
                ] : null,
                'courrierInterne'  => $cd->getIdCourrierInterne() ? [
                    'id' => $cd->getIdCourrierInterne()->getId(),
                    'numero' => $cd->getIdCourrierInterne()->getNumero(),
                    'objet' => $cd->getIdCourrierInterne()->getObjet(),
                    'commentairePublic' => $cd->getIdCourrierInterne()->getCommentairePublic(),
                    'commentaireInterne' => $cd->getIdCourrierInterne()->getCommentaireInterne(),
                    'dateReponse' => $cd->getIdCourrierInterne()->getDateReponse()?->format('Y-m-d'),
                    'typeReponse' => $cd->getIdCourrierInterne()->getTypeReponse()?->getNom(),
                    'priorite' => $cd->getIdCourrierInterne()->getPriorite(),
                    'statut' => $cd->getIdCourrierInterne()->getStatut(),
                    'isGeled' => $cd->getIdCourrierInterne()->isGeled(),
                    'isinstance' => $cd->getIdCourrierInterne()->isinstance(),
                    'nombrePieceJointe' => $cd->getIdCourrierInterne()->getNombrePieceJointe(),
                    'piecesJointes' => array_map(fn($pj) => [
                        'id' => $pj->getId(),
                        'nom' => $pj->getNom(),
                        'intitule' => $pj->getIntitule(),
                        'chemin' => $pj->getChemin(),
                        'type' => $pj->getType(),
                        'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
                    ], $piecesJointesCourrierInterne),
                    'createdAt' => $cd->getIdCourrierInterne()->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'updatedAt' => $cd->getIdCourrierInterne()->getUpdatedAt()?->format('Y-m-d H:i:s'),
                ] : null,
                'transmissions'    => array_map(function ($t) use ($piecesJointesTransmissionGrouped) {
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
                        // 'pieceJointe' => $t->getPieceJointe(),
                        'piecesJointes' => array_map(fn($pj) => [
                            'id' => $pj->getId(),
                            'nom' => $pj->getNom(),
                            'intitule' => $pj->getIntitule(),
                            'chemin' => $pj->getChemin(),
                            'type' => $pj->getType(),
                        ], $transmissionPieces),
                        'createdAt' => $t->getCreatedAt()?->format('Y-m-d H:i:s'),
                    ];
                }, $transmissions),
                'isDelete'         => $cd->isDelete(),
                'isArchive'        => $cd->isArchive(),
                'createdAt'        => $cd->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $results);

        // Logger la consultation de la collection avec TOUTES les donnÃ©es
        $this->actionLogger->logView(
            'CourrierDepart',
            null,
            'Consultation de la liste des courriers départ',
            [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'search' => $search ?: null,
                'filters' => [
                    'is_delete' => $isDelete,
                    'isarchive' => $isArchive,
                    'date' => $date,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'year' => $year,
                    'priorite' => $priorite,
                    'statut' => $statut,
                    'categorie' => $categorie,
                    'type_courrier' => $typeCourrier,
                    'classe_courrier' => $classeCourrier,
                    'id_destinataire' => $idDestinataire,
                ],
                // Logger les donnÃ©es complÃ¨tes des courriers (limitÃ© aux 100 premiers pour ne pas surcharger)
                'courriers' => array_slice($data, 0, 100)
            ]
        );

        return $this->json([
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'data'  => $data,
        ], 200);
    }
}
