<?php

namespace App\Controller\Organigramme;

use App\Repository\CategoryRepository;
use App\Repository\AssetTypeRepository;
use App\Repository\AssetSubTypeRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/organigramme_categorie')]
#[OA\Tag(name: 'Categories')]
final class GetOrganigrammeController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly AssetTypeRepository $assetTypeRepository,
        private readonly AssetSubTypeRepository $assetSubTypeRepository
    ) {
    }

    #[Route('/hierarchie', name: 'app_organigramme_hierarchie', methods: ['GET'])]
    #[OA\Get(
        path: '/organigramme_categorie/hierarchie',
        summary: "Obtenir l'organigramme hiérarchique complet",
        description: 'Retourne une structure hiérarchique : Catégories → Types de biens → Sous-types de biens avec pagination et recherche.'
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 1),
        description: 'Numéro de la page'
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 10),
        description: "Nombre d'éléments par page"
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
        description: 'Recherche par nom de catégorie, type ou sous-type'
    )]
    #[OA\Parameter(
        name: 'include_inactive',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'Inclure les éléments inactifs'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Organigramme hiérarchique retourné'
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié'
    )]
    public function hierarchie(
        Request $request,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // Paramètres de pagination
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = min(max($limit, 1), 200);
        $search = $request->query->get('search');  // ✅ Peut être null
        $includeInactive = filter_var($request->query->get('include_inactive', false), FILTER_VALIDATE_BOOLEAN);

        // Récupérer les catégories paginées avec recherche
        $categories = $this->categoryRepository->findPaginatedCategories($page, $limit, false, $search);
        $total = $this->categoryRepository->countAllCategories(false, $search);

        // Construire l'arborescence
        $items = [];
        foreach ($categories as $category) {
            // Récupérer les types par catégorie avec recherche
            $types = $this->assetTypeRepository->findActiveByCategoryId(
                $category->getId(), 
                $includeInactive,
                $search
            );

            $typesData = [];
            foreach ($types as $type) {
                // ✅ Vérifier si le type correspond à la recherche
                $typeMatches = $this->matchesSearch($type, $search);
                
                // Récupérer les sous-types par type avec recherche
                $subTypes = $this->assetSubTypeRepository->findActiveByAssetTypeId(
                    $type->getId(), 
                    $includeInactive,
                    $search
                );

                $subTypesData = [];
                foreach ($subTypes as $subType) {
                    $subTypesData[] = [
                        'id' => $subType->getId(),
                        'nom' => $subType->getNom(),
                        'description' => $subType->getDescription(),
                    ];
                }

                // ✅ Ne garder le type que s'il correspond à la recherche OU s'il a des sous-types correspondants
                if ($search) {
                    if ($typeMatches || !empty($subTypesData)) {
                        $typesData[] = [
                            'id' => $type->getId(),
                            'nom' => $type->getNom(),
                            'description' => $type->getDescription(),
                            'dureeVie' => $type->getDureeVie(),
                            'taux' => $type->getTaux(),
                            'sousTypes' => $subTypesData,
                        ];
                    }
                } else {
                    $typesData[] = [
                        'id' => $type->getId(),
                        'nom' => $type->getNom(),
                        'description' => $type->getDescription(),
                        'dureeVie' => $type->getDureeVie(),
                        'taux' => $type->getTaux(),
                        'sousTypes' => $subTypesData,
                    ];
                }
            }

            
            
                $items[] = [
                    'id' => $category->getId(),
                    'nom' => $category->getNom(),
                    'description' => $category->getDescription(),
                    'types' => $typesData,
                ];
            
        }

        // Structure de réponse avec pagination
        $data = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / $limit),
                'search' => $search,
            ],
            'items' => $items,
        ];

        return $apiResponse->success(
            $data,
            Response::HTTP_OK,
            'Organigramme récupéré avec succès.'
        );
    }

    /**
     * ✅ Vérifie si un élément correspond à la recherche
     * @param object $entity L'entité à vérifier
     * @param string|null $search Le terme de recherche
     * @return bool
     */
    private function matchesSearch(object $entity, ?string $search): bool
    {
        // ✅ Si pas de recherche, tout correspond
        if (!$search || empty(trim($search))) {
            return true;
        }

        $searchLower = strtolower(trim($search));
        $nom = strtolower($entity->getNom() ?? '');
        $description = strtolower($entity->getDescription() ?? '');
        
        return strpos($nom, $searchLower) !== false || 
               strpos($description, $searchLower) !== false;
    }
}