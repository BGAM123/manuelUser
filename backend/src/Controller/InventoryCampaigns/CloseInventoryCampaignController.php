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
final class CloseInventoryCampaignController extends AbstractController
{
    #[Route('/{id}/close', name: 'app_inventory_campaign_close', methods: ['POST'])]
    #[OA\Post(
        path: '/inventory-campaigns/{id}/close',
        summary: 'Clôturer une campagne d\'inventaire',
        description: 'Après clôture, plus aucun pointage n\'est possible sur cette campagne. Les biens encore non retrouvés à ce moment restent tels quels, exploitables par le module IA.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 404, description: 'Not Found')]
    #[OA\Response(response: 409, description: 'Conflict - Déjà clôturée')]
    public function __invoke(
        InventaireCampagne $campaign,
        InventaireCampagneRepository $campaignRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($campaign->isDelete()) {
            return $apiResponse->error('Campagne non trouvée.', Response::HTTP_NOT_FOUND);
        }
        if ($campaign->isCloturee()) {
            return $apiResponse->error('Cette campagne est déjà clôturée.', Response::HTTP_CONFLICT);
        }

        $campaign->close();
        $campaignRepository->save($campaign);

        return $apiResponse->success(null, Response::HTTP_OK, 'Campagne clôturée avec succès.');
    }
}