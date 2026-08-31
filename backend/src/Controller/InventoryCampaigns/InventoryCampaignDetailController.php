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
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/inventory-campaigns')]
#[OA\Tag(name: 'InventoryCampaigns')]
final class InventoryCampaignDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_inventory_campaign_detail', methods: ['GET'])]
    #[OA\Get(path: '/inventory-campaigns/{id}', summary: 'Détail d\'une campagne (avec compteurs de suivi)')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 404, description: 'Not Found')]
    public function __invoke(
        InventaireCampagne $campaign,
        InventaireCampagneRepository $campaignRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($campaign->isDelete()) {
            return $apiResponse->error('Campagne non trouvée.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($campaign, 'json', ['groups' => ['inventory_campaign:detail']]), true);
        $total = count($campaign->getItems());
        $manquants = $campaignRepository->countMissingItems($campaign);
        $data['suivi'] = [
            'total_biens' => $total,
            'retrouves' => $total - $manquants,
            'manquants' => $manquants,
        ];

        return $apiResponse->success($data, Response::HTTP_OK, 'Campagne retournée avec succès.');
    }
}