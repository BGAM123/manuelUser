<?php

namespace App\Controller\Categories;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/categories')]
#[OA\Tag(name: 'Categories')]
final class UpdateCategoryController extends AbstractController
{
    #[Route('/{id}', name: 'app_category_update', methods: ['PUT'])]
    #[OA\Put(summary: 'Mettre à jour une catégorie')]
    #[OA\Patch(summary: 'Mettre à jour partiellement une catégorie')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Véhicules et matériel roulant'),
                new OA\Property(property: 'description', type: 'string', example: 'Description mise à jour'),
                new OA\Property(property: 'seuil', type: 'integer', example: 1000000),
                new OA\Property(property: 'consommable', type: 'boolean', example: false),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Catégorie mise à jour avec succès',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Catégorie mise à jour avec succès.', 'data' => ['id' => 1, 'consommable' => false]]     
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide')]
    #[OA\Response(response: 404, description: 'Not Found - Catégorie non trouvée')]
    #[OA\Response(response: 409, description: 'Conflict - Nom déjà utilisé, ou catégorie supprimée')]
    public function __invoke(
        Category $category,
        Request $request,
        CategoryRepository $categoryRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($category->isDelete()) {
            return $apiResponse->error(
                'Cette catégorie est supprimée.',
                Response::HTTP_CONFLICT
            );
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (
            !empty($payload['nom'])
            && $categoryRepository->existsByNom((string) $payload['nom'], $category->getId())
        ) {
            return $apiResponse->error('Ce nom de catégorie est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        $categoryRepository->applyPayloadToCategory($category, $payload);

        $errors = $validator->validate($category);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $categoryRepository->save($category);

        $data = json_decode($serializer->serialize($category, 'json', ['groups' => ['category:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Catégorie mise à jour avec succès.');
    }
}