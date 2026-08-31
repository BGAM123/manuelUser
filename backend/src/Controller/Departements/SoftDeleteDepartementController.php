<?php

namespace App\Controller\Departements;

use App\Entity\Departement;
use App\Exception\ResourceInUseException;
use App\Repository\DepartementRepository;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/departements')]
#[OA\Tag(name: 'Departements')]
final class SoftDeleteDepartementController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_departement_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/departements/{id}/soft-delete',
        summary: 'Suppression d\'un département',
        description: 'Par défaut, passe is_delete à true. Avec force=true, supprime définitivement la ligne en base.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'force',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'true = suppression définitive (hard delete) au lieu de la suppression logique par défaut.'
    )]
    #[OA\Response(response: 200, description: 'Success - Département supprimé avec succès', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Département supprimé avec succès.', 'data' => null]))]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found - Département non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - Déjà supprimé ou enfants actifs')]
    public function __invoke(
        Departement $departement,
        Request $request,
        DepartementRepository $departementRepository,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        if (!$force && $departement->isDelete()) {
            return $apiResponse->error('Ce département est déjà supprimé.', Response::HTTP_CONFLICT);
        }

        $activeCount = $departementRepository->countActiveArrondissements($departement);
        if ($activeCount > 0) {
            return $apiResponse->error(
                sprintf('Impossible de supprimer ce département : %d arrondissement(s) actif(s) y sont encore rattachés.', $activeCount),
                Response::HTTP_CONFLICT
            );
        }

        if ($force) {
            try {
                $forceDeleteService->delete($departement);
            } catch (ResourceInUseException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            return $apiResponse->success(null, Response::HTTP_OK, 'Département supprimé définitivement avec succès.');
        }

        $departementRepository->softDelete($departement);

        return $apiResponse->success(null, Response::HTTP_OK, 'Département supprimé avec succès.');
    }
}
