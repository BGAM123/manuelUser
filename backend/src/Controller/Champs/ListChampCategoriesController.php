<?php

namespace App\Controller\Champs;

use App\Entity\Champ;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/champs')]
#[OA\Tag(name: 'Champs')]
final class ListChampCategoriesController extends AbstractController
{
    #[Route('/{id}/categories', name: 'app_champ_categories_list', methods: ['GET'])]
    #[OA\Get(path: '/champs/{id}/categories', summary: 'Lister les catégories associées à un champ')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 404, description: 'Not Found')]
    public function __invoke(
        Champ $champ,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($champ->isDelete()) {
            return $apiResponse->error('Champ non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $categories = [];
        foreach ($champ->getCategories() as $category) {
            if (!$category->isDelete()) {
                $categories[] = $category;
            }
        }

        $data = json_decode($serializer->serialize($categories, 'json', ['groups' => ['category:list']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Catégories du champ retournées avec succès.');
    }
}
