<?php

namespace App\Controller\Consumables;

use App\Entity\Consumable;
use App\Entity\User;
use App\Repository\ConsumableRepository;
use App\Security\ConsumableAccessChecker;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/consumables')]
#[OA\Tag(name: 'Consomptibles')]
final class ConsumableBilanController extends AbstractController
{
    #[Route('/{id}/bilan', name: 'app_consumable_bilan', methods: ['GET'])]
    #[OA\Get(
        path: '/consumables/{id}/bilan',
        summary: 'Bilan d\'un consomptible',
        description: 'Retourne le bilan d\'un consomptible sur une période donnée.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Parameter(name: 'dateDebut', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de début (YYYY-MM-DD)')]
    #[OA\Parameter(name: 'dateFin', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de fin (YYYY-MM-DD)')]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Bilan récupéré avec succès.',
                'data' => [
                    // 'stockDebutPeriode' => 5000.00,
                    'totalEntrees' => 1000.00,
                    'totalTransfere' => 1500.00,
                    'stockActuel' => 4500.00,
                    'parService' => [
                        ['serviceId' => 16, 'serviceNom' => 'Direction des Systèmes d\'Information', 'quantite' => 1000.00],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le consomptible demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        int $id,
        Request $request,
        #[CurrentUser] User $user,
        ConsumableRepository $consumableRepository,
        ConsumableAccessChecker $accessChecker,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $consumable = $consumableRepository->getActiveById($id);
        if (!$consumable instanceof Consumable) {
            return $apiResponse->error('Le consomptible demandé est introuvable.', Response::HTTP_NOT_FOUND);
        }

        $accessChecker->assertCanAccessConsumable($user, $consumable);

        $dateDebut = $request->query->get('dateDebut');
        $dateFin = $request->query->get('dateFin');

        $dateDebutObj = $dateDebut ? new \DateTime($dateDebut) : null;
        $dateFinObj = $dateFin ? new \DateTime($dateFin) : null;

        $bilan = $consumableRepository->getBilan($id, $dateDebutObj, $dateFinObj);

        return $apiResponse->success(
            $bilan,
            Response::HTTP_OK,
            'Bilan récupéré avec succès.'
        );
    }
}
