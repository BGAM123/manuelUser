<?php

namespace App\Controller\ConsumableEntries;

use App\Repository\ConsumableEntryRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumable-entries')]
#[OA\Tag(name: 'Consomptibles')]
final class ListConsumableEntriesController extends AbstractController
{
    #[Route('', name: 'app_consumable_entry_list', methods: ['GET'])]
    #[OA\Get(
        path: '/consumable-entries',
        summary: 'Lister les entrées de consomptibles',
        description: 'Liste paginée des entrées de consomptibles.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Recherche sur nom du consomptible, observations')]
    #[OA\Parameter(name: 'consumable_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filtrer par consomptible')]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'),
        description: 'false (défaut) = entrées actives uniquement. true = entrées supprimées (corbeille) uniquement. all = toutes.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Entrées retournées avec succès.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 1, 'total_pages' => 1],
                    'data' => [
                        [
                            'id' => 1,
                            'consumable' => ['id' => 1, 'nom' => 'Papier A4'],
                            'service' => ['id' => 16, 'nom' => 'Direction des Systèmes d\'Information'],
                            'quantite' => '1000.00',
                            'dateEntree' => '2026-08-16',
                            'observations' => 'Réception mensuelle',
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
        ConsumableEntryRepository $consumableEntryRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $consumableId = $request->query->has('consumable_id') ? $request->query->getInt('consumable_id') : null;
        $isDelete = $request->query->get('is_delete', 'false');

        $items = $consumableEntryRepository->findPaginated($page, $limit, $search, $consumableId, $isDelete);
        $total = $consumableEntryRepository->countAll($search, $consumableId, $isDelete);

        $data = array_map(fn ($entry) => $this->normalizeListItem($entry), $items);

        $meta = [
            'current_page' => $page,
            'limit' => $limit,
            'total_items' => $total,
            'total_pages' => (int) ceil($total / max(1, $limit)),
        ];

        return $apiResponse->success([
            'meta' => $meta,
            'data' => $data,
        ], Response::HTTP_OK, 'Entrées retournées avec succès.');
    }

    private function normalizeListItem($entry): array
    {
        return [
            'id' => $entry->getId(),
            'consumable' => $entry->getConsumable() ? ['id' => $entry->getConsumable()->getId(), 'nom' => $entry->getConsumable()->getNom()] : null,
            'service' => $entry->getService() ? ['id' => $entry->getService()->getId(), 'nom' => $entry->getService()->getNom()] : null,
            'quantite' => $entry->getQuantite(),
            'dateEntree' => $entry->getDateEntree()?->format('Y-m-d'),
            'observations' => $entry->getObservations(),
            'createdAt' => $entry->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $entry->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
