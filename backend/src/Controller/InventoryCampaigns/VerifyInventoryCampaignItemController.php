<?php

namespace App\Controller\InventoryCampaigns;

use App\Entity\InventaireCampaign;
use App\Entity\InventoryCampaignItem;
use App\Entity\User;
use App\Repository\InventoryCampaignItemRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/inventory-campaigns/{id}/items/{itemId}/verify')]
#[OA\Tag(name: 'InventoryCampaigns')]
final class VerifyInventoryCampaignItemController extends AbstractController
{
    #[Route('', name: 'app_inventory_campaign_item_verify', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/inventory-campaigns/{id}/items/{itemId}/verify',
        summary: 'Pointer un bien comme physiquement retrouvé',
        description: 'Réservé à une campagne encore ouverte (EN_COURS).'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            type: 'object',
            properties: [new OA\Property(property: 'observations', type: 'string', example: 'État conforme au registre.')]
        )
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 404, description: 'Not Found - Campagne ou bien introuvable dans cette campagne')]
    #[OA\Response(response: 409, description: 'Conflict - Campagne clôturée')]
    public function __invoke(
        InventaireCampagne $campaign,
        int $itemId,
        Request $request,
        InventoryCampaignItemRepository $itemRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($campaign->isDelete()) {
            return $apiResponse->error('Campagne non trouvée.', Response::HTTP_NOT_FOUND);
        }
        if ($campaign->isCloturee()) {
            return $apiResponse->error('Cette campagne est clôturée, plus aucun pointage n\'est possible.', Response::HTTP_CONFLICT);
        }

        $item = $itemRepository->getItemById($itemId);
        if (!$item || $item->getCampaign()?->getId() !== $campaign->getId()) {
            return $apiResponse->error('Ce bien ne fait pas partie de cette campagne.', Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?: [];
        $observations = !empty($payload['observations']) ? (string) $payload['observations'] : null;

        $itemRepository->markAsFound($item, $user, $observations);

        $data = json_decode($serializer->serialize($item, 'json', ['groups' => ['inventory_campaign_item:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Bien marqué comme retrouvé avec succès.');
    }
}