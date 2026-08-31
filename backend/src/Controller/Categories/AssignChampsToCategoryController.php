<?php

namespace App\Controller\Categories;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\ChampRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/categories')]
#[OA\Tag(name: 'Champs')]
final class AssignChampsToCategoryController extends AbstractController
{
    #[Route('/{id}/champs', name: 'app_category_assign_champs', methods: ['POST'])]
    #[OA\Post(
        path: '/categories/{id}/champs',
        summary: 'Associer plusieurs champs à une catégorie',
        description: 'Remplace la liste des champs associés à la catégorie par champ_ids fournis.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['champ_ids'],
            properties: [
                new OA\Property(
                    property: 'champ_ids',
                    type: 'array',
                    items: new OA\Items(type: 'integer'),
                    example: [1, 2, 3]
                ),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 400, description: 'Bad Request')]
    #[OA\Response(response: 404, description: 'Not Found')]
    #[OA\Response(response: 409, description: 'Conflict')]
    public function __invoke(
        Category $category,
        Request $request,
        CategoryRepository $categoryRepository,
        ChampRepository $champRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($category->isDelete()) {
            return $apiResponse->error('Cette catégorie est supprimée.', Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !array_key_exists('champ_ids', $payload) || !is_array($payload['champ_ids'])) {
            return $apiResponse->error('Le champ champ_ids (tableau) est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        $ids = array_values(array_unique(array_map('intval', $payload['champ_ids'])));
        $champs = $champRepository->findActiveByIds($ids);

        if (count($champs) !== count($ids)) {
            return $apiResponse->error(
                'Un ou plusieurs champs sont introuvables ou supprimés.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $category->syncChamps($champs);
        $category->setUpdatedAt(new \DateTimeImmutable());
        $categoryRepository->save($category);

        $data = json_decode(
            $serializer->serialize($category, 'json', ['groups' => ['category:detail', 'champ:list', 'asset_type:list']]),
            true
        );

        return $apiResponse->success($data, Response::HTTP_OK, 'Champs associés à la catégorie avec succès.');
    }
}
