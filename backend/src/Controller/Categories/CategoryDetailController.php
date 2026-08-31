<?php

namespace App\Controller\Categories;

use App\Entity\Category;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/categories')]
#[OA\Tag(name: 'Categories')]
final class CategoryDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_category_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/categories/{id}',
        summary: 'Détail d\'une catégorie (avec ses types de biens rattachés)'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success - Catégorie retournée avec ses types de biens')]
    #[OA\Response(response: 404, description: 'Not Found - Catégorie non trouvée')]
   public function __invoke(
        Category $category,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($category->isDelete()) {
            return $apiResponse->error('Catégorie non trouvée.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($category, 'json', ['groups' => ['category:detail', 'asset_type:list', 'champ:list']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Catégorie retournée avec succès.');
    }
}