<?php

namespace App\Controller\InventoryCampaigns;

use App\Entity\InventaireCampagne;
use App\Repository\InventoryCampaignItemRepository;
use App\Service\ApiResponseFactory;
use App\Service\PaginationFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/inventory-campaigns/{id}/items')]
#[OA\Tag(name: 'InventoryCampaigns')]
final class ListInventoryCampaignItemsController extends AbstractController
{
    #[Route('', name: 'app_inventory_campaign_items_list', methods: ['GET'])]
    #[OA\Get(
        path: '/inventory-campaigns/{id}/items',
        summary: 'Lister les biens d\'une campagne',
        description: 'Filtre retrouve=false pour obtenir exactement les biens attendus mais non retrouvés (exploité par le module IA).'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'retrouve', in: 'query', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 404, description: 'Not Found - Campagne non trouvée')]
    public function __invoke(
        InventaireCampagne $campaign,
        Request $request,
        InventoryCampaignItemRepository $itemRepository,
        SerializerInterface $serializer,
        PaginationFactory $paginationFactory,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($campaign->isDelete()) {
            return $apiResponse->error('Campagne non trouvée.', Response::HTTP_NOT_FOUND);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $retrouveParam = $request->query->get('retrouve');
        $retrouve = null !== $retrouveParam ? filter_var($retrouveParam, FILTER_VALIDATE_BOOLEAN) : null;

        $items = $itemRepository->findPaginatedByCampaign($campaign, $page, $limit, $retrouve);
        $total = $itemRepository->countByCampaign($campaign, $retrouve);

        $serialized = json_decode($serializer->serialize($items, 'json', ['groups' => ['inventory_campaign_item:list']]), true);
        $paginatedData = $paginationFactory->createPaginatedResponse($serialized, $page, $limit, $total);

        return $apiResponse->success($paginatedData, Response::HTTP_OK, 'Biens de la campagne retournés avec succès.');
    }
}