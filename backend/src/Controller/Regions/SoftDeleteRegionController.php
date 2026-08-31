<?php

namespace App\Controller\Regions;

use App\Entity\Region;
use App\Exception\ResourceInUseException;
use App\Repository\RegionRepository;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/regions')]
#[OA\Tag(name: 'Regions')]
final class SoftDeleteRegionController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_region_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/regions/{id}/soft-delete',
        summary: 'Suppression d\'une région',
        description: 'Par défaut, passe is_delete à true. Avec force=true, supprime définitivement la ligne en base.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'force',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'true = suppression définitive (hard delete) au lieu de la suppression logique par défaut.'
    )]
    #[OA\Response(response: 200, description: 'Success - Région supprimée avec succès', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Région supprimée avec succès.', 'data' => null]))]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found - Région non trouvée')]
    #[OA\Response(response: 409, description: 'Conflict - Déjà supprimée ou enfants actifs')]
    public function __invoke(
        Region $region,
        Request $request,
        RegionRepository $regionRepository,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        if (!$force && $region->isDelete()) {
            return $apiResponse->error('Cette région est déjà supprimée.', Response::HTTP_CONFLICT);
        }

        $activeCount = $regionRepository->countActiveDepartements($region);
        if ($activeCount > 0) {
            return $apiResponse->error(
                sprintf('Impossible de supprimer cette région : %d département(s) actif(s) y sont encore rattachés.', $activeCount),
                Response::HTTP_CONFLICT
            );
        }

        if ($force) {
            try {
                $forceDeleteService->delete($region);
            } catch (ResourceInUseException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            return $apiResponse->success(null, Response::HTTP_OK, 'Région supprimée définitivement avec succès.');
        }

        $regionRepository->softDelete($region);

        return $apiResponse->success(null, Response::HTTP_OK, 'Région supprimée avec succès.');
    }
}
