<?php

namespace App\Controller\Core\Coffre;

use App\Repository\Core\CoffreRepository;
use App\Repository\Core\SalleRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Coffre")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private CoffreRepository $coffreRepository,
        private SalleRepository $salleRepository,
    ) {}

    #[Route('/core/coffre', name: 'app_core_coffre_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/coffre',
        summary: 'Lister les coffres',
        tags: ['Coffre'],
        description: "Récupère la liste de tous les coffres avec pagination et filtres. Possibilité de filtrer par salle, par disponibilité, etc.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Numéro de la page',
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: 'Nombre d\'éléments par page (max 100)',
                schema: new OA\Schema(type: 'integer', default: 10)
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Recherche par nom de coffre',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'id_salle',
                in: 'query',
                required: false,
                description: 'Filtrer par ID de salle',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'is_active',
                in: 'query',
                required: false,
                description: 'Filtrer par statut actif',
                schema: new OA\Schema(type: 'boolean')
            ),
            new OA\Parameter(
                name: 'is_delete',
                in: 'query',
                required: false,
                description: 'Inclure les coffres supprimés',
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
            new OA\Parameter(
                name: 'disponible',
                in: 'query',
                required: false,
                description: 'Afficher uniquement les coffres avec des places disponibles',
                schema: new OA\Schema(type: 'boolean')
            ),
            new OA\Parameter(
                name: 'plein',
                in: 'query',
                required: false,
                description: 'Afficher uniquement les coffres pleins',
                schema: new OA\Schema(type: 'boolean')
            ),
            new OA\Parameter(
                name: 'group_by_salle',
                in: 'query',
                required: false,
                description: 'Grouper les coffres par salle (activé par défaut)',
                schema: new OA\Schema(type: 'boolean', default: true)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des coffres récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 25),
                        new OA\Property(property: 'totalPages', type: 'integer', example: 3),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Coffre A1'),
                                    new OA\Property(property: 'nombrePlaceActuelle', type: 'integer', example: 5),
                                    new OA\Property(property: 'tailleMaximale', type: 'integer', example: 20),
                                    new OA\Property(property: 'idSalle', type: 'integer', example: 1),
                                    new OA\Property(property: 'placesDisponibles', type: 'integer', example: 15),
                                    new OA\Property(property: 'tauxRemplissage', type: 'number', format: 'float', example: 25.0),
                                    new OA\Property(property: 'isPlein', type: 'boolean', example: false),
                                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-12-02T10:30:00+00:00'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function collection(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCollectionCoffre');

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 10)));
        $search = $request->query->get('search');
        $idSalle = $request->query->get('id_salle');
        $isActive = $request->query->get('is_active');
        $isDelete = $request->query->getBoolean('is_delete', false);
        $disponible = $request->query->get('disponible');
        $plein = $request->query->get('plein');
        $groupBySalle = $request->query->getBoolean('group_by_salle', true); // Par dÃ©faut TRUE

        $queryBuilder = $this->coffreRepository->createQueryBuilder('c');

        // Filtre de suppression
        if (!$isDelete) {
            $queryBuilder->andWhere('c.isDelete = :isDelete')
                ->setParameter('isDelete', false);
        }

        // Filtre actif
        if ($isActive !== null) {
            $queryBuilder->andWhere('c.isActive = :isActive')
                ->setParameter('isActive', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        // Filtre par salle
        if ($idSalle !== null) {
            $queryBuilder->andWhere('c.idSalle = :idSalle')
                ->setParameter('idSalle', (int) $idSalle);
        }

        // Recherche par nom
        if ($search) {
            $queryBuilder->andWhere('c.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Filtre disponibilitÃ©
        if ($disponible !== null && filter_var($disponible, FILTER_VALIDATE_BOOLEAN)) {
            $queryBuilder->andWhere('c.nombrePlaceActuelle < c.tailleMaximale');
        }

        // Filtre plein
        if ($plein !== null && filter_var($plein, FILTER_VALIDATE_BOOLEAN)) {
            $queryBuilder->andWhere('c.nombrePlaceActuelle >= c.tailleMaximale');
        }

        // Comptage total
        $total = (int) (clone $queryBuilder)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Si groupement par salle demandÃ©
        if ($groupBySalle) {
            // RÃ©cupÃ©rer tous les coffres sans pagination pour le groupement
            $queryBuilder->orderBy('c.idSalle', 'ASC')
                ->addOrderBy('c.nom', 'ASC');
            
            $coffres = $queryBuilder->getQuery()->getResult();
            
            // Grouper les coffres par salle
            $coffresParSalle = [];
            $sallesCache = []; // Cache pour Ã©viter de requÃªter plusieurs fois la mÃªme salle
            
            foreach ($coffres as $coffre) {
                $idSalle = $coffre->getIdSalle();
                
                if (!isset($coffresParSalle[$idSalle])) {
                    // RÃ©cupÃ©rer les informations de la salle si pas en cache
                    if (!isset($sallesCache[$idSalle])) {
                        $salle = $this->salleRepository->find($idSalle);
                        $sallesCache[$idSalle] = $salle ? [
                            'id' => $salle->getId(),
                            'nom' => $salle->getNom(),
                            'isActive' => $salle->isActive()
                        ] : null;
                    }
                    
                    $coffresParSalle[$idSalle] = [
                        'salle' => $sallesCache[$idSalle] ?? [
                            'id' => $idSalle,
                            'nom' => 'Salle inconnue',
                            'isActive' => false
                        ],
                        'totalCoffres' => 0,
                        'totalPlaces' => 0,
                        'totalPlacesOccupees' => 0,
                        'totalPlacesDisponibles' => 0,
                        'coffres' => []
                    ];
                }
                
                // Calculs statistiques
                $coffresParSalle[$idSalle]['totalCoffres']++;
                $coffresParSalle[$idSalle]['totalPlaces'] += $coffre->getTailleMaximale();
                $coffresParSalle[$idSalle]['totalPlacesOccupees'] += $coffre->getNombrePlaceActuelle();
                $coffresParSalle[$idSalle]['totalPlacesDisponibles'] += $coffre->getPlacesDisponibles();
                
                $coffresParSalle[$idSalle]['coffres'][] = [
                    'id' => $coffre->getId(),
                    'nom' => $coffre->getNom(),
                    'nombrePlaceActuelle' => $coffre->getNombrePlaceActuelle(),
                    'tailleMaximale' => $coffre->getTailleMaximale(),
                    'placesDisponibles' => $coffre->getPlacesDisponibles(),
                    'tauxRemplissage' => $coffre->getTauxRemplissage(),
                    'isPlein' => $coffre->isPlein(),
                    'isActive' => $coffre->isActive(),
                    'createdAt' => $coffre->getCreatedAt()?->format('c'),
                ];
            }
            
            return $this->json([
                'total' => $total,
                'totalSalles' => count($coffresParSalle),
                'data' => array_values($coffresParSalle),
            ], 200);
        }

        // Pagination normale
        $queryBuilder->orderBy('c.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $coffres = $queryBuilder->getQuery()->getResult();

        $totalPages = ceil($total / $limit);

        $formattedData = array_map(function ($coffre) {
            return [
                'id' => $coffre->getId(),
                'nom' => $coffre->getNom(),
                'nombrePlaceActuelle' => $coffre->getNombrePlaceActuelle(),
                'tailleMaximale' => $coffre->getTailleMaximale(),
                'idSalle' => $coffre->getIdSalle(),
                'placesDisponibles' => $coffre->getPlacesDisponibles(),
                'tauxRemplissage' => $coffre->getTauxRemplissage(),
                'isPlein' => $coffre->isPlein(),
                'isActive' => $coffre->isActive(),
                'isDelete' => $coffre->isDelete(),
                'createdAt' => $coffre->getCreatedAt()?->format('c'),
                'updatedAt' => $coffre->getUpdatedAt()?->format('c'),
            ];
        }, $coffres);

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
            'data' => $formattedData,
        ], 200);
    }
}
