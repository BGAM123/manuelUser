<?php

namespace App\Controller\Arrondissements;

use App\Entity\Arrondissement;
use App\Exception\ResourceInUseException;
use App\Repository\ArrondissementRepository;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/arrondissements')]
#[OA\Tag(name: 'Arrondissements')]
final class SoftDeleteArrondissementController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_arrondissement_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/arrondissements/{id}/soft-delete',
        summary: 'Suppression d\'un arrondissement',
        description: 'Par défaut, passe is_delete à true. Avec force=true, supprime définitivement la ligne en base.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'force',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'true = suppression définitive (hard delete) au lieu de la suppression logique par défaut.'
    )]
    #[OA\Response(response: 200, description: 'Success - Arrondissement supprimé avec succès', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Arrondissement supprimé avec succès.', 'data' => null]))]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found - Arrondissement non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - Déjà supprimé')]
    public function __invoke(
        Arrondissement $arrondissement,
        Request $request,
        ArrondissementRepository $arrondissementRepository,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        if (!$force && $arrondissement->isDelete()) {
            return $apiResponse->error('Cet arrondissement est déjà supprimé.', Response::HTTP_CONFLICT);
        }

        if ($force) {
            try {
                $forceDeleteService->delete($arrondissement);
            } catch (ResourceInUseException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            return $apiResponse->success(null, Response::HTTP_OK, 'Arrondissement supprimé définitivement avec succès.');
        }

        $arrondissementRepository->softDelete($arrondissement);

        return $apiResponse->success(null, Response::HTTP_OK, 'Arrondissement supprimé avec succès.');
    }
}
