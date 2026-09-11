<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Repository\AssetRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ordre_sortie', name: 'api_ordre_sortie', methods: ['GET'])]
#[OA\Tag(name: 'Comptables')]
final class OrdreSortieController extends AbstractController
{
    #[OA\Get(
        path: '/ordre_sortie',
        summary: 'Analyse des biens destinés à la sortie',
        description: ' Retourne les biens avec le statut SORTIES, avec filtres optionnels par service, catégorie.'
    )]
    #[OA\Parameter(
        name: 'serviceIds',
        description: 'IDs des services (séparés par des virgules, ex: 1,3,5)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'categorieId',
        description: 'ID de la catégorie',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    // #[OA\Parameter(
    //     name: 'search',
    //     description: 'Recherche sur la désignation',
    //     in: 'query',
    //     required: false,
    //     schema: new OA\Schema(type: 'string')
    // )]
    #[OA\Parameter(
        name: 'page',
        description: 'Numéro de page (défaut: 1)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Nombre d\'éléments par page (défaut: 10)',
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
                'message' => 'Biens destinés à la sortie récupérés avec succès.',
                'data' => [
                    'meta' => [
                        'current_page' => 1,
                        'limit' => 10,
                        'total_items' => 50,
                        'total_pages' => 5
                    ],
                    'data' => [
                        [
                            'id' => '1',
                            'folioGrandLivre' => '',
                            'numeroOrdre' => '001',
                            'designation' => 'Ordinateur HP',
                            "champs" => [
                                [
                                    "nom" => "Imputation Budgetaire",
                                    "inputs" => [
                                        [
                                            "valeur" => "CENTRE",
                                        ]
                                    ],
                                    "nom" => "Unité",
                                    "inputs" => [
                                        [
                                            "valeur" => "Kg",
                                        ]
                                    ],
                                    "nom" => "Quantité",
                                    "inputs" => [
                                        [
                                            "valeur" => "20",
                                        ]
                                    ],
                                    "nom" => "Partielles",
                                    "inputs" => [
                                        [
                                            "valeur" => "",
                                        ]
                                    ],
                                    "nom" => "Par classe de la nomenclature somaire",
                                    "inputs" => [
                                        [
                                            "valeur" => "",
                                        ]
                                    ],
                                    "nom" => "Numero de la piece justificat",
                                    "inputs" => [
                                        [
                                            "valeur" => "",
                                        ]
                                    ]
                                ]
                            ],
                            'prix' => '5000.00',
                        ]
                    ]
                ]
            ]
        )
    )]
    public function __invoke(
        Request $request,
        AssetRepository $assetRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, $request->query->getInt('limit', 10));
        
        $serviceIdsStr = $request->query->get('serviceIds');
        $categorieId = $request->query->getInt('categorieId');
        $search = $request->query->get('search');
        
        // Parsing des serviceIds
        $serviceIds = null;
        if (null !== $serviceIdsStr && '' !== trim($serviceIdsStr)) {
            $serviceIds = array_map('intval', array_map('trim', explode(',', $serviceIdsStr)));
            $serviceIds = array_filter($serviceIds, fn($id) => $id > 0);
        }
        
        // Requête avec filtres
        $queryBuilder = $assetRepository->createQueryBuilder('a')
            ->leftJoin('a.categories', 'cat')
            ->leftJoin('a.champs', 'ch')
            ->leftJoin('ch.inputs', 'i')
            ->addSelect('cat')
            ->addSelect('ch')
            ->addSelect('i')
            ->where('a.statut = :statut')
            ->andWhere('a.isDelete = false')
            ->setParameter('statut', 'SORTIS');
        
        // Filtre par service
        if (null !== $serviceIds && !empty($serviceIds)) {
            $queryBuilder->innerJoin('a.services', 's')
                ->andWhere('s.id IN (:serviceIds)')
                ->setParameter('serviceIds', $serviceIds);
        }
        
        // Filtre par catégorie
        if ($categorieId > 0) {
            $queryBuilder->innerJoin('a.categories', 'c')
                ->andWhere('c.id = :categorieId')
                ->setParameter('categorieId', $categorieId);
        }
        
        // Recherche
        if (null !== $search && '' !== trim($search)) {
            $queryBuilder->andWhere('LOWER(a.nom) LIKE :search')
                ->setParameter('search', '%' . strtolower(trim($search)) . '%');
        }
        
        // Pagination
        $total = count($queryBuilder->getQuery()->getResult());
        $totalPages = (int) ceil($total / max(1, $limit));
        $offset = ($page - 1) * $limit;
        
        $assets = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        
        // Formatage des données
        $data = array_map(function (Asset $asset) {
            $categories = $asset->getCategories();
            $numeroOrdre = null;
            
            if (!$categories->isEmpty()) {
                $firstCategory = $categories->first();
                if ($firstCategory) {
                    $numeroOrdre = $firstCategory->getOrdre();
                }
            }
            
            // Récupérer les champs liés au bien avec leurs inputs
            $champs = [];
            foreach ($asset->getChamps() as $champ) {
                $inputs = [];
                foreach ($champ->getInputs() as $input) {
                    if (!$input->isDelete()) {
                        $inputs[] = [
                            'valeur' => $input->getValeur()
                        ];
                    }
                }
                
                $champs[] = [
                    'nom' => $champ->getNom(),
                    // 'type' => $champ->getType(),
                    'inputs' => $inputs
                ];
            }
            
            return [
                'id' => $asset->getId(),
                'folioGrandLivre' => '',
                'numeroOrdre' => $numeroOrdre,
                'designation' => $asset->getNom(),
                'champs' => $champs,
                'prix' => $asset->getValeur()
            ];
        }, $assets);
        
        return $apiResponse->success(
            [
                'meta' => [
                    'current_page' => $page,
                    'limit' => $limit,
                    'total_items' => $total,
                    'total_pages' => $totalPages
                ],
                'data' => $data
            ],
            Response::HTTP_OK,
            'Biens destinés à la sortie récupérés avec succès.'
        );
    }
}
