<?php

namespace App\Controller\Assets;

use App\Repository\AssetRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetResponseBuilder;
use App\Service\DefaultAssetReferencesService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Assets')]
final class ListAssetsController extends AbstractController
{
    // #[Route('', name: 'app_asset_list', methods: ['GET'])]
    #[Route('', name: 'app_asset_list', methods: ['GET'], priority: 10)]
    #[OA\Get(
        path: '/assets',
        summary: 'Lister les biens patrimoniaux',
        description: 'Liste paginée allégée des biens. Ne retourne pas les photos, pièces jointes ni informations fournisseur. Champs : référence, nom, projet, catégorie, type, structure, responsable, statut, valeur, dateAcquisition, état, localisation (dernière position connue), maintenanceEnCours (infos de la maintenance ouverte, non-null si statut = EN MAINTENANCE).'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 10), description: 'Nombre d\'éléments par page (anciennement limit)')]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Recherche sur nom, référence, numéro de série')]
    #[OA\Parameter(name: 'is_delete', in: 'query', schema: new OA\Schema(type: 'boolean', default: false))]
    #[OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'asset_type_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
   #[OA\Parameter(name: 'service_id', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Filtrer par service (ID unique ou plusieurs IDs séparés par des virgules, ex: 1,2,5)')]
    #[OA\Parameter(name: 'project_id', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Filtrer par projet (ID unique ou plusieurs IDs séparés par des virgules, ex: 1,3,7)')]
    #[OA\Parameter(name: 'exercice', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filtrer par exercice (année)')]
    #[OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Filtrer par statut du bien (ex: ACTIF, EN MAINTENANCE, SORTIS).')]
    #[OA\Parameter(name: 'securise', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filtrer les biens sécurisés (true) ou non sécurisés (false).')]
    #[OA\Parameter(name: 'received', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filtrer les biens recus (true) ou non recu (false).')]
    #[OA\Parameter(name: 'restitue', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filtrer les biens restitués (true) ou non restitués (false).')]
    #[OA\Parameter(name: 'restituable', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filtrer les biens restituables (true) ou non restituables (false).')]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Biens retournés avec succès.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 1, 'total_pages' => 1],
                    'data' => [
                        [
                            'id' => 1,
                            'reference' => 'PAT-2026-00001',
                            'nom' => 'Ordinateur Portable HP ProBook 450 G10',
                            'projet' => ['id' => 3, 'nom' => 'Projet de Modernisation du Système d\'Information'],
                            'categorie' => ['id' => 2, 'nom' => 'Matériel Informatique'],
                            'typeBien' => ['id' => 5, 'nom' => 'Ordinateur Portable'],
                            'structure' => ['id' => 16, 'nom' => 'Direction des Systèmes d\'Information'],
                            'sourceFinancement' => 'Budget Propre',
                            'responsable' => [
                                'id' => 8,
                                'nom' => 'NGONO',
                                'prenom' => 'Jean Paul',
                                'matricule' => 'JP001',
                            
                            ],
                            'statut' => 'ACTIF',
                            'valeur' => 850000,
                            'valeurInitiale' => 850000,
                            'dateAcquisition' => '2026-07-30',
                            'unite_mesure' => 'Unité',
                            'quantiteStock' => 100,
                            'isRestitue' => false,
                            'received' => 'false',
                            'etatBien' => ['id' => 1, 'nom' => 'Fonctionnel'],
                            "securise" => true,  // ✅True si le bien est sécurisé et False sinon
                            'location' => ['id' => 2, 'latitude' => 3.8500, 'longitude' => 11.5050, 'geometry_type' => 'Point', 'geometry' => null],
                            'maintenanceEnCours' => null,
                            'doitEtreRestitue' => false,
                        ],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null])
    )]
    public function __invoke(
        Request $request,
        AssetRepository $assetRepository,
        AssetResponseBuilder $responseBuilder,
        DefaultAssetReferencesService $defaultAssetReferences,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        // $limit = $request->query->getInt('limit', 10);
        $limit = $request->query->getInt('per_page', 0);
        if ($limit <= 0) {
            $limit = $request->query->getInt('limit', 10);
        }
        $limit = max(1, $limit);
        // $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $isDelete = filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN);
        $requestedCategoryId = $request->query->has('category_id') ? $request->query->getInt('category_id') : null;
        $assetTypeId = $request->query->has('asset_type_id') ? $request->query->getInt('asset_type_id') : null;
        $serviceId = $request->query->get('service_id');
        $serviceIds = null;
        if (null !== $serviceId && '' !== trim($serviceId)) {
            // Parser les IDs multiples séparés par des virgules
            $serviceIds = array_map('intval', array_map('trim', explode(',', $serviceId)));
        }
        $projectId = $request->query->get('project_id');
        $projectIds = null;
        if (null !== $projectId && '' !== trim($projectId)) {
            // Parser les IDs multiples séparés par des virgules
            $projectIds = array_map('intval', array_map('trim', explode(',', $projectId)));
        }
        $exercice = $request->query->has('exercice') ? $request->query->getInt('exercice') : null;
        $statut = $request->query->get('statut');
        $securise = $request->query->get('securise');
        $received = $request->query->get('received');
        $restitue = $request->query->get('restitue');
        $restituable = $request->query->get('restituable');
        $restituableBool = null;
        if (null !== $restituable && '' !== trim($restituable)) {
            $restituableBool = filter_var($restituable, FILTER_VALIDATE_BOOLEAN);
        }

        // Un category_id peut pointer vers une catégorie soft-deletée : on la résout
        // toujours vers une catégorie active (ou la catégorie par défaut) avant de filtrer,
        // pour ne jamais traiter une catégorie supprimée comme active (cf. resolveActiveCategoryOrDefault).
        $categoryResolution = null;
        $categoryId = null;
        if (null !== $requestedCategoryId) {
            $resolvedCategory = $defaultAssetReferences->resolveActiveCategoryOrDefault($requestedCategoryId);
            $categoryId = $resolvedCategory->getId();
            $categoryResolution = $defaultAssetReferences->describeCategoryResolution($requestedCategoryId, $resolvedCategory);
        }

        // Filtrage par utilisateur connecté
        $user = $this->getUser();
        $userId = null;
        if ($user && method_exists($user, 'getId')) {
            // Vérifier si l'utilisateur a un rôle administrateur
            $isAdmin = false;
            $userRoles = $user->getAssignedRoles();
            $adminRoleNames = ['Administrateur', 'Administrateur patrimonial', 'Administrateur système'];

            foreach ($userRoles as $role) {
                if (in_array($role->getNom(), $adminRoleNames, true)) {
                    $isAdmin = true;
                    break;
                }
            }

            // Si pas administrateur, filtrer par l'utilisateur connecté (détenteur actuel)
            if (!$isAdmin) {
                $userId = $user->getId();
            }
        }

        $items = $assetRepository->findPaginated($page, $limit, $isDelete, $search, $categoryId, $assetTypeId, $serviceIds, $projectIds, $exercice, $statut, $userId, $securise, $received, $restitue, $restituableBool);
        $total = $assetRepository->countAll($isDelete, $search, $categoryId, $assetTypeId, $serviceIds, $projectIds, $exercice, $statut, $userId, $securise, $received, $restitue, $restituableBool);

        $data = array_map(static fn ($asset) => $responseBuilder->buildListItem($asset), $items);

        $meta = [
            'current_page' => $page,
            'limit' => $limit,
            'per_page' => $limit,
            'total_items' => $total,
            'total_pages' => (int) ceil($total / max(1, $limit)),
        ];
        if (null !== $categoryResolution) {
            $meta['category_filter'] = $categoryResolution;
        }

        return $apiResponse->success([
            'meta' => $meta,
            'data' => $data,
        ], Response::HTTP_OK, 'Biens retournés avec succès.');
    }
}
