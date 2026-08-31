<?php

namespace App\Controller\ConsumableEntries;

use App\Repository\ConsumableEntryRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumable-entries')]
#[OA\Tag(name: 'Consomptibles')]
final class ConsumableEntriesByConsumableController extends AbstractController
{
    #[Route('/by-consumable/{consumableId}', name: 'app_consumable_entries_by_consumable', methods: ['GET'])]
    #[OA\Get(
        path: '/consumable-entries/by-consumable/{consumableId}',
        summary: 'Lister les entrées d\'un consomptible',
        description: 'Retourne toutes les entrées non supprimées pour un consomptible donné.'
    )]
    #[OA\Parameter(name: 'consumableId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Entrées retournées avec succès.',
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
            ]
        )
    )]
    public function __invoke(
        int $consumableId,
        ConsumableEntryRepository $consumableEntryRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $entries = $consumableEntryRepository->findByConsumable($consumableId);

        $data = array_map(fn ($entry) => [
            'id' => $entry->getId(),
            'consumable' => $entry->getConsumable() ? ['id' => $entry->getConsumable()->getId(), 'nom' => $entry->getConsumable()->getNom()] : null,
            'service' => $entry->getService() ? ['id' => $entry->getService()->getId(), 'nom' => $entry->getService()->getNom()] : null,
            'quantite' => $entry->getQuantite(),
            'dateEntree' => $entry->getDateEntree()?->format('Y-m-d'),
            'observations' => $entry->getObservations(),
            'createdAt' => $entry->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $entry->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ], $entries);

        return $apiResponse->success(
            $data,
            Response::HTTP_OK,
            'Entrées retournées avec succès.'
        );
    }
}
