<?php

namespace App\Controller\Categories;

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
final class CreateCategoryController extends AbstractController
{
    #[Route('', name: 'app_category_create', methods: ['POST'])]
    #[OA\Post(path: '/categories', summary: 'Créer une catégorie de biens')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['nom'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Véhicules'),
                new OA\Property(property: 'description', type: 'string', example: 'Ensemble du matériel roulant du MINEPIA'),
                new OA\Property(property: 'seuil', type: 'integer', example: 1000000),
                new OA\Property(property: 'consommable', type: 'boolean', example: false),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Catégorie créée avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Catégorie créée avec succès.',
                'data' => ['id' => 1, 'nom' => 'Véhicules', 'description' => 'Ensemble du matériel roulant du MINEPIA', 'is_delete' => false, 'seuil' => 1000000, 'consommable' => false],
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide')]
    #[OA\Response(response: 409, description: 'Conflict - Ce nom de catégorie existe déjà')]
    public function __invoke(
        Request $request,
        CategoryRepository $categoryRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        // Vérification explicite AVANT la validation, pour distinguer un 409 (conflit
        // métier) d'un 400 (payload structurellement invalide) - même logique que
        // CreatePermissionController.
        if (!empty($payload['nom']) && $categoryRepository->existsByNom((string) $payload['nom'])) {
            return $apiResponse->error('Ce nom de catégorie est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        $category = $categoryRepository->buildCategoryFromPayload($payload);

        $errors = $validator->validate($category);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $categoryRepository->save($category);

        $data = json_decode($serializer->serialize($category, 'json', ['groups' => ['category:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_CREATED, 'Catégorie créée avec succès.');
    }
}