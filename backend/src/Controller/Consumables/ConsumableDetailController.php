<?php

namespace App\Controller\Consumables;

use App\Entity\Consumable;
use App\Repository\ConsumableRepository;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableTransferStockManager; // 🔥 NOUVEAU
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumables')]
#[OA\Tag(name: 'Consomptibles')]
final class ConsumableDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_consumable_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/consumables/{id}',
        summary: 'Détail d\'un consomptible',
        description: 'Retourne le consomptible complet avec ses pièces jointes et le stock actuel calculé.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Détail du consomptible récupéré avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Papier A4',
                    'description' => 'Papier format A4 80g/m²',
                    'quantite' => '5000.00',
                    'stockActuel' => '4500.00',
                    'prixInitial' => '10.00',
                    'prixTotal' => '50000.00',
                    'category' => ['id' => 2, 'nom' => 'Fournitures de bureau'],
                    'assetType' => null,
                    'assetSubType' => null,
                    'service' => ['id' => 16, 'nom' => 'Direction des Systèmes d\'Information'],
                    'pieceJointes' => [
                        ['id' => 1, 'nom' => 'Fiche technique', 'chemin' => '/uploads/consumables/fiche.pdf'],
                    ],
                    'createdAt' => '2026-08-16 10:00:00',
                    'updatedAt' => '2026-08-16 10:00:00',
                ],
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le consomptible demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        int $id,
        ConsumableRepository $consumableRepository,
        ConsumableTransferStockManager $stockManager, // 🔥 NOUVEAU
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $consumable = $consumableRepository->getActiveById($id);
        if (!$consumable instanceof Consumable) {
            return $apiResponse->error('Le consomptible demandé est introuvable.', Response::HTTP_NOT_FOUND);
        }

        // 🔥 UTILISER LE NOUVEAU STOCK MANAGER
        $serviceId = $consumable->getService()?->getId();
        $stockActuel = $serviceId 
            ? $stockManager->getCurrentStock($id, $serviceId)
            : 0;

        // 🔥 METTRE À JOUR LE STOCK DANS L'ENTITÉ (optionnel, pour le cache)
        $consumable->setStockActuel((string) $stockActuel);

        return $apiResponse->success(
            $this->normalizeDetail($consumable, $stockActuel),
            Response::HTTP_OK,
            'Détail du consomptible récupéré avec succès.'
        );
    }

    private function normalizeDetail(Consumable $consumable, float $stockActuel): array
    {
        return [
            'id' => $consumable->getId(),
            'nom' => $consumable->getNom(),
            'description' => $consumable->getDescription(),
            'quantite' => $consumable->getQuantite(),
            'stockActuel' => (string) $stockActuel,
            'prixInitial' => $consumable->getPrixInitial(),
            'prixTotal' => $consumable->getPrixTotal(),
            'category' => $consumable->getCategory() ? ['id' => $consumable->getCategory()->getId(), 'nom' => $consumable->getCategory()->getNom()] : null,
            'assetType' => $consumable->getAssetType() ? ['id' => $consumable->getAssetType()->getId(), 'nom' => $consumable->getAssetType()->getNom()] : null,
            'assetSubType' => $consumable->getAssetSubType() ? ['id' => $consumable->getAssetSubType()->getId(), 'nom' => $consumable->getAssetSubType()->getNom()] : null,
            'service' => $consumable->getService() ? ['id' => $consumable->getService()->getId(), 'nom' => $consumable->getService()->getNom()] : null,
            'pieceJointes' => array_map(fn ($pj) => [
                'id' => $pj->getId(),
                'nom' => $pj->getNom(),
                'chemin' => $pj->getChemin(),
            ], $consumable->getPieceJointes()->toArray()),
            'createdAt' => $consumable->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $consumable->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}