<?php

namespace App\Controller\Groupes;

use App\Entity\Groupe;
use App\Exception\ResourceInUseException;
use App\Repository\GroupeRepository;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/groupes')]
#[OA\Tag(name: 'Groupes')]
final class SoftDeleteGroupeController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_groupe_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/groupes/{id}/soft-delete',
        summary: 'Suppression d\'un groupe',
        description: "Par défaut, passe is_delete/is_active à true. Aucun blocage même si des utilisateurs actifs sont rattachés : leurs droits hérités du groupe disparaissent simplement de leurs permissions effectives. Avec force=true, supprime définitivement la ligne en base."
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
        description: 'Success - Groupe désactivé (suppression logique)',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Groupe supprimé (logiquement) avec succès.', 'data' => null]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null]))]
    #[OA\Response(response: 409, description: 'Conflict - Déjà supprimé')]
    public function __invoke(
        Groupe $groupe,
        Request $request,
        GroupeRepository $groupeRepository,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        if (!$force && $groupe->isDelete()) {
            return $apiResponse->error('Ce groupe est déjà supprimé.', Response::HTTP_CONFLICT);
        }

        if ($force) {
            try {
                $forceDeleteService->delete($groupe);
            } catch (ResourceInUseException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            return $apiResponse->success(null, Response::HTTP_OK, 'Groupe supprimé définitivement avec succès.');
        }

        $groupeRepository->softDelete($groupe);

        return $apiResponse->success(null, Response::HTTP_OK, 'Groupe supprimé (logiquement) avec succès.');
    }
}