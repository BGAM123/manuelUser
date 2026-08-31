<?php

namespace App\Controller\InventoryCampaigns;

use App\Entity\InventaireCampagne;
use App\Repository\InventaireCampagneRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inventory-campaigns')]
#[OA\Tag(name: 'InventoryCampaigns')]
final class SoftDeleteInventoryCampaignController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_inventory_campaign_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(path: '/inventory-campaigns/{id}/soft-delete', summary: 'Suppression logique d\'une campagne')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 404, description: 'Not Found')]
    #[OA\Response(response: 409, description: 'Conflict - Déjà supprimée')]
    public function __invoke(
        InventaireCampagne $campaign,
        InventaireCampagneRepository $campaignRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($campaign->isDelete()) {
            return $apiResponse->error('Cette campagne est déjà supprimée.', Response::HTTP_CONFLICT);
        }

        $campaignRepository->softDelete($campaign);

        return $apiResponse->success(null, Response::HTTP_OK, 'Campagne supprimée (logiquement) avec succès.');
    }
}