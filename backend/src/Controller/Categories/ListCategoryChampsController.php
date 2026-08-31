<?php

namespace App\Controller\Categories;

use App\Entity\Category;
use App\Repository\ChampRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/categories')]
#[OA\Tag(name: 'Champs')]
final class ListCategoryChampsController extends AbstractController
{
    #[Route('/{id}/champs', name: 'app_category_champs_list', methods: ['GET'])]
    #[OA\Get(path: '/categories/{id}/champs', summary: 'Lister les champs associés à une catégorie')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 404, description: 'Not Found')]
    public function __invoke(
        Category $category,
        ChampRepository $champRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($category->isDelete()) {
            return $apiResponse->error('Catégorie non trouvée.', Response::HTTP_NOT_FOUND);
        }

        $champs = $champRepository->findActiveByCategoryId((int) $category->getId());
        $data = json_decode($serializer->serialize($champs, 'json', ['groups' => ['champ:list']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Champs de la catégorie retournés avec succès.');
    }
}
