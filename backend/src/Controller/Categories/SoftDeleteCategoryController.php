<?php

namespace App\Controller\Categories;

use App\Entity\Category;
use App\Exception\ResourceInUseException;
use App\Repository\CategoryRepository;
use App\Service\ApiResponseFactory;
use App\Service\DefaultAssetReferencesService;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/categories')]
#[OA\Tag(name: 'Categories')]
final class SoftDeleteCategoryController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_category_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/categories/{id}/soft-delete',
        summary: 'Suppression d\'une catégorie',
        description: "Par défaut, passe is_delete à true. Avec force=true, supprime définitivement la ligne en base."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'force',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'true = suppression définitive (hard delete) au lieu de la suppression logique par défaut.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Catégorie supprimée ',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Catégorie supprimée (logiquement) avec succès.', 'data' => null]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found - Catégorie non trouvée')]
    #[OA\Response(response: 409, description: 'Conflict - Déjà supprimée, ou des types de biens actifs y sont rattachés')]
    public function __invoke(
        Category $category,
        Request $request,
        CategoryRepository $categoryRepository,
        DefaultAssetReferencesService $defaultAssetReferences,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        if (!$force && $category->isDelete()) {
            return $apiResponse->error('Cette catégorie est déjà supprimée.', Response::HTTP_CONFLICT);
        }

        if ($defaultAssetReferences->isDefaultCategory($category)) {
            return $apiResponse->error('La catégorie par défaut ne peut pas être supprimée.', Response::HTTP_CONFLICT);
        }

        if ($force) {
            try {
                $forceDeleteService->delete($category);
            } catch (ResourceInUseException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            return $apiResponse->success(null, Response::HTTP_OK, 'Catégorie supprimée définitivement avec succès.');
        }

        $defaultAssetReferences->reassignAssetTypesToDefaultCategory($category);
        $categoryRepository->softDelete($category);

        return $apiResponse->success(null, Response::HTTP_OK, 'Catégorie supprimée avec succès.');
    }
}