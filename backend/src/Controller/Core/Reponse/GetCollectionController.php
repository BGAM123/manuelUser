<?php

namespace App\Controller\Core\Reponse;

use App\Repository\Cour\ReponseRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;

#[OA\Tag(name: "Reponse")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private ReponseRepository $reponseRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private TransmissionReponseRepository $transmissionReponseRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/reponse', name: 'app_core_reponse_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/reponse',
        summary: 'Lister les réponses avec filtres et pagination (2 blocs distincts)',
        description: '
## â€¹ Description
Retourne la liste des réponses organisée en **2 blocs distincts** :
1. **mesReponses** : Réponses créées par l\'utilisateur connecté (rédacteur)
2. **reponsesRecuesParMonService** : Réponses reçues par le service de l\'utilisateur

Chaque réponse inclut :
- Les courriers associés (relation ManyToMany)
- Les types de courrier
- Le service destinataire
- Le rédacteur
- Les pièces jointes
- Les informations de transmission

## Filtres disponibles
Tous les filtres s\'appliquent aux deux blocs simultanément.',
        tags: ['Reponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'order_by', 
                in: 'query', 
                required: false, 
                description: 'Ordre de tri des réponses (par date de réponse)', 
                schema: new OA\Schema(type: 'string', enum: ['DESC', 'ASC'], default: 'DESC'),
                example: 'DESC'
            ),
            new OA\Parameter(
                name: 'start_date', 
                in: 'query', 
                required: false, 
                description: 'Date de début du filtre (format: YYYY-MM-DD). à utiliser avec end_date pour un intervalle.', 
                schema: new OA\Schema(type: 'string', format: 'date'),
                example: '2025-01-01'
            ),
            new OA\Parameter(
                name: 'end_date', 
                in: 'query', 
                required: false, 
                description: 'Date de fin du filtre (format: YYYY-MM-DD). à utiliser avec start_date.', 
                schema: new OA\Schema(type: 'string', format: 'date'),
                example: '2025-12-31'
            ),
            new OA\Parameter(
                name: 'date', 
                in: 'query', 
                required: false, 
                description: 'Filtrer sur une date spécifique (format: YYYY-MM-DD)', 
                schema: new OA\Schema(type: 'string', format: 'date'),
                example: '2025-12-17'
            ),
            new OA\Parameter(
                name: 'priorite', 
                in: 'query', 
                required: false, 
                description: 'Filtrer par priorité du courrier', 
                schema: new OA\Schema(type: 'string', enum: ['Toutes', 'Basse', 'Normal', 'Haute']),
                example: 'Haute'
            ),
            new OA\Parameter(
                name: 'categorie', 
                in: 'query', 
                required: false, 
                description: 'Filtrer par ID de catégorie du correspondant', 
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
                description: 'Filtrer par ID de courrier spécifique', 
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
                description: 'Filtrer par ID de service destinataire spécifique (remplace le filtre automatique)', 
                schema: new OA\Schema(type: 'integer'),
                example: 25
            ),
            new OA\Parameter(
                name: 'service_destinataire_reponse_liee',
                in: 'query',
                required: false,
                description: 'Filtrer par ID du service destinataire de la derniere transmission des reponses liees',
                schema: new OA\Schema(type: 'integer'),
                example: 283
            ),
            new OA\Parameter(
                name: 'statut_reponse_liee',
                in: 'query',
                required: false,
                description: 'Filtrer par statut de la derniere transmission des reponses liees',
                schema: new OA\Schema(type: 'string'),
                example: 'Recu'
            ),
            new OA\Parameter(
                name: 'redacteur', 
                in: 'query', 
                required: false, 
                description: 'Filtrer par ID du rédacteur (utilisateur)', 
                schema: new OA\Schema(type: 'integer'),
                example: 10
            ),
            new OA\Parameter(
                name: 'type_reponse', 
                in: 'query', 
                required: false, 
                description: 'Filtrer par ID de type de réponse', 
                schema: new OA\Schema(type: 'integer'),
                example: 3
            ),
            new OA\Parameter(
                name: 'page', 
                in: 'query', 
                required: false, 
                description: 'Numéro de la page (pagination)', 
                schema: new OA\Schema(type: 'integer', default: 1),
                example: 1
            ),
            new OA\Parameter(
                name: 'limit', 
                in: 'query', 
                required: false, 
                description: 'Nombre d\'éléments par page. Utiliser 0 pour tout récupérer.', 
                schema: new OA\Schema(type: 'integer', default: 10),
                example: 10
            ),
            new OA\Parameter(
                name: 'is_delete', 
                in: 'query', 
                required: false, 
                description: 'Inclure les réponses supprimées (soft delete)', 
                schema: new OA\Schema(type: 'boolean', default: false),
                example: false
            ),
            new OA\Parameter(
                name: 'search', 
                in: 'query', 
                required: false, 
                description: 'Recherche textuelle sur les données de la réponse retournée (hors objet courriers)', 
                schema: new OA\Schema(type: 'string'),
                example: 'demande congé'
            ),
            new OA\Parameter(
                name: 'year', 
                in: 'query', 
                required: false, 
                description: 'Filtrer par année d\'arrivée du courrier', 
                schema: new OA\Schema(type: 'integer'),
                example: 2025
            ),
        ],
        responses: [
            new OA\Response(
                response: 200, 
                description: '**Succès** - Liste des réponses récupérée et organisée en 2 blocs distincts',
                content: new OA\JsonContent(
                    example: [
                        'page' => 1,
                        'limit' => 10,
                        'mesReponses' => [
                            'description' => 'Réponses créées par l\'utilisateur connecté (rédacteur)',
                            'total' => 5,
                            'data' => [
                                [
                                    'id' => 123,
                                    'objet' => 'Réponse à la demande de congé annuel 2025',
                                    'commentairePublic' => 'Demande approuvée avec réserve',
                                    'classeCourrier' => 'Urgent',
                                    'typeTransmission' => 'Electronique',
                                    'dateReponse' => '2025-12-15 14:30:00',
                                    'createdAt' => '2025-12-15 14:25:00',
                                    'courriers' => [
                                        [
                                            'id' => 456,
                                            'numero' => 'CRR-2025-00456',
                                            'reference' => 'REF/DRH/2025/0123',
                                            'objet' => 'Demande de congé annuel',
                                            'dateArrivee' => '2025-12-10',
                                            'dateEnregistrement' => '2025-12-10',
                                            'typeCourrier' => 'Demande administrative',
                                            'provenance' => 'Direction des Ressources Humaines',
                                            'categorie' => 'Administration',
                                            'priorite' => 'Haute'
                                        ]
                                    ],
                                    'typesCourrier' => [
                                        [
                                            'id' => 12,
                                            'nom' => 'Réponse administrative',
                                            'type' => 'Sortant'
                                        ]
                                    ],
                                    'idTransmission' => [45, 46, 47],
                                    'serviceDestinataire' => [
                                        'id' => 25,
                                        'nom' => 'Direction des Affaires Juridiques',
                                        'sigle' => 'DAJ'
                                    ],
                                    'redacteur' => [
                                        'id' => 10,
                                        'fullName' => 'Jean DUPONT',
                                        'email' => 'jean.dupont@minepia.cm'
                                    ],
                                    'piecesJointes' => [
                                        [
                                            'id' => 89,
                                            'nom' => 'decision_conge_2025.pdf',
                                            'chemin' => '/uploads/reponses/2025/12/decision_conge_2025.pdf',
                                            'type' => 'application/pdf'
                                        ],
                                        [
                                            'id' => 90,
                                            'nom' => 'planning_remplacement.xlsx',
                                            'chemin' => '/uploads/reponses/2025/12/planning_remplacement.xlsx',
                                            'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                                        ]
                                    ]
                                ],
                                [
                                    'id' => 124,
                                    'objet' => 'Notification de mutation interne',
                                    'commentairePublic' => 'Mutation approuvée - Prise de service le 01/01/2026',
                                    'classeCourrier' => 'Confidentiel',
                                    'typeTransmission' => 'Physique',
                                    'dateReponse' => '2025-12-14 10:15:00',
                                    'createdAt' => '2025-12-14 10:10:00',
                                    'courriers' => [
                                        [
                                            'id' => 457,
                                            'numero' => 'CRR-2025-00457',
                                            'reference' => 'REF/DG/2025/0089',
                                            'objet' => 'Demande de mutation',
                                            'dateArrivee' => '2025-12-05',
                                            'dateEnregistrement' => '2025-12-05',
                                            'typeCourrier' => 'Note de service',
                                            'provenance' => 'Direction Générale',
                                            'categorie' => 'Personnel',
                                            'priorite' => 'Haute'
                                        ]
                                    ],
                                    'typesCourrier' => [
                                        [
                                            'id' => 15,
                                            'nom' => 'Décision administrative',
                                            'type' => 'Sortant'
                                        ]
                                    ],
                                    'idTransmission' => [48, 49],
                                    'serviceDestinataire' => [
                                        'id' => 30,
                                        'nom' => 'Direction Régionale Nord',
                                        'sigle' => 'DRN'
                                    ],
                                    'redacteur' => [
                                        'id' => 10,
                                        'fullName' => 'Jean DUPONT',
                                        'email' => 'jean.dupont@minepia.cm'
                                    ],
                                    'piecesJointes' => [
                                        [
                                            'id' => 91,
                                            'nom' => 'decision_mutation.pdf',
                                            'chemin' => '/uploads/reponses/2025/12/decision_mutation.pdf',
                                            'type' => 'application/pdf'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'reponsesRecuesParMonService' => [
                            'description' => 'Réponses envoyées au service de l\'utilisateur connecté',
                            'total' => 12,
                            'data' => [
                                [
                                    'id' => 200,
                                    'objet' => 'Validation du budget prévisionnel 2026',
                                    'commentairePublic' => 'Budget approuvé sous condition de révision trimestrielle',
                                    'classeCourrier' => 'Normal',
                                    'typeTransmission' => 'Electronique',
                                    'dateReponse' => '2025-12-16 16:00:00',
                                    'createdAt' => '2025-12-16 15:55:00',
                                    'courriers' => [
                                        [
                                            'id' => 500,
                                            'numero' => 'CRR-2025-00500',
                                            'reference' => 'REF/DAF/2025/0250',
                                            'objet' => 'Proposition budget 2026',
                                            'dateArrivee' => '2025-12-01',
                                            'dateEnregistrement' => '2025-12-01',
                                            'typeCourrier' => 'Note de service',
                                            'provenance' => 'Direction Administrative et Financière',
                                            'categorie' => 'Finance',
                                            'priorite' => 'Normal'
                                        ]
                                    ],
                                    'typesCourrier' => [
                                        [
                                            'id' => 18,
                                            'nom' => 'Validation budgétaire',
                                            'type' => 'Sortant'
                                        ]
                                    ],
                                    'idTransmission' => [65, 66],
                                    'serviceDestinataire' => [
                                        'id' => 20,
                                        'nom' => 'Direction des Ressources Humaines',
                                        'sigle' => 'DRH'
                                    ],
                                    'redacteur' => [
                                        'id' => 35,
                                        'fullName' => 'Marie NGONO',
                                        'email' => 'marie.ngono@minepia.cm'
                                    ],
                                    'piecesJointes' => [
                                        [
                                            'id' => 150,
                                            'nom' => 'budget_approuve_2026.xlsx',
                                            'chemin' => '/uploads/reponses/2025/12/budget_approuve_2026.xlsx',
                                            'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                                        ],
                                        [
                                            'id' => 151,
                                            'nom' => 'conditions_approbation.pdf',
                                            'chemin' => '/uploads/reponses/2025/12/conditions_approbation.pdf',
                                            'type' => 'application/pdf'
                                        ]
                                    ]
                                ],
                                [
                                    'id' => 201,
                                    'objet' => 'Autorisation de mission à l\'étranger',
                                    'commentairePublic' => 'Mission autorisée - Prévoir ordre de mission',
                                    'classeCourrier' => 'Urgent',
                                    'typeTransmission' => 'Electronique',
                                    'dateReponse' => '2025-12-15 09:30:00',
                                    'createdAt' => '2025-12-15 09:25:00',
                                    'courriers' => [
                                        [
                                            'id' => 501,
                                            'numero' => 'CRR-2025-00501',
                                            'reference' => 'REF/DCOOP/2025/0078',
                                            'objet' => 'Demande de mission internationale',
                                            'dateArrivee' => '2025-12-08',
                                            'dateEnregistrement' => '2025-12-08',
                                            'typeCourrier' => 'Demande administrative',
                                            'provenance' => 'Direction de la Coopération',
                                            'categorie' => 'Coopération internationale',
                                            'priorite' => 'Haute'
                                        ]
                                    ],
                                    'typesCourrier' => [
                                        [
                                            'id' => 20,
                                            'nom' => 'Autorisation de mission',
                                            'type' => 'Sortant'
                                        ]
                                    ],
                                    'idTransmission' => [67],
                                    'serviceDestinataire' => [
                                        'id' => 20,
                                        'nom' => 'Direction des Ressources Humaines',
                                        'sigle' => 'DRH'
                                    ],
                                    'redacteur' => [
                                        'id' => 42,
                                        'fullName' => 'Paul KAMGA',
                                        'email' => 'paul.kamga@minepia.cm'
                                    ],
                                    'piecesJointes' => [
                                        [
                                            'id' => 152,
                                            'nom' => 'autorisation_mission.pdf',
                                            'chemin' => '/uploads/reponses/2025/12/autorisation_mission.pdf',
                                            'type' => 'application/pdf'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'totalGlobal' => 17
                    ]
                )
            ),
            new OA\Response(
                response: 401, 
                description: '**Non autorisé** - Token JWT manquant ou invalide',
                content: new OA\JsonContent(
                    example: [
                        'code' => 401,
                        'message' => 'JWT Token not found'
                    ]
                )
            ),
            new OA\Response(
                response: 403, 
                description: 'à« **Accès refusé** - Permissions insuffisantes',
                content: new OA\JsonContent(
                    example: [
                        'code' => 403,
                        'message' => 'Access denied. You do not have permission to view this resource.'
                    ]
                )
            )
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetReponseCollection');

        /** @var \App\Entity\Core\User $currentUser */
        $currentUser = $this->getUser();
        $userService = $currentUser->getIdService();

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
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => (int)$request->query->get('limit', 10),
        ];

        $queryBuilderMyResponses = $this->reponseRepository->createQueryBuilder('r')
            ->leftJoin('r.courriers', 'c')
            ->leftJoin('c.typeCourrier', 'tc')
            ->leftJoin('c.idProvenance', 'prov')
            ->leftJoin('prov.categories', 'cat')
            ->leftJoin('r.idServiceDestinataire', 'sd')
            ->leftJoin('r.idRedacteur', 'red')
            ->leftJoin('r.typeReponse', 'tr')
            ->addSelect('c', 'tc', 'prov', 'cat', 'sd', 'red', 'tr')
            ->where('r.isDelete = :isDelete')
            ->andWhere('r.idRedacteur = :currentUser')
            ->setParameter('isDelete', $filters['is_delete'])
            ->setParameter('currentUser', $currentUser);

        $queryBuilderServiceResponses = $this->reponseRepository->createQueryBuilder('r')
            ->leftJoin('r.courriers', 'c')
            ->leftJoin('c.typeCourrier', 'tc')
            ->leftJoin('c.idProvenance', 'prov')
            ->leftJoin('prov.categories', 'cat')
            ->leftJoin('r.idServiceDestinataire', 'sd')
            ->leftJoin('r.idRedacteur', 'red')
            ->leftJoin('r.typeReponse', 'tr')
            ->addSelect('c', 'tc', 'prov', 'cat', 'sd', 'red', 'tr')
            ->where('r.isDelete = :isDelete')
            ->andWhere('r.idServiceDestinataire = :userService')
            ->andWhere('r.idRedacteur != :currentUser')
            ->setParameter('isDelete', $filters['is_delete'])
            ->setParameter('userService', $userService)
            ->setParameter('currentUser', $currentUser);

        $applyFilters = function($qb) use ($filters) {

            // Ã°Å¸â€œâ€  Filtres de date
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

            // Ã°Å¸Â§Â© Filtres sur le COURRIER
            if (!empty($filters['priorite']) && $filters['priorite'] !== 'Toutes') {
                $qb->andWhere('c.priorite = :priorite')
                   ->setParameter('priorite', $filters['priorite']);
            }
            if (!empty($filters['categorie'])) {
                $qb->andWhere('cat.id = :categorie')
                   ->setParameter('categorie', $filters['categorie']);
            }
            // type_courrier est applique apres requete pour gerer
            // a la fois les courriers lies et typesCourrierIds (JSON).
            if (!empty($filters['courrier'])) {
                $qb->andWhere('c.id = :courrier')
                   ->setParameter('courrier', $filters['courrier']);
            }
            if (!empty($filters['provenance'])) {
                $qb->andWhere('prov.id = :provenance')
                   ->setParameter('provenance', $filters['provenance']);
            }

            // Ã°Å¸Â§Â© Filtres sur la REPONSE
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

            // Filtres spÃƒÂ©cifiques au paramÃƒÂ¨tre (si fournis explicitement)
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

        // Appliquer les filtres aux deux query builders
        $applyFilters($queryBuilderMyResponses);
        $applyFilters($queryBuilderServiceResponses);

        // Ã°Å¸â€â€ž Tri
        $queryBuilderMyResponses->orderBy('r.createdAt', $filters['order_by']);
        $queryBuilderServiceResponses->orderBy('r.createdAt', $filters['order_by']);

        // Ã°Å¸â€œâ€ž Compter les totaux
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
            $matchesPostFilters = function ($reponse) use ($typeCourrierFilter, $linkedServiceDestinataireFilter, $linkedStatutFilterNormalized, $searchFilterNormalized): bool {
                if ($typeCourrierFilter !== null) {
                    $hasTypeCourrier = false;
                    foreach ($reponse->getCourriers() as $courrier) {
                        $typeCourrier = $courrier->getTypeCourrier();
                        if ($typeCourrier && $typeCourrier->getId() === $typeCourrierFilter) {
                            $hasTypeCourrier = true;
                            break;
                        }
                    }

                    if (!$hasTypeCourrier) {
                        $typesCourrierIds = $reponse->getTypesCourrierIds();
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

                if ($linkedServiceDestinataireFilter !== null || $linkedStatutFilterNormalized !== null) {
                    $linkedIds = is_array($reponse->getIdReponses()) ? $reponse->getIdReponses() : [];
                    if (empty($linkedIds)) {
                        $linkedIds = [$reponse->getId()];
                    }

                    $hasLinkedServiceDestinataire = $linkedServiceDestinataireFilter === null;
                    $hasLinkedStatut = $linkedStatutFilterNormalized === null;
                    foreach ($linkedIds as $reponseId) {
                        if (!is_numeric($reponseId)) {
                            continue;
                        }

                        $lastTransmission = $this->transmissionReponseRepository->findLatestByReponseId((int) $reponseId);
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

                    // Champs de la reponse (hors objet courriers)
                    $pushSearchValue($searchValues, $reponse->getId());
                    $pushSearchValue($searchValues, $reponse->getObjet());
                    $pushSearchValue($searchValues, $reponse->getCommentairePublic());
                    $pushSearchValue($searchValues, $reponse->getClasseCourrier());
                    $pushSearchValue($searchValues, $reponse->getTypeTransmission());
                    $pushSearchValue($searchValues, $reponse->getPriorite());
                    $pushSearchValue($searchValues, $reponse->getStatut());
                    $pushSearchValue($searchValues, $reponse->isAccuseReception());
                    $pushSearchValue($searchValues, $reponse->isinstance());
                    $pushSearchValue($searchValues, $reponse->isGeled());
                    $pushSearchValue($searchValues, $reponse->getDateReponse()?->format('Y-m-d'));
                    $pushSearchValue($searchValues, $reponse->getNombrePieceJointe());
                    $pushSearchValue($searchValues, $reponse->getCreatedAt()?->format('Y-m-d'));

                    $serviceDestinataire = $reponse->getIdServiceDestinataire();
                    if ($serviceDestinataire) {
                        $pushSearchValue($searchValues, $serviceDestinataire->getId());
                        $pushSearchValue($searchValues, $serviceDestinataire->getNom());
                        $pushSearchValue($searchValues, $serviceDestinataire->getSigle());
                    }

                    $redacteur = $reponse->getIdRedacteur();
                    if ($redacteur) {
                        $pushSearchValue($searchValues, $redacteur->getId());
                        $pushSearchValue($searchValues, $redacteur->getFullName());
                        $pushSearchValue($searchValues, $redacteur->getEmail());
                    }

                    if (is_array($reponse->getIdTransmission())) {
                        foreach ($reponse->getIdTransmission() as $idTransmission) {
                            $pushSearchValue($searchValues, $idTransmission);
                        }
                    }

                    if (is_array($reponse->getIdCourrierInternes())) {
                        foreach ($reponse->getIdCourrierInternes() as $idCourrierInterne) {
                            $pushSearchValue($searchValues, $idCourrierInterne);
                        }
                    }

                    $typesCourrierIds = $reponse->getTypesCourrierIds();
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

                    $linkedIdsForSearch = is_array($reponse->getIdReponses()) ? $reponse->getIdReponses() : [];
                    if (empty($linkedIdsForSearch)) {
                        $linkedIdsForSearch = [$reponse->getId()];
                    }
                    foreach ($linkedIdsForSearch as $reponseId) {
                        $pushSearchValue($searchValues, $reponseId);
                        if (!is_numeric($reponseId)) {
                            continue;
                        }

                        $lastTransmission = $this->transmissionReponseRepository->findLatestByReponseId((int) $reponseId);
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

                    $piecesJointes = $this->pieceJointeRepository->findBy([
                        'idParent' => $reponse->getId(),
                        'typeParent' => 'Reponse',
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
            };

            $allMyResponses = array_values(array_filter($queryBuilderMyResponses->getQuery()->getResult(), $matchesPostFilters));
            $allServiceResponses = array_values(array_filter($queryBuilderServiceResponses->getQuery()->getResult(), $matchesPostFilters));

            $totalMyResponses = count($allMyResponses);
            $totalServiceResponses = count($allServiceResponses);

            if ($limit > 0) {
                $offset = ($page - 1) * $limit;
                $myResponses = array_slice($allMyResponses, $offset, $limit);
                $serviceResponses = array_slice($allServiceResponses, $offset, $limit);
            } else {
                $myResponses = $allMyResponses;
                $serviceResponses = $allServiceResponses;
            }
        } else {
            $totalMyResponses = (clone $queryBuilderMyResponses)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
            $totalServiceResponses = (clone $queryBuilderServiceResponses)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

            if ($limit > 0) {
                $queryBuilderMyResponses->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
                $queryBuilderServiceResponses->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
            }

            $myResponses = $queryBuilderMyResponses->getQuery()->getResult();
            $serviceResponses = $queryBuilderServiceResponses->getQuery()->getResult();
        }

        // Ã°Å¸â€â€ž Fonction de formatage des donnÃƒÂ©es
        $formatReponse = function($r) {
            // Ã°Å¸â€œÅ½ RÃƒÂ©cupÃƒÂ©rer les piÃƒÂ¨ces jointes
            $piecesJointes = $this->pieceJointeRepository->findBy([
                'idParent' => $r->getId(),
                'typeParent' => 'Reponse',
                'isDelete' => false
            ]);

            // Ã°Å¸â€œâ€¹ RÃƒÂ©cupÃƒÂ©rer les types de courrier depuis typesCourrierIds
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

            // Ã°Å¸â€œÂ§ RÃƒÂ©cupÃƒÂ©rer tous les courriers associÃƒÂ©s
            $courriers = [];
            foreach ($r->getCourriers() as $courrier) {
                $courriers[] = [
                    'id' => $courrier->getId(),
                    'numero' => $courrier->getNumero(),
                    'reference' => $courrier->getReference(),
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

            // Recuperer le statut et le service destinataire de la derniere transmission reponse liee
            $reponsesLiees = [];
            $linkedIds = is_array($r->getIdReponses()) ? $r->getIdReponses() : [];
            if (empty($linkedIds)) {
                $linkedIds = [$r->getId()];
            }
            foreach ($linkedIds as $reponseId) {
                if (!is_numeric($reponseId)) {
                    continue;
                }
                $lastTransmission = $this->transmissionReponseRepository->findLatestByReponseId((int) $reponseId);
                $reponsesLiees[] = [
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
                'id' => $r->getId(),
                'courriers' => $courriers,
                'typesCourrier' => $typesCourrier,
                'idTransmission' => $r->getIdTransmission(),
                'idReponses' => $r->getIdReponses(),
                'idCourrierInternes' => $r->getIdCourrierInternes(),
                'reponsesLiees' => $reponsesLiees,
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
                ] : null,
                'dateReponse' => $r->getDateReponse()?->format('Y-m-d'),
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

        // Formater les deux blocs
        $myResponsesData = array_map($formatReponse, $myResponses);
        $serviceResponsesData = array_map($formatReponse, $serviceResponses);

        // Logger la consultation de la liste avec toutes les donnÃƒÂ©es
        $this->actionLogger->logView(
            'Reponse',
            null,
            'Consultation de la liste des réponses (par blocs)',
            [
                'totalMesReponses' => (int)$totalMyResponses,
                'totalReponsesService' => (int)$totalServiceResponses,
                'totalGlobal' => (int)($totalMyResponses + $totalServiceResponses),
                'page' => $filters['page'],
                'limit' => $filters['limit'],
                'search' => $filters['search'] ?: null,
                'filters' => $filters,
                'userServiceId' => $userService?->getId(),
            ]
        );

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'mesReponses' => [
                'description' => 'Réponses créées par l\'utilisateur connecté (rédacteur)',
                'total' => (int)$totalMyResponses,
                'data' => $myResponsesData
            ],
            'reponsesRecuesParMonService' => [
                'description' => 'Réponses envoyées au service de l\'utilisateur connecté',
                'total' => (int)$totalServiceResponses,
                'data' => $serviceResponsesData
            ],
            'totalGlobal' => (int)($totalMyResponses + $totalServiceResponses),
        ], 200);
    }
}
