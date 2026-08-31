<?php

namespace App\Controller\Regions;

use App\Entity\Region;
use App\Repository\RegionRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/regions')]
#[OA\Tag(name: 'Regions')]
final class RestoreRegionController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_region_restore', methods: ['POST'])]
    #[OA\Post(path: '/regions/{id}/restore', summary: 'Restaurer une région supprimée logiquement')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success - Région restaurée avec succès', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Région restaurée avec succès.', 'data' => ['id' => 1, 'nom' => 'Centre', 'code' => 'CE', 'is_delete' => false]]))]
    #[OA\Response(response: 404, description: 'Not Found - Région non trouvée')]
    #[OA\Response(response: 409, description: 'Conflict - Région non supprimée')]
    public function __invoke(
        Region $region,
        RegionRepository $regionRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (!$region->isDelete()) {
            return $apiResponse->error('Cette région n\'est pas supprimée.', Response::HTTP_CONFLICT);
        }

        if ($regionRepository->countActiveRegions() >= 10) {
            return $apiResponse->error(
                'Impossible de restaurer cette région: la limite globale de 10 régions actives est déjà atteinte.',
                Response::HTTP_CONFLICT
            );
        }

        $regionRepository->restore($region);

        $data = json_decode($serializer->serialize($region, 'json', ['groups' => ['region:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Région restaurée avec succès.');
    }
}
