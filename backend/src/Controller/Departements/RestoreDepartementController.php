<?php

namespace App\Controller\Departements;

use App\Entity\Departement;
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
final class RestoreDepartementController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_departement_restore', methods: ['POST'])]
    #[OA\Post(path: '/departements/{id}/restore', summary: 'Restaurer un département supprimé logiquement')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success - Département restauré avec succès', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Département restauré avec succès.', 'data' => ['id' => 1, 'nom' => 'Mfoundi', 'code' => 'MF', 'region_id' => 1, 'is_delete' => false]]))]
    #[OA\Response(response: 404, description: 'Not Found - Département non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - Département non supprimé ou région supprimée')]
    public function __invoke(
        Departement $departement,
        DepartementRepository $departementRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (!$departement->isDelete()) {
            return $apiResponse->error('Ce département n\'est pas supprimé.', Response::HTTP_CONFLICT);
        }

        if ($departement->getRegion()?->isDelete()) {
            return $apiResponse->error('Impossible de restaurer : la région parente est supprimée.', Response::HTTP_CONFLICT);
        }

        $departementRepository->restore($departement);

        $data = json_decode($serializer->serialize($departement, 'json', ['groups' => ['departement:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Département restauré avec succès.');
    }
}
