<?php

namespace App\Controller\InventoryCampaigns;

use App\Repository\InventaireCampagneRepository;
use App\Service\ApiResponseFactory;
use App\Service\PaginationFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/inventory-campaigns')]
#[OA\Tag(name: 'InventoryCampaigns')]
final class ListInventoryCampaignsController extends AbstractController
{
    #[Route('', name: 'app_inventory_campaign_list', methods: ['GET'])]
    #[OA\Get(path: '/inventory-campaigns', summary: 'Lister les campagnes d\'inventaire')]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['EN_COURS', 'CLOTUREE']))]
    #[OA\Response(response: 200, description: 'Success')]
    public function __invoke(
        Request $request,
        InventaireCampagneRepository $campaignRepository,
        SerializerInterface $serializer,
        PaginationFactory $paginationFactory,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $statut = $request->query->get('statut');

        $campaigns = $campaignRepository->findPaginatedCampaigns($page, $limit, $statut);
        $total = $campaignRepository->countCampaigns($statut);

        $serialized = json_decode($serializer->serialize($campaigns, 'json', ['groups' => ['inventory_campaign:list']]), true);
        $paginatedData = $paginationFactory->createPaginatedResponse($serialized, $page, $limit, $total);

        return $apiResponse->success($paginatedData, Response::HTTP_OK, 'Campagnes d\'inventaire retournées avec succès.');
    }
}