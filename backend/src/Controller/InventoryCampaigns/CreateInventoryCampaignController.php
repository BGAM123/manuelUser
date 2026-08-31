<?php

namespace App\Controller\InventoryCampaigns;

use App\Repository\AssetRepository;
use App\Repository\CategoryRepository;
use App\Repository\InventaireCampagnRepository;
use App\Repository\ServiceRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/inventory-campaigns')]
#[OA\Tag(name: 'InventoryCampaigns')]
final class CreateInventoryCampaignController extends AbstractController
{
    #[Route('', name: 'app_inventory_campaign_create', methods: ['POST'])]
    #[OA\Post(
        path: '/inventory-campaigns',
        summary: 'Lancer une campagne d\'inventaire',
        description: 'Fige immédiatement la liste des biens actifs concernés (filtrés par service_id et/ou category_id, sinon tous les biens actifs). Cette liste ne pourra plus être modifiée après coup.'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['nom'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Inventaire annuel 2026'),
                new OA\Property(property: 'description', type: 'string', example: 'Contrôle physique de fin d\'exercice'),
                new OA\Property(property: 'service_id', type: 'integer', example: 3, description: 'Optionnel - limite la campagne à ce service'),
                new OA\Property(property: 'category_id', type: 'integer', example: 1, description: 'Optionnel - limite la campagne à cette catégorie'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Created - Campagne créée, biens figés')]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide, service/catégorie inexistant, ou aucun bien actif dans le périmètre')]
    public function __invoke(
        Request $request,
        InventaireCampagneRepository $campaignRepository,
        AssetRepository $assetRepository,
        ServiceRepository $serviceRepository,
        CategoryRepository $categoryRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || empty($payload['nom'])) {
            return $apiResponse->error('Le champ nom est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        $service = null;
        if (!empty($payload['service_id'])) {
            $service = $serviceRepository->getServiceById((int) $payload['service_id']);
            if (!$service) {
                return $apiResponse->error('Service introuvable.', Response::HTTP_BAD_REQUEST);
            }
        }

        $category = null;
        if (!empty($payload['category_id'])) {
            $category = $categoryRepository->getActiveCategoryById((int) $payload['category_id']);
            if (!$category) {
                return $apiResponse->error('Catégorie introuvable ou supprimée.', Response::HTTP_BAD_REQUEST);
            }
        }

        $assetsToFreeze = $assetRepository->findAllActiveForCampaignScope(
            $service?->getId(),
            $category?->getId()
        );

        if ([] === $assetsToFreeze) {
            return $apiResponse->error('Aucun bien actif ne correspond à ce périmètre. Aucune campagne créée.', Response::HTTP_BAD_REQUEST);
        }

        $campaign = $campaignRepository->buildCampaignFromPayload($payload, $assetsToFreeze, $service, $category);

        $errors = $validator->validate($campaign);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $campaignRepository->save($campaign);

        $data = json_decode($serializer->serialize($campaign, 'json', ['groups' => ['inventory_campaign:detail']]), true);
        $data['nombre_biens_figes'] = count($assetsToFreeze);

        return $apiResponse->success($data, Response::HTTP_CREATED, 'Campagne d\'inventaire créée avec succès.');
    }
}