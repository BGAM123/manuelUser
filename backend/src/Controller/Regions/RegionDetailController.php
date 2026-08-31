<?php

namespace App\Controller\Regions;

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
final class RegionDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_region_detail', methods: ['GET'])]
    #[OA\Get(path: '/regions/{id}', summary: 'Détail d\'une région (avec départements et arrondissements)')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success - Détail région retourné', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Région retournée avec succès.', 'data' => ['id' => 1, 'nom' => 'Centre', 'code' => 'CE', 'is_delete' => false]]))]
    #[OA\Response(response: 404, description: 'Not Found - Région non trouvée')]
    public function __invoke(
        int $id,
        RegionRepository $regionRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $region = $regionRepository->getRegionWithDepartementsAndArrondissements($id);
        if (!$region || $region->isDelete()) {
            return $apiResponse->error('Région non trouvée.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($region, 'json', [
            'groups' => ['region:detail', 'departement:detail', 'arrondissement:list'],
            'circular_reference_handler' => static fn (object $object): ?int => method_exists($object, 'getId') ? $object->getId() : null,
        ]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Région retournée avec succès.');
    }
}
