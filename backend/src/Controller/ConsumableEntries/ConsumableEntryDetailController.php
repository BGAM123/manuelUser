<?php

namespace App\Controller\ConsumableEntries;

use App\Entity\ConsumableEntry;
use App\Repository\ConsumableEntryRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumable-entries')]
#[OA\Tag(name: 'Consomptibles')]
final class ConsumableEntryDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_consumable_entry_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/consumable-entries/{id}',
        summary: 'Détail d\'une entrée de consomptible',
        description: 'Retourne l\'entrée complète avec ses pièces jointes (factures).'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Détail de l\'entrée récupéré avec succès.',
                'data' => [
                    'id' => 1,
                    'consumable' => ['id' => 1, 'nom' => 'Papier A4'],
                    'service' => ['id' => 16, 'nom' => 'Direction des Systèmes d\'Information'],
                    'quantite' => '1000.00',
                    'dateEntree' => '2026-08-16',
                    'observations' => 'Réception mensuelle',
                    'pieceJointes' => [
                        ['id' => 1, 'nom' => 'Facture 001', 'chemin' => '/uploads/consumable-entries/facture.pdf'],
                    ],
                    'createdAt' => '2026-08-16 10:00:00',
                    'updatedAt' => '2026-08-16 10:00:00',
                ],
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'L\'entrée demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        int $id,
        ConsumableEntryRepository $consumableEntryRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $entry = $consumableEntryRepository->getActiveById($id);
        if (!$entry instanceof ConsumableEntry) {
            return $apiResponse->error('L\'entrée demandée est introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            $this->normalizeDetail($entry),
            Response::HTTP_OK,
            'Détail de l\'entrée récupéré avec succès.'
        );
    }

    private function normalizeDetail(ConsumableEntry $entry): array
    {
        return [
            'id' => $entry->getId(),
            'consumable' => $entry->getConsumable() ? ['id' => $entry->getConsumable()->getId(), 'nom' => $entry->getConsumable()->getNom()] : null,
            'service' => $entry->getService() ? ['id' => $entry->getService()->getId(), 'nom' => $entry->getService()->getNom()] : null,
            'quantite' => $entry->getQuantite(),
            'dateEntree' => $entry->getDateEntree()?->format('Y-m-d'),
            'observations' => $entry->getObservations(),
            'pieceJointes' => array_map(fn ($pj) => [
                'id' => $pj->getId(),
                'nom' => $pj->getNom(),
                'chemin' => $pj->getChemin(),
            ], $entry->getPieceJointes()->toArray()),
            'createdAt' => $entry->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $entry->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
