<?php

namespace App\Controller\Categories;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/categories')]
#[OA\Tag(name: 'Categories')]
final class RestoreCategoryController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_category_restore', methods: ['POST'])]
    #[OA\Post(
        path: '/categories/{id}/restore',
        summary: 'Restaurer une catégorie supprimée logiquement',
        description: 'Réservé à l\'administrateur : passe is_delete à false pour annuler une suppression logique.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success - Catégorie restaurée avec succès')]
    #[OA\Response(response: 404, description: 'Not Found - Catégorie non trouvée')]
    #[OA\Response(response: 409, description: 'Conflict - Cette catégorie n\'est pas supprimée')]
    public function __invoke(
        Category $category,
        CategoryRepository $categoryRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (!$category->isDelete()) {
            return $apiResponse->error('Cette catégorie n\'est pas supprimée.', Response::HTTP_CONFLICT);
        }

        $categoryRepository->restore($category);

        $data = json_decode($serializer->serialize($category, 'json', ['groups' => ['category:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Catégorie restaurée avec succès.');
    }
}