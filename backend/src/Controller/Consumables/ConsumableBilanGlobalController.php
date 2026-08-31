<?php

namespace App\Controller\Consumables;

use App\Repository\ConsumableRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumables')]
#[OA\Tag(name: 'Consomptibles')]
final class ConsumableBilanGlobalController extends AbstractController
{
    #[Route('/bilan-global', name: 'app_consumable_bilan_global', methods: ['GET'])]
    #[OA\Get(
        path: '/consumables/bilan-global',
        summary: 'Bilan global des consomptibles',
        description: 'Retourne le bilan de tous les consomptibles sur une période donnée.'
    )]
    #[OA\Parameter(name: 'dateDebut', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de début (YYYY-MM-DD)')]
    #[OA\Parameter(name: 'dateFin', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de fin (YYYY-MM-DD)')]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Bilan global récupéré avec succès.',
                'data' => [
                    [
                        'consumable' => ['id' => 1, 'nom' => 'Papier A4'],
                        'bilan' => [
                            // 'stockDebutPeriode' => 5000.00,
                            'totalEntrees' => 1000.00,
                            'totalTransfere' => 1500.00,
                            'stockActuel' => 4500.00,
                            'parService' => [
                                ['serviceId' => 16, 'serviceNom' => 'DSI', 'quantite' => 1000.00],
                            ],
                        ],
                    ],
                ],
            ]
        )
    )]
    public function __invoke(
        Request $request,
        ConsumableRepository $consumableRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $dateDebut = $request->query->get('dateDebut');
        $dateFin = $request->query->get('dateFin');

        $dateDebutObj = $dateDebut ? new \DateTime($dateDebut) : null;
        $dateFinObj = $dateFin ? new \DateTime($dateFin) : null;

        $bilan = $consumableRepository->getBilanGlobal($dateDebutObj, $dateFinObj);

        return $apiResponse->success(
            $bilan,
            Response::HTTP_OK,
            'Bilan global récupéré avec succès.'
        );
    }
}
