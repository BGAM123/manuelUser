<?php

namespace App\Controller\Comptables;

use App\Entity\Asset;
use App\Entity\AssetExit;
use App\Entity\Category;
use App\Entity\Consumable;
use App\Entity\ConsumableTransfer;
use App\Repository\AssetExitRepository;
use App\Repository\AssetRepository;
use App\Repository\CategoryRepository;
use App\Repository\ConsumableRepository;
use App\Repository\ConsumableTransferRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/comptables/etat-appreciatif', name: 'api_etat_appreciatif', methods: ['GET'])]
#[OA\Tag(name: 'Comptables')]
final class EtatAppreciatifController extends AbstractController
{
    #[OA\Get(
        path: '/comptables/etat-appreciatif',
        summary: 'État appréciatif semestriel',
        description: 'Reconstitue le document papier "État appréciatif semestriel" avec les mouvements de biens/matières.'
    )]
    #[OA\Parameter(
        name: 'dateDebut',
        description: 'Date de début de période (YYYY-MM-DD)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date')
    )]
    #[OA\Parameter(
        name: 'dateFin',
        description: 'Date de fin de période (YYYY-MM-DD)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date')
    )]
    #[OA\Parameter(
        name: 'exercice',
        description: 'Année de l\'exercice (ex: 2026)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'categorieIds',
        description: 'IDs des catégories (séparés par des virgules, ex: 4,5,6)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'sens',
        description: 'Filtrer par sens de mouvement (ENTREE ou SORTIE)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['ENTREE', 'SORTIE'])
    )]
    #[OA\Parameter(
        name: 'type',
        description: 'Filtrer par type de bien (bien ou consommable)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['bien', 'consommable'])
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Numéro de page (défaut: 1)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Nombre d\'éléments par page (défaut: 100)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'État appréciatif généré avec succès.',
                'data' => [
                    'periode' => [
                        'dateDebut' => '2026-01-01',
                        'dateFin' => '2026-06-30'
                    ],
                    'lignes' => [
                        [
                            'pieceJustificative' => null,
                            'date' => '2026-01-05',
                            'designation' => 'Réception armement',
                            'sens' => 'ENTREE',
                            'ordre' => 4,
                            'categorie_nom' => 'Matériel de guerre',
                            'montant' => 500000
                        ],
                        [
                            'pieceJustificative' => null,
                            'date' => '2026-01-05',
                            'designation' => 'Réception armement',
                            'sens' => 'SORTIES',
                            'ordre' => 4,
                            'categorie_nom' => 'Matériel de guerre',
                            'montant' => 500000
                        ]
                    ],
                    'totaux' => [
                        ['ordre' => 4, 'categorie_nom' => 'Matériel de guerre', 'entrees' => 500000, 'sorties' => 0]
                    ],
                    'meta' => [
                        'current_page' => 1,
                        'limit' => 100,
                        'total_items' => 1,
                        'total_pages' => 1
                    ]
                ]
            ]
        )
    )]
    public function __invoke(
        Request $request,
        CategoryRepository $categoryRepository,
        AssetRepository $assetRepository,
        AssetExitRepository $assetExitRepository,
        ConsumableRepository $consumableRepository,
        ConsumableTransferRepository $consumableTransferRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, $request->query->getInt('limit', 100));
        
        $dateDebutStr = $request->query->get('dateDebut');
        $dateFinStr = $request->query->get('dateFin');
        $exercice = $request->query->getInt('exercice');
        $categorieIdsStr = $request->query->get('categorieIds');
        $sens = $request->query->get('sens');
        $type = $request->query->get('type');
        
        // Définition de la période
        if ($exercice > 0) {
            $dateDebut = new \DateTime("{$exercice}-01-01");
            $dateFin = new \DateTime("{$exercice}-12-31");
        } elseif ($dateDebutStr && $dateFinStr) {
            $dateDebut = new \DateTime($dateDebutStr);
            $dateFin = new \DateTime($dateFinStr);
        } else {
            // Par défaut, année courante
            $currentYear = (int) date('Y');
            $dateDebut = new \DateTime("{$currentYear}-01-01");
            $dateFin = new \DateTime("{$currentYear}-12-31");
        }
        
        // Parsing des catégorieIds
        $categorieIds = null;
        if (null !== $categorieIdsStr && '' !== trim($categorieIdsStr)) {
            $categorieIds = array_map('intval', array_map('trim', explode(',', $categorieIdsStr)));
            $categorieIds = array_filter($categorieIds, fn($id) => $id > 0);
        }
        
        // Récupérer toutes les catégories (filtrées si nécessaire)
        $categoriesQuery = $categoryRepository->createQueryBuilder('c')
            ->where('c.isDelete = false');
        
        if (null !== $categorieIds && !empty($categorieIds)) {
            $categoriesQuery->andWhere('c.id IN (:categorieIds)')
                ->setParameter('categorieIds', $categorieIds);
        }
        
        $categories = $categoriesQuery->orderBy('c.ordre', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Initialisation des totaux par catégorie
        $totaux = [];
        foreach ($categories as $category) {
            $totaux[$category->getId()] = [
                'id' =>$category->getId(),
                'ordre' => $category->getOrdre(),
                'categorie_nom' => $category->getNom(),
                'entrees' => 0,
                'sorties' => 0
            ];
        }
        
        // Récupération des mouvements
        $lignes = [];
        
        // Filtre par type (bien/consommable)
        $includeBiens = ($type === null || $type === 'bien');
        $includeConsommables = ($type === null || $type === 'consommable');
        
        // 1. Biens durables - Entrées (Asset créés)
        if ($includeBiens) {
            if (null !== $categorieIds && !empty($categorieIds)) {
                $assetsQuery = $assetRepository->createQueryBuilder('a')
                    ->innerJoin('a.categories', 'c')
                    ->where('a.isDelete = false')
                    ->andWhere('c.id IN (:categorieIds)')
                    ->andWhere('a.dateAcquisition >= :dateDebut')
                    ->andWhere('a.dateAcquisition <= :dateFin')
                    ->setParameter('categorieIds', $categorieIds)
                    ->setParameter('dateDebut', $dateDebut)
                    ->setParameter('dateFin', $dateFin);
            } else {
                $assetsQuery = $assetRepository->createQueryBuilder('a')
                    ->innerJoin('a.categories', 'c')
                    ->where('a.isDelete = false')
                    ->andWhere('a.dateAcquisition >= :dateDebut')
                    ->andWhere('a.dateAcquisition <= :dateFin')
                    ->setParameter('dateDebut', $dateDebut)
                    ->setParameter('dateFin', $dateFin);
            }
            
            $assets = $assetsQuery->getQuery()->getResult();
            
            foreach ($assets as $asset) {
                foreach ($asset->getCategories() as $category) {
                    if (!$category->getConsommable()) {
                        // Filtre par sens
                        if ($sens === null || $sens === 'ENTREE') {
                            $montant = (float) ($asset->getValeur() ?? 0);
                            $lignes[] = [
                                'id_categorie' =>$category->getId(),
                                'id_bien' =>$asset->getId(),
                                'pieceJustificative' => null,
                                'date' => $asset->getDateAcquisition()?->format('Y-m-d'),
                                'designation' => $asset->getNom() ?? 'Bien durable',
                                'sens' => 'ENTREE',
                                'ordre' => $category->getOrdre(),
                                'categorie_nom' => $category->getNom(),
                                'montant' => $montant
                            ];
                            
                            if (isset($totaux[$category->getId()])) {
                                $totaux[$category->getId()]['entrees'] += $montant;
                            }
                        }
                    }
                }
            }
        }
        
        // 2. Biens durables - Sorties (AssetExit)
        if ($includeBiens) {
            if (null !== $categorieIds && !empty($categorieIds)) {
                $exitsQuery = $assetExitRepository->createQueryBuilder('ae')
                    ->innerJoin('ae.asset', 'a')
                    ->innerJoin('a.categories', 'c')
                    ->where('ae.isDelete = false')
                    ->andWhere('c.id IN (:categorieIds)')
                    ->andWhere('ae.dateSortie >= :dateDebut')
                    ->andWhere('ae.dateSortie <= :dateFin')
                    ->setParameter('categorieIds', $categorieIds)
                    ->setParameter('dateDebut', $dateDebut)
                    ->setParameter('dateFin', $dateFin);
            } else {
                $exitsQuery = $assetExitRepository->createQueryBuilder('ae')
                    ->innerJoin('ae.asset', 'a')
                    ->innerJoin('a.categories', 'c')
                    ->where('ae.isDelete = false')
                    ->andWhere('ae.dateSortie >= :dateDebut')
                    ->andWhere('ae.dateSortie <= :dateFin')
                    ->setParameter('dateDebut', $dateDebut)
                    ->setParameter('dateFin', $dateFin);
            }
            
            $exits = $exitsQuery->getQuery()->getResult();
            
            foreach ($exits as $exit) {
                $asset = $exit->getAsset();
                foreach ($asset->getCategories() as $category) {
                    if (!$category->getConsommable()) {
                        // Filtre par sens
                        if ($sens === null || $sens === 'SORTIE') {
                            $montant = (float) ($asset->getValeurInitiale() ?? 0);
                            $lignes[] = [
                                'id_categorie' =>$category->getId(),
                                'id_bien' =>$asset->getId(),
                                'pieceJustificative' => $exit->getProtocoleReference(),
                                'date' => $exit->getDateSortie()?->format('Y-m-d'),
                                // 'designationg' => $exit->getMotifSortie() ?? 'Sortie bien durable',
                                'designation' => $asset->getNom() ?? 'Sortie bien durable',
                                'sens' => 'SORTIE',
                                'ordre' => $category->getOrdre(),
                                'categorie_nom' => $category->getNom(),
                                'montant' => $montant
                            ];
                            
                            if (isset($totaux[$category->getId()])) {
                                $totaux[$category->getId()]['sorties'] += $montant;
                            }
                        }
                    }
                }
            }
        }
        
        // 3. Consommables - Mouvements (ConsumableTransfer)
        if ($includeConsommables) {
            if (null !== $categorieIds && !empty($categorieIds)) {
                $transfersQuery = $consumableTransferRepository->createQueryBuilder('ct')
                    ->innerJoin('ct.consumable', 'co')
                    ->innerJoin('co.category', 'c')
                    ->where('ct.isDelete = false')
                    ->andWhere('c.id IN (:categorieIds)')
                    ->andWhere('c.consommable = true')
                    ->andWhere('ct.dateTransfert >= :dateDebut')
                    ->andWhere('ct.dateTransfert <= :dateFin')
                    ->setParameter('categorieIds', $categorieIds)
                    ->setParameter('dateDebut', $dateDebut)
                    ->setParameter('dateFin', $dateFin);
            } else {
                $transfersQuery = $consumableTransferRepository->createQueryBuilder('ct')
                    ->innerJoin('ct.consumable', 'co')
                    ->innerJoin('co.category', 'c')
                    ->where('ct.isDelete = false')
                    ->andWhere('c.consommable = true')
                    ->andWhere('ct.dateTransfert >= :dateDebut')
                    ->andWhere('ct.dateTransfert <= :dateFin')
                    ->setParameter('dateDebut', $dateDebut)
                    ->setParameter('dateFin', $dateFin);
            }
            
            $transfers = $transfersQuery->getQuery()->getResult();
            
            foreach ($transfers as $transfer) {
                $consumable = $transfer->getConsumable();
                $category = $consumable->getCategory();
                
                if ($category && $category->getConsommable()) {
                    $montant = (float) ($transfer->getQuantite() * ($consumable->getPrixInitial() ?? 0));
                    $sensMouvement = ($transfer->getType() === 'INITIAL' || $transfer->getType() === 'TRANSFERT_DIRECT') ? 'ENTREE' : 'SORTIE';
                    
                    // Filtre par sens
                    if ($sens === null || $sens === $sensMouvement) {
                        $lignes[] = [
                            'id_categorie' =>$category->getId(),
                            'id_comsomtible' =>$consumable->getId(),
                            'pieceJustificative' => null,
                            'date' => $transfer->getDateTransfert()?->format('Y-m-d'),
                            'designation' => $consumable->getNom(),
                            'sens' => $sensMouvement,
                            'ordre' => $category->getOrdre(),
                            'categorie_nom' => $category->getNom(),
                            'montant' => $montant
                        ];
                        
                        if (isset($totaux[$category->getId()])) {
                            if ($sensMouvement === 'ENTREE') {
                                $totaux[$category->getId()]['entrees'] += $montant;
                            } else {
                                $totaux[$category->getId()]['sorties'] += $montant;
                            }
                        }
                    }
                }
            }
        }
        
        // Trier les lignes par ordre de catégorie (ceux avec ordre en premier, puis par date)
        usort($lignes, function ($a, $b) {
            $ordreA = $a['ordre'] ?? PHP_INT_MAX;
            $ordreB = $b['ordre'] ?? PHP_INT_MAX;
            
            if ($ordreA !== $ordreB) {
                return $ordreA <=> $ordreB;
            }
            return strtotime($a['date']) <=> strtotime($b['date']);
        });
        
        // Pagination des lignes
        $totalLignes = count($lignes);
        $totalPages = (int) ceil($totalLignes / max(1, $limit));
        $offset = ($page - 1) * $limit;
        $lignesPaginees = array_slice($lignes, $offset, $limit);
        
        // Formater les totaux
        $totauxArray = array_values($totaux);
        
        return $apiResponse->success(
            [
                'periode' => [
                    'dateDebut' => $dateDebut->format('Y-m-d'),
                    'dateFin' => $dateFin->format('Y-m-d')
                ],
                'lignes' => $lignesPaginees,
                'totaux' => $totauxArray,
                'meta' => [
                    'current_page' => $page,
                    'limit' => $limit,
                    'total_items' => $totalLignes,
                    'total_pages' => $totalPages
                ]
            ],
            Response::HTTP_OK,
            'État appréciatif généré avec succès.'
        );
    }
}
