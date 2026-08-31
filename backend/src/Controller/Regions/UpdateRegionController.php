<?php

namespace App\Controller\Regions;

use App\Entity\Region;
use App\Repository\RegionRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/regions')]
#[OA\Tag(name: 'Regions')]
final class UpdateRegionController extends AbstractController
{
    #[Route('/{id}', name: 'app_region_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(path: '/regions/{id}', summary: 'Mettre à jour une région')]
    #[OA\Patch(path: '/regions/{id}', summary: 'Mettre à jour partiellement une région')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Région mise à jour'),
                new OA\Property(property: 'description', type: 'string', example: 'Description mise à jour'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Success - Région mise à jour avec succès', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Région mise à jour avec succès.', 'data' => ['id' => 1, 'nom' => 'Centre', 'code' => 'CE', 'is_delete' => false]]))]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide')]
    #[OA\Response(response: 404, description: 'Not Found - Région non trouvée')]
    #[OA\Response(response: 409, description: 'Conflict - Région supprimée ou nom déjà utilisé')]
    public function __invoke(
        Region $region,
        Request $request,
        RegionRepository $regionRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($region->isDelete()) {
            return $apiResponse->error('Cette région est supprimée.', Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (!empty($payload['nom']) && $regionRepository->existsByNom((string) $payload['nom'], $region->getId())) {
            return $apiResponse->error('Ce nom de région est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        $regionRepository->applyPayloadToRegion($region, $payload);

        $errors = $validator->validate($region);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $regionRepository->save($region);

        $data = json_decode($serializer->serialize($region, 'json', ['groups' => ['region:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Région mise à jour avec succès.');
    }
}
