<?php

namespace App\Controller\Departements;

use App\Repository\DepartementRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/departements')]
#[OA\Tag(name: 'Departements')]
final class DepartementDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_departement_detail', methods: ['GET'])]
    #[OA\Get(path: '/departements/{id}', summary: 'Détail d\'un département (avec arrondissements)')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success - Détail département retourné', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Département retourné avec succès.', 'data' => ['id' => 1, 'nom' => 'Mfoundi', 'code' => 'MF', 'region_id' => 1, 'is_delete' => false]]))]
    #[OA\Response(response: 404, description: 'Not Found - Département non trouvé')]
    public function __invoke(
        int $id,
        DepartementRepository $departementRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $departement = $departementRepository->getDepartementWithArrondissements($id);
        if (!$departement || $departement->isDelete()) {
            return $apiResponse->error('Département non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($departement, 'json', [
            'groups' => ['departement:detail', 'arrondissement:list'],
            'circular_reference_handler' => static fn (object $object): ?int => method_exists($object, 'getId') ? $object->getId() : null,
        ]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Département retourné avec succès.');
    }
}
