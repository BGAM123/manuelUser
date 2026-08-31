<?php

namespace App\Controller\Consumables;

use App\Repository\ConsumableRepository;
use App\Repository\ConsumableTransferRepository; // 🔥 NOUVEAU
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumables')]
#[OA\Tag(name: 'Consomptibles')]
final class ListConsumablesController extends AbstractController
{
    #[Route('', name: 'app_consumable_list', methods: ['GET'])]
    #[OA\Get(
        path: '/consumables',
        summary: 'Lister les consomptibles',
        description: 'Liste paginée des consomptibles.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Recherche sur nom, description')]
    #[OA\Parameter(
        name: 'service_id',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par service propriétaire. Ignoré si all_services=true et que l\'utilisateur y est autorisé.'
    )]
    #[OA\Parameter(
        name: 'all_services',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'Par défaut, seuls les consomptibles du service de l\'utilisateur connecté sont retournés. all_services=true lève cette restriction, réservé aux rôles administrateur.'
    )]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'),
        description: 'false (défaut) = consomptibles actifs uniquement. true = consomptibles supprimés (corbeille) uniquement. all = tous.'
    )]
    #[OA\Parameter(
        name: 'category_ids',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
        description: 'Filtrer par IDs de catégories (séparés par des virgules, ex: 1,2,3)'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Consomptibles retournés avec succès.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 1, 'total_pages' => 1],
                    'data' => [
                        [
                            'id' => 1,
                            'nom' => 'Papier A4',
                            'description' => 'Papier format A4 80g/m²',
                            'quantite' => '5000.00',
                            'stockActuel' => '4500.00', // 🔥 NOUVEAU CHAMP
                            'category' => ['id' => 2, 'nom' => 'Fournitures de bureau'],
                            'assetType' => null,
                            'assetSubType' => null,
                            'prixInitial' => '10.00',
                            'prixTotal' => '50000.00',
                            'service' => ['id' => 16, 'nom' => 'Direction des Systèmes d\'Information'], 
                            'createdAt' => '2026-08-16 10:00:00',
                            'updatedAt' => '2026-08-16 10:00:00',
                        ],
                    ],
                ],
            ]
        )
    )]
    public function __invoke(
        Request $request,
        ConsumableRepository $consumableRepository,
        ConsumableTransferRepository $transferRepository, // 🔥 NOUVEAU
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $serviceId = $request->query->has('service_id') ? $request->query->getInt('service_id') : null;
        $isDelete = $request->query->get('is_delete', 'false');

        // 🔥 RÉCUPÉRER LES CATÉGORIES
        $categoryIds = null;
        $categoryIdsParam = $request->query->get('category_ids');
        if ($categoryIdsParam) {
            $categoryIds = array_map('intval', explode(',', $categoryIdsParam));
        }

        $allServicesRequested = filter_var($request->query->get('all_services', false), FILTER_VALIDATE_BOOLEAN);
        $user = $this->getUser();
        if ($user && method_exists($user, 'getId')) {
            $isAdmin = false;
            $adminRoleNames = ['Administrateur', 'Administrateur patrimonial', 'Administrateur système'];
            foreach ($user->getAssignedRoles() as $role) {
                if (in_array($role->getNom(), $adminRoleNames, true)) {
                    $isAdmin = true;
                    break;
                }
            }

            if (!$allServicesRequested || !$isAdmin) {
                $serviceId = $user->getService()?->getId();
            }
        }

        $items = $consumableRepository->findPaginated($page, $limit, $search, $serviceId, $isDelete, $categoryIds);
        $total = $consumableRepository->countAll($search, $serviceId, $isDelete, $categoryIds);

        // 🔥 CALCULER LE STOCK POUR CHAQUE CONSOMMABLE
        $data = array_map(function ($consumable) use ($transferRepository) {
            $serviceId = $consumable->getService()?->getId();
            $stockActuel = $serviceId 
                ? $transferRepository->getCurrentStock($consumable->getId(), $serviceId)
                : 0;
            
            return $this->normalizeListItem($consumable, $stockActuel);
        }, $items);

        $meta = [
            'current_page' => $page,
            'limit' => $limit,
            'total_items' => $total,
            'total_pages' => (int) ceil($total / max(1, $limit)),
        ];

        return $apiResponse->success([
            'meta' => $meta,
            'data' => $data,
        ], Response::HTTP_OK, 'Consomptibles retournés avec succès.');
    }

    // 🔥 MODIFIER LA MÉTHODE DE NORMALISATION
    private function normalizeListItem($consumable, float $stockActuel): array
    {
        return [
            'id' => $consumable->getId(),
            'nom' => $consumable->getNom(),
            'description' => $consumable->getDescription(),
            'quantite' => $consumable->getQuantite(),
            'stockActuel' => (string) $stockActuel, // 🔥 NOUVEAU CHAMP
            'prixInitial' => $consumable->getPrixInitial(),
            'prixTotal' => $consumable->getPrixTotal(),
            'category' => $consumable->getCategory() ? ['id' => $consumable->getCategory()->getId(), 'nom' => $consumable->getCategory()->getNom()] : null,
            'assetType' => $consumable->getAssetType() ? ['id' => $consumable->getAssetType()->getId(), 'nom' => $consumable->getAssetType()->getNom()] : null,
            'assetSubType' => $consumable->getAssetSubType() ? ['id' => $consumable->getAssetSubType()->getId(), 'nom' => $consumable->getAssetSubType()->getNom()] : null,
            'service' => $consumable->getService() ? ['id' => $consumable->getService()->getId(), 'nom' => $consumable->getService()->getNom()] : null,
            'createdAt' => $consumable->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $consumable->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}