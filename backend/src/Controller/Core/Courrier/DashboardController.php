<?php

namespace App\Controller\Core\Courrier;

use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\CourrierDepartRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\ReponseRepository;
use App\Repository\Core\ServiceRepository;
use App\Repository\Core\UserRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Repository\Core\CategorieCorrespondantRepository;
use App\Repository\Core\TypeCourrierRepository;
use App\Repository\Core\RoleRepository;
use App\Repository\Core\PermissionRepository;
use App\Repository\Core\RolePermissionRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: "Dashboard")]
class DashboardController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private CourrierDepartRepository $courrierDepartRepository,
        private TransmissionRepository $transmissionRepository,
        private ReponseRepository $reponseRepository,
        private ServiceRepository $serviceRepository,
        private UserRepository $userRepository,
        private CorrespondantRepository $correspondantRepository,
        private CategorieCorrespondantRepository $categorieCorrespondantRepository,
        private TypeCourrierRepository $typeCourrierRepository,
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository,
        private RolePermissionRepository $rolePermissionRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {}

    #[Route('/core/courrier/dashboard', name: 'app_core_courrier_dashboard', methods: ['GET'])]
    #[OA\Get(
        path: '/core/courrier/dashboard',
        summary: 'Statistiques du dashboard',
        description: 'Retourne toutes les statistiques globales et graphiques pour le dashboard avec possibilité de filtrage',
        tags: ['Dashboard'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'annee', in: 'query', description: 'Filtrer par année (ex: 2025)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'mois', in: 'query', description: 'Filtrer par mois (1-12)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'dateDebut', in: 'query', description: 'Date de début (Y-m-d)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateFin', in: 'query', description: 'Date de fin (Y-m-d)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'priorite', in: 'query', description: 'Filtrer par priorité (Urgent, Haute, Normal, Basse)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'statut', in: 'query', description: 'Filtrer par statut (En cours, Traité)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'classeCourrier', in: 'query', description: 'Filtrer par classe de courrier', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'idServiceTraitant', in: 'query', description: 'Filtrer par ID du service traitant', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'idTypeCourrier', in: 'query', description: 'Filtrer par ID du type de courrier', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'isConfidentiel', in: 'query', description: 'Filtrer par confidentialité (true/false)', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'isGeled', in: 'query', description: 'Filtrer par courriers gelés (true/false)', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Statistiques récupérées avec succès.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.'),
            new OA\Response(response: 500, description: 'Erreur serveur.')
        ]
    )]
    public function getDashboard(Request $request): Response
    {
        try {
            $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetDashboardStatistics');

            // ðŸ” RÃ©cupÃ©ration et validation des filtres
            $filters = $this->extractFilters($request);

            $statistics = [
                // ðŸ“Š Filtres appliquÃ©s
                'filtres' => $filters,
                
                // ðŸ“Š STATISTIQUES GLOBALES
                'global' => $this->getGlobalStatistics($filters),
                
                // ðŸ“‹ Courriers groupÃ©s par service traitant avec dÃ©tails
                'courriersByServiceDetails' => $this->getCourriersByServiceTraitantWithDetails($filters),
                
                // ðŸ“‹ Courriers groupÃ©s par type de courrier avec dÃ©tails
                'courriersByTypeCourrierDetails' => $this->getCourriersByTypeCourrierWithDetails($filters),
                
                // ðŸ”’ Courriers gelÃ©s avec dÃ©tails
                'courriersGelesDetails' => $this->getCourriersGelesWithDetails($filters),
            ];

            return $this->json([
                'code' => 200,
                'message' => 'Statistiques récupérées avec succès',
                'data' => $statistics
            ], 200);

        } catch (\Exception $e) {
            $this->logger->error('Erreur dashboard: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
                'trace' => $_ENV['APP_ENV'] === 'dev' ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * ðŸ” Extraction et validation des filtres depuis la requÃªte
     */
    private function extractFilters(Request $request): array
    {
        $filters = [];

        // Filtrage par annÃ©e
        if ($annee = $request->query->get('annee')) {
            $filters['annee'] = (int) $annee;
        }

        // Filtrage par mois
        if ($mois = $request->query->get('mois')) {
            $filters['mois'] = (int) $mois;
        }

        // Filtrage par pÃ©riode
        if ($dateDebut = $request->query->get('dateDebut')) {
            try {
                $filters['dateDebut'] = new \DateTime($dateDebut);
            } catch (\Exception $e) {
                $this->logger->warning("Date de début invalide: $dateDebut");
            }
        }

        if ($dateFin = $request->query->get('dateFin')) {
            try {
                $filters['dateFin'] = new \DateTime($dateFin);
            } catch (\Exception $e) {
                $this->logger->warning("Date de fin invalide: $dateFin");
            }
        }

        // Filtrage par prioritÃ©
        if ($priorite = $request->query->get('priorite')) {
            $filters['priorite'] = $priorite;
        }

        // Filtrage par statut
        if ($statut = $request->query->get('statut')) {
            $filters['statut'] = $statut;
        }

        // Filtrage par classe de courrier
        if ($classeCourrier = $request->query->get('classeCourrier')) {
            $filters['classeCourrier'] = $classeCourrier;
        }

        // Filtrage par service traitant
        if ($idServiceTraitant = $request->query->get('idServiceTraitant')) {
            $filters['idServiceTraitant'] = (int) $idServiceTraitant;
        }

        // Filtrage par type de courrier
        if ($idTypeCourrier = $request->query->get('idTypeCourrier')) {
            $filters['idTypeCourrier'] = (int) $idTypeCourrier;
        }

        // Filtrage par confidentialitÃ©
        if ($request->query->has('isConfidentiel')) {
            $filters['isConfidentiel'] = filter_var($request->query->get('isConfidentiel'), FILTER_VALIDATE_BOOLEAN);
        }

        // Filtrage par courriers gelÃ©s
        if ($request->query->has('isGeled')) {
            $filters['isGeled'] = filter_var($request->query->get('isGeled'), FILTER_VALIDATE_BOOLEAN);
        }

        return $filters;
    }

    /**
     * ðŸ”§ Application des filtres au QueryBuilder
     * @param string $dateField Le champ de date Ã  filtrer (par dÃ©faut 'createdAt')
     */
    private function applyFilters($qb, array $filters, string $alias = 'c', string $dateField = 'createdAt'): void
    {
        // Filtre par annÃ©e - converti en plage de dates
        if (isset($filters['annee'])) {
            $dateDebut = new \DateTime("{$filters['annee']}-01-01 00:00:00");
            $dateFin = new \DateTime("{$filters['annee']}-12-31 23:59:59");
            
            $qb->andWhere("{$alias}.{$dateField} >= :anneeDebut")
                ->andWhere("{$alias}.{$dateField} <= :anneeFin")
                ->setParameter('anneeDebut', $dateDebut)
                ->setParameter('anneeFin', $dateFin);
        }

        // Filtre par mois - converti en plage de dates
        if (isset($filters['mois']) && isset($filters['annee'])) {
            $annee = $filters['annee'];
            $mois = str_pad($filters['mois'], 2, '0', STR_PAD_LEFT);
            
            $dateDebut = new \DateTime("{$annee}-{$mois}-01 00:00:00");
            $dateFin = (clone $dateDebut)->modify('last day of this month')->setTime(23, 59, 59);
            
            $qb->andWhere("{$alias}.{$dateField} >= :moisDebut")
                ->andWhere("{$alias}.{$dateField} <= :moisFin")
                ->setParameter('moisDebut', $dateDebut)
                ->setParameter('moisFin', $dateFin);
        } elseif (isset($filters['mois']) && !isset($filters['annee'])) {
            // Si mois sans annÃ©e, on prend l'annÃ©e courante
            $annee = date('Y');
            $mois = str_pad($filters['mois'], 2, '0', STR_PAD_LEFT);
            
            $dateDebut = new \DateTime("{$annee}-{$mois}-01 00:00:00");
            $dateFin = (clone $dateDebut)->modify('last day of this month')->setTime(23, 59, 59);
            
            $qb->andWhere("{$alias}.{$dateField} >= :moisDebut")
                ->andWhere("{$alias}.{$dateField} <= :moisFin")
                ->setParameter('moisDebut', $dateDebut)
                ->setParameter('moisFin', $dateFin);
        }

        // Filtre par pÃ©riode personnalisÃ©e (prioritaire sur annÃ©e/mois si fourni)
        if (isset($filters['dateDebut']) && !isset($filters['annee']) && !isset($filters['mois'])) {
            $dateDebut = clone $filters['dateDebut'];
            $dateDebut->setTime(0, 0, 0);
            $qb->andWhere("{$alias}.{$dateField} >= :dateDebut")
                ->setParameter('dateDebut', $dateDebut);
        }

        if (isset($filters['dateFin']) && !isset($filters['annee']) && !isset($filters['mois'])) {
            $dateFin = clone $filters['dateFin'];
            $dateFin->setTime(23, 59, 59);
            $qb->andWhere("{$alias}.{$dateField} <= :dateFin")
                ->setParameter('dateFin', $dateFin);
        }

        // Filtre par prioritÃ© (seulement pour Courrier)
        if (isset($filters['priorite']) && $alias === 'c') {
            $qb->andWhere("{$alias}.priorite = :priorite")
                ->setParameter('priorite', $filters['priorite']);
        }

        // Filtre par statut (pour Courrier et Transmission)
        if (isset($filters['statut'])) {
            $qb->andWhere("{$alias}.statut = :statut")
                ->setParameter('statut', $filters['statut']);
        }

        // Filtre par classe de courrier (seulement pour Courrier)
        if (isset($filters['classeCourrier']) && $alias === 'c') {
            $qb->andWhere("{$alias}.classeCourrier = :classeCourrier")
                ->setParameter('classeCourrier', $filters['classeCourrier']);
        }

        // Filtre par service traitant (seulement pour Courrier)
        if (isset($filters['idServiceTraitant']) && $alias === 'c') {
            $qb->andWhere("{$alias}.idServiceTraitant = :idServiceTraitant")
                ->setParameter('idServiceTraitant', $filters['idServiceTraitant']);
        }

        // Filtre par type de courrier (seulement pour Courrier)
        if (isset($filters['idTypeCourrier']) && $alias === 'c') {
            $qb->andWhere("{$alias}.typeCourrier = :idTypeCourrier")
                ->setParameter('idTypeCourrier', $filters['idTypeCourrier']);
        }

        // Filtre par confidentialitÃ© (seulement pour Courrier)
        if (isset($filters['isConfidentiel']) && $alias === 'c') {
            $qb->andWhere("{$alias}.isConfidentiel = :isConfidentiel")
                ->setParameter('isConfidentiel', $filters['isConfidentiel']);
        }

        // Filtre par courriers gelÃ©s (seulement pour Courrier)
        if (isset($filters['isGeled']) && $alias === 'c') {
            $qb->andWhere("{$alias}.isGeled = :isGeled")
                ->setParameter('isGeled', $filters['isGeled']);
        }
    }

    /**
     * ðŸ“Š Statistiques globales avec filtres
     */
    private function getGlobalStatistics(array $filters = []): array
    {
        try {
            // CrÃ©ation du QueryBuilder de base
            $baseQb = $this->em->createQueryBuilder()
                ->from('App\Entity\Cour\Courrier', 'c')
                ->where('c.isDelete = false');

            // COURRIERS ARRIVÃ‰S
            $qbTotal = clone $baseQb;
            $qbTotal->select('COUNT(c.id)');
            $this->applyFilters($qbTotal, $filters, 'c', 'createdAt');
            $totalCourriers = $qbTotal->getQuery()->getSingleScalarResult();

            $qbEnCours = clone $baseQb;
            $qbEnCours->select('COUNT(c.id)')->andWhere('c.statut = :statut')->setParameter('statut', 'En cours');
            $this->applyFilters($qbEnCours, $filters, 'c', 'createdAt');
            $courriersEnCours = $qbEnCours->getQuery()->getSingleScalarResult();

            $qbTraites = clone $baseQb;
            $qbTraites->select('COUNT(c.id)')->andWhere('c.statut = :statut')->setParameter('statut', 'Traité');
            $this->applyFilters($qbTraites, $filters, 'c', 'createdAt');
            $courriersTraites = $qbTraites->getQuery()->getSingleScalarResult();

            $qbUrgents = clone $baseQb;
            $qbUrgents->select('COUNT(c.id)')->andWhere('c.priorite = :priorite')->setParameter('priorite', 'Urgent');
            $this->applyFilters($qbUrgents, $filters, 'c', 'createdAt');
            $courriersUrgents = $qbUrgents->getQuery()->getSingleScalarResult();

            $qbConfidentiels = clone $baseQb;
            $qbConfidentiels->select('COUNT(c.id)')->andWhere('c.isConfidentiel = true');
            $this->applyFilters($qbConfidentiels, $filters, 'c', 'createdAt');
            $courriersConfidentiels = $qbConfidentiels->getQuery()->getSingleScalarResult();

            $qbGeles = clone $baseQb;
            $qbGeles->select('COUNT(c.id)')->andWhere('c.isGeled = true');
            $this->applyFilters($qbGeles, $filters, 'c', 'createdAt');
            $courriersGeles = $qbGeles->getQuery()->getSingleScalarResult();

            // COURRIERS PAR PRIORITÃ‰
            $qbHaute = clone $baseQb;
            $qbHaute->select('COUNT(c.id)')->andWhere('c.priorite = :priorite')->setParameter('priorite', 'Haute');
            $this->applyFilters($qbHaute, $filters, 'c', 'createdAt');
            $courriersPrioriteHaute = $qbHaute->getQuery()->getSingleScalarResult();

            $qbBasse = clone $baseQb;
            $qbBasse->select('COUNT(c.id)')->andWhere('c.priorite = :priorite')->setParameter('priorite', 'Basse');
            $this->applyFilters($qbBasse, $filters, 'c', 'createdAt');
            $courriersPrioriteBasse = $qbBasse->getQuery()->getSingleScalarResult();

            $qbNormal = clone $baseQb;
            $qbNormal->select('COUNT(c.id)')->andWhere('c.priorite = :priorite')->setParameter('priorite', 'Normal');
            $this->applyFilters($qbNormal, $filters, 'c', 'createdAt');
            $courriersPrioriteNormal = $qbNormal->getQuery()->getSingleScalarResult();

            // âœ… COURRIERS DÃ‰PART - avec filtres
            $qbCourrierDepart = $this->em->createQueryBuilder()
                ->select('COUNT(cd.id)')
                ->from('App\Entity\Cour\CourrierDepart', 'cd')
                ->where('cd.isDelete = false');
            $this->applyFilters($qbCourrierDepart, $filters, 'cd', 'createdAt');
            $totalCourrierDepart = $qbCourrierDepart->getQuery()->getSingleScalarResult();

            // âœ… TRANSMISSIONS - avec filtres
            $qbTransmissions = $this->em->createQueryBuilder()
                ->select('COUNT(t.id)')
                ->from('App\Entity\Cour\Transmission', 't')
                ->where('t.isDelete = false');
            $this->applyFilters($qbTransmissions, $filters, 't', 'createdAt');
            $totalTransmissions = $qbTransmissions->getQuery()->getSingleScalarResult();

            $qbTransmissionsEnAttente = $this->em->createQueryBuilder()
                ->select('COUNT(t.id)')
                ->from('App\Entity\Cour\Transmission', 't')
                ->where('t.isDelete = false')
                ->andWhere('t.statut = :statut')
                ->setParameter('statut', 'En attente');
            $this->applyFilters($qbTransmissionsEnAttente, $filters, 't', 'createdAt');
            $transmissionsEnAttente = $qbTransmissionsEnAttente->getQuery()->getSingleScalarResult();

            $qbTransmissionsTraitees = $this->em->createQueryBuilder()
                ->select('COUNT(t.id)')
                ->from('App\Entity\Cour\Transmission', 't')
                ->where('t.isDelete = false')
                ->andWhere('t.statut = :statut')
                ->setParameter('statut', 'Traité');
            $this->applyFilters($qbTransmissionsTraitees, $filters, 't', 'createdAt');
            $transmissionsTraitees = $qbTransmissionsTraitees->getQuery()->getSingleScalarResult();

            // âœ… RÃ‰PONSES - avec filtres
            $qbReponses = $this->em->createQueryBuilder()
                ->select('COUNT(r.id)')
                ->from('App\Entity\Cour\Reponse', 'r')
                ->where('r.isDelete = false');
            $this->applyFilters($qbReponses, $filters, 'r', 'createdAt');
            $totalReponses = $qbReponses->getQuery()->getSingleScalarResult();

            // âœ… SERVICES - avec filtres
            $qbServices = $this->em->createQueryBuilder()
                ->select('COUNT(s.id)')
                ->from('App\Entity\Core\Service', 's')
                ->where('s.isDelete = false');
            $this->applyFilters($qbServices, $filters, 's', 'createdAt');
            $totalServices = $qbServices->getQuery()->getSingleScalarResult();

            $qbServicesActifs = $this->em->createQueryBuilder()
                ->select('COUNT(s.id)')
                ->from('App\Entity\Core\Service', 's')
                ->where('s.isDelete = false')
                ->andWhere('s.isActive = true');
            $this->applyFilters($qbServicesActifs, $filters, 's', 'createdAt');
            $servicesActifs = $qbServicesActifs->getQuery()->getSingleScalarResult();

            // âœ… UTILISATEURS - avec filtres
            $qbUtilisateurs = $this->em->createQueryBuilder()
                ->select('COUNT(u.id)')
                ->from('App\Entity\Core\User', 'u')
                ->where('u.isDelete = false');
            $this->applyFilters($qbUtilisateurs, $filters, 'u', 'createdAt');
            $totalUtilisateurs = $qbUtilisateurs->getQuery()->getSingleScalarResult();

            $qbUtilisateursActifs = $this->em->createQueryBuilder()
                ->select('COUNT(u.id)')
                ->from('App\Entity\Core\User', 'u')
                ->where('u.isDelete = false')
                ->andWhere('u.isActive = true');
            $this->applyFilters($qbUtilisateursActifs, $filters, 'u', 'createdAt');
            $utilisateursActifs = $qbUtilisateursActifs->getQuery()->getSingleScalarResult();

            // âœ… CORRESPONDANTS - avec filtres
            $qbCorrespondants = $this->em->createQueryBuilder()
                ->select('COUNT(cor.id)')
                ->from('App\Entity\Core\Correspondant', 'cor')
                ->where('cor.isDelete = false');
            $this->applyFilters($qbCorrespondants, $filters, 'cor', 'createdAt');
            $totalCorrespondants = $qbCorrespondants->getQuery()->getSingleScalarResult();

            // âœ… CATÃ‰GORIES CORRESPONDANT - avec filtres
            $qbCategories = $this->em->createQueryBuilder()
                ->select('COUNT(cat.id)')
                ->from('App\Entity\Core\CategorieCorrespondant', 'cat')
                ->where('cat.isDelete = false');
            $this->applyFilters($qbCategories, $filters, 'cat', 'createdAt');
            $totalCategoriesCorrespondant = $qbCategories->getQuery()->getSingleScalarResult();

            // âœ… TYPES COURRIER - avec filtres
            $qbTypesCourrier = $this->em->createQueryBuilder()
                ->select('COUNT(tc.id)')
                ->from('App\Entity\Core\TypeCourrier', 'tc')
                ->where('tc.isDelete = false');
            $this->applyFilters($qbTypesCourrier, $filters, 'tc', 'createdAt');
            $totalTypesCourrier = $qbTypesCourrier->getQuery()->getSingleScalarResult();

            // âœ… RÃ”LES - avec filtres
            $qbRoles = $this->em->createQueryBuilder()
                ->select('COUNT(ro.id)')
                ->from('App\Entity\Core\Role', 'ro')
                ->where('ro.isDelete = false');
            $this->applyFilters($qbRoles, $filters, 'ro', 'createdAt');
            $totalRoles = $qbRoles->getQuery()->getSingleScalarResult();

            // PERMISSIONS et ROLE_PERMISSIONS (pas de createdAt dans ces tables)
            $totalPermissions = $this->permissionRepository->count([]);
            $totalRolePermissions = $this->rolePermissionRepository->count([]);

            return [
                'courriers' => [
                    'total' => (int) $totalCourriers,
                    'enCours' => (int) $courriersEnCours,
                    'traites' => (int) $courriersTraites,
                    'urgents' => (int) $courriersUrgents,
                    'confidentiels' => (int) $courriersConfidentiels,
                    'geles' => (int) $courriersGeles,
                    'priorites' => [
                        'haute' => (int) $courriersPrioriteHaute,
                        'basse' => (int) $courriersPrioriteBasse,
                        'normal' => (int) $courriersPrioriteNormal,
                    ],
                ],
                'courriersDepart' => [
                    'total' => (int) $totalCourrierDepart,
                ],
                'transmissions' => [
                    'total' => (int) $totalTransmissions,
                    'enAttente' => (int) $transmissionsEnAttente,
                    'traitees' => (int) $transmissionsTraitees,
                ],
                'reponses' => [
                    'total' => (int) $totalReponses,
                ],
                'services' => [
                    'total' => (int) $totalServices,
                    'actifs' => (int) $servicesActifs,
                ],
                'utilisateurs' => [
                    'total' => (int) $totalUtilisateurs,
                    'actifs' => (int) $utilisateursActifs,
                ],
                'correspondants' => [
                    'total' => (int) $totalCorrespondants,
                ],
                'categories' => [
                    'total' => (int) $totalCategoriesCorrespondant,
                ],
                'typesCourrier' => [
                    'total' => (int) $totalTypesCourrier,
                ],
                'roles' => [
                    'total' => (int) $totalRoles,
                ],
                'permissions' => [
                    'total' => $totalPermissions,
                ],
                
            ];
        } catch (\Exception $e) {
            $this->logger->error('Erreur getGlobalStatistics: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ðŸ“Š Courriers groupÃ©s par service traitant avec dÃ©tails et filtres
     */
    private function getCourriersByServiceTraitantWithDetails(array $filters = []): array
    {
        try {
            $qb = $this->em->createQueryBuilder();
            $qb->select('s.id as serviceId', 's.nom as serviceNom', 's.sigle as serviceSigle', 
                        's.emailService as serviceEmail', 's.telephone as serviceTelephone',
                        'COUNT(c.id) as nombreCourriers')
                ->from('App\Entity\Cour\Courrier', 'c')
                ->leftJoin('c.idServiceTraitant', 's')
                ->where('c.isDelete = false')
                ->andWhere('s.nom IS NOT NULL')
                ->groupBy('s.id', 's.nom', 's.sigle', 's.emailService', 's.telephone')
                ->orderBy('nombreCourriers', 'DESC');

            $this->applyFilters($qb, $filters);

            $servicesWithCount = $qb->getQuery()->getResult();

            $result = [];
            foreach ($servicesWithCount as $serviceData) {
                $courriersQb = $this->em->createQueryBuilder();
                $courriersQb->select('c')
                    ->from('App\Entity\Cour\Courrier', 'c')
                    ->where('c.idServiceTraitant = :serviceId')
                    ->andWhere('c.isDelete = false')
                    ->orderBy('c.dateArrivee', 'DESC')
                    ->setParameter('serviceId', $serviceData['serviceId']);

                $this->applyFilters($courriersQb, $filters);

                $courriers = $courriersQb->getQuery()->getResult();

                $result[] = [
                    'service' => [
                        'id' => $serviceData['serviceId'],
                        'nom' => $serviceData['serviceNom'],
                        'sigle' => $serviceData['serviceSigle'],
                        'email' => $serviceData['serviceEmail'],
                        'telephone' => $serviceData['serviceTelephone'],
                    ],
                    'nombreCourriers' => (int) $serviceData['nombreCourriers'],
                    'courriers' => array_map(function($courrier) {
                        return [
                            'id' => $courrier->getId(),
                            'numero' => $courrier->getNumero(),
                            'objet' => $courrier->getObjet(),
                            'priorite' => $courrier->getPriorite(),
                            'statut' => $courrier->getStatut(),
                            'classeCourrier' => $courrier->getClasseCourrier(),
                            'isConfidentiel' => $courrier->isConfidentiel(),
                            'isGeled' => $courrier->isGeled(),
                        ];
                    }, $courriers)
                ];
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Erreur getCourriersByServiceTraitantWithDetails: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * ðŸ“Š Courriers groupÃ©s par type de courrier avec dÃ©tails et filtres
     */
    private function getCourriersByTypeCourrierWithDetails(array $filters = []): array
    {
        try {
            $qb = $this->em->createQueryBuilder();
            $qb->select('tc.id as typeId', 'tc.nom as typeNom', 'tc.type as typeCategorie',
                        'tc.classeCourrier as typeClasse',
                        'COUNT(c.id) as nombreCourriers')
                ->from('App\Entity\Cour\Courrier', 'c')
                ->leftJoin('c.typeCourrier', 'tc')
                ->where('c.isDelete = false')
                ->andWhere('tc.nom IS NOT NULL')
                ->groupBy('tc.id', 'tc.nom', 'tc.type', 'tc.classeCourrier')
                ->orderBy('nombreCourriers', 'DESC');

            $this->applyFilters($qb, $filters);

            $typesWithCount = $qb->getQuery()->getResult();

            $result = [];
            foreach ($typesWithCount as $typeData) {
                $courriersQb = $this->em->createQueryBuilder();
                $courriersQb->select('c')
                    ->from('App\Entity\Cour\Courrier', 'c')
                    ->where('c.typeCourrier = :typeId')
                    ->andWhere('c.isDelete = false')
                    ->orderBy('c.dateArrivee', 'DESC')
                    ->setParameter('typeId', $typeData['typeId']);

                $this->applyFilters($courriersQb, $filters);

                $courriers = $courriersQb->getQuery()->getResult();

                $result[] = [
                    'typeCourrier' => [
                        'id' => $typeData['typeId'],
                        'nom' => $typeData['typeNom'],
                        'type' => $typeData['typeCategorie'],
                        'classeCourrier' => $typeData['typeClasse'],
                    ],
                    'nombreCourriers' => (int) $typeData['nombreCourriers'],
                    'courriers' => array_map(function($courrier) {
                        return [
                            'id' => $courrier->getId(),
                            'numero' => $courrier->getNumero(),
                            'objet' => $courrier->getObjet(),
                            'priorite' => $courrier->getPriorite(),
                            'statut' => $courrier->getStatut(),
                            'classeCourrier' => $courrier->getClasseCourrier(),
                            'isConfidentiel' => $courrier->isConfidentiel(),
                            'isGeled' => $courrier->isGeled(),
                        ];
                    }, $courriers)
                ];
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Erreur getCourriersByTypeCourrierWithDetails: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * ðŸ”’ Courriers gelÃ©s avec dÃ©tails et filtres
     */
    private function getCourriersGelesWithDetails(array $filters = []): array
    {
        try {
            $qbCount = $this->em->createQueryBuilder();
            $qbCount->select('COUNT(c.id) as nombreCourriersGeles')
                ->from('App\Entity\Cour\Courrier', 'c')
                ->where('c.isGeled = true')
                ->andWhere('c.isDelete = false');

            $this->applyFilters($qbCount, $filters);

            $countResult = $qbCount->getQuery()->getSingleResult();

            $qbCourriers = $this->em->createQueryBuilder();
            $qbCourriers->select('c', 's', 'tc', 'prov', 'createur')
                ->from('App\Entity\Cour\Courrier', 'c')
                ->leftJoin('c.idServiceTraitant', 's')
                ->leftJoin('c.typeCourrier', 'tc')
                ->leftJoin('c.idProvenance', 'prov')
                ->leftJoin('c.idCreateur', 'createur')
                ->where('c.isGeled = true')
                ->andWhere('c.isDelete = false')
                ->orderBy('c.dateArrivee', 'DESC');

            $this->applyFilters($qbCourriers, $filters);

            $courriers = $qbCourriers->getQuery()->getResult();

            return [
                'nombreCourriersGeles' => (int) $countResult['nombreCourriersGeles'],
                'courriers' => array_map(function($courrier) {
                    return [
                        'id' => $courrier->getId(),
                        'numero' => $courrier->getNumero(),
                        'objet' => $courrier->getObjet(),
                        'priorite' => $courrier->getPriorite(),
                        'statut' => $courrier->getStatut(),
                        'classeCourrier' => $courrier->getClasseCourrier(),
                        'isConfidentiel' => $courrier->isConfidentiel(),
                        'isGeled' => $courrier->isGeled(),
                        'dateArrivee' => $courrier->getDateArrivee()?->format('Y-m-d H:i:s'),
                        'dateEnregistrement' => $courrier->getDateEnregistrement()?->format('Y-m-d H:i:s'),
                        'commentaire' => $courrier->getCommentaire(),
                        'nom' => $courrier->getNom(),
                        'civilite' => $courrier->getCivilite(),
                        'telephone' => $courrier->getTelephone(),
                        'email' => $courrier->getEmail(),
                        'adresse' => $courrier->getAdresse(),
                        'matricule' => $courrier->getMatricule(),
                        'serviceTraitant' => $courrier->getIdServiceTraitant() ? [
                            'id' => $courrier->getIdServiceTraitant()->getId(),
                            'nom' => $courrier->getIdServiceTraitant()->getNom(),
                            'sigle' => $courrier->getIdServiceTraitant()->getSigle(),
                        ] : null,
                        'typeCourrier' => $courrier->getTypeCourrier() ? [
                            'id' => $courrier->getTypeCourrier()->getId(),
                            'nom' => $courrier->getTypeCourrier()->getNom(),
                            'type' => $courrier->getTypeCourrier()->getType(),
                            'classeCourrier' => $courrier->getTypeCourrier()->getClasseCourrier(),
                        ] : null,
                        'provenance' => $courrier->getIdProvenance() ? [
                            'id' => $courrier->getIdProvenance()->getId(),
                            'nom' => $courrier->getIdProvenance()->getNom(),
                            'email' => $courrier->getIdProvenance()->getEmail(),
                            'telephone' => $courrier->getIdProvenance()->getTelephone(),
                        ] : null,
                        'createur' => $courrier->getIdCreateur() ? [
                            'id' => $courrier->getIdCreateur()->getId(),
                            'nom' => $courrier->getIdCreateur()->getFullName(),
                            'email' => $courrier->getIdCreateur()->getEmail(),
                        ] : null,
                    ];
                }, $courriers)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Erreur getCourriersGelesWithDetails: ' . $e->getMessage());
            return [
                'nombreCourriersGeles' => 0,
                'courriers' => []
            ];
        }
    }
}