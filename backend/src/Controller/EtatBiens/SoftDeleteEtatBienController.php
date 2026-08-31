<?php

namespace App\Controller\EtatBiens;

use App\Entity\EtatBien;
use App\Exception\ResourceInUseException;
use App\Repository\EtatBienRepository;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/etat-biens')]
#[OA\Tag(name: 'EtatBiens')]
final class SoftDeleteEtatBienController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_etat_bien_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/etat-biens/{id}/soft-delete',
        summary: 'Suppression d\'un état de bien',
        description: 'Par défaut, passe is_delete à true. Avec force=true, supprime définitivement la ligne en base.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'force',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'true = suppression définitive (hard delete) au lieu de la suppression logique par défaut.'
    )]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found')]
    #[OA\Response(response: 409, description: 'Conflict')]
    public function __invoke(
        EtatBien $etatBien,
        Request $request,
        EtatBienRepository $etatBienRepository,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        if (!$force && $etatBien->isDelete()) {
            return $apiResponse->error('Cet état de bien est déjà supprimé.', Response::HTTP_CONFLICT);
        }

        if ($force) {
            try {
                $forceDeleteService->delete($etatBien);
            } catch (ResourceInUseException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            return $apiResponse->success(null, Response::HTTP_OK, 'État de bien supprimé définitivement avec succès.');
        }

        $etatBienRepository->softDelete($etatBien);

        return $apiResponse->success(null, Response::HTTP_OK, 'État de bien supprimé avec succès.');
    }
}
