<?php

namespace App\Controller\ConsumableTransfers;

use App\Entity\User;
use App\Repository\ConsumableTransferRepository;
use App\Security\ConsumableAccessChecker;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableTransferResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/consumable-transfers')]
#[OA\Tag(name: 'Consomptibles-Transferts')]
final class ListConsumableTransfersController extends AbstractController
{
    #[Route('', name: 'app_consumable_transfer_list', methods: ['GET'])]
    #[OA\Get(
        path: '/consumable-transfers',
        summary: 'Lister les transferts de consomptibles',
        description: 'Liste paginée des transferts de consomptibles.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Recherche sur nom du consomptible, observations')]
    #[OA\Parameter(name: 'consumable_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filtrer par consomptible')]
    #[OA\Parameter(
        name: 'service_id',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par service destinataire. Ignoré si all_services=true et que l\'utilisateur y est autorisé.'
    )]
    #[OA\Parameter(
        name: 'all_services',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'Par défaut, seuls les transferts reçus par le service de l\'utilisateur connecté sont retournés. all_services=true lève cette restriction, réservé aux rôles administrateur.'
    )]
    #[OA\Parameter(
        name: 'statut',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['SORTI', 'TRANSFERE']),
        description: 'Filtrer par statut : SORTI ou TRANSFERE'
    )]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'),
        description: 'false (défaut) = transferts actifs uniquement. true = transferts supprimés (corbeille) uniquement. all = tous.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Transferts retournés avec succès.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 1, 'total_pages' => 1],
                    'data' => [
                        [
                            'id' => 1,
                            'consumable' => ['id' => 1, 'nom' => 'Papier A4'],
                            'serviceDestination' => ['id' => 16, 'nom' => 'Direction des Systèmes d\'Information'],
                            'type' => 'TRANSFERT_DIRECT',
                            'statut' => 'TRANSFERE',
                            'quantite' => '500.00',
                            'dateTransfert' => '2026-08-16',
                            'observations' => 'Transfert pour usage interne',
                            'createdAt' => '2026-08-16 10:00:00',
                            'updatedAt' => '2026-08-16 10:00:00',
                            'accuseReception' => [
                                'effectue' => false,
                                'date' => null,
                                'par' => null,
                                'commentaire' => null,
                            ],
                        ],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'Le statut doit être SORTI ou TRANSFERE.', 'data' => null]))]
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        ConsumableTransferRepository $consumableTransferRepository,
        ConsumableAccessChecker $accessChecker,
        ConsumableTransferResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $search = $request->query->get('search');
        $consumableId = $request->query->has('consumable_id') ? $request->query->getInt('consumable_id') : null;
        $serviceId = $request->query->has('service_id') ? $request->query->getInt('service_id') : null;
        $statut = $request->query->get('statut');

        // Validation du statut
        if ($statut !== null && !in_array($statut, ['SORTI', 'TRANSFERE'], true)) {
            return $apiResponse->error('Le statut doit être SORTI ou TRANSFERE.', Response::HTTP_BAD_REQUEST);
        }

        $isDelete = $request->query->get('is_delete', 'false');

        // Un utilisateur normal ne voit que les transferts reçus par son propre service ;
        // seul un administrateur peut demander all_services=true pour tout voir.
        $allServicesRequested = filter_var($request->query->get('all_services', false), FILTER_VALIDATE_BOOLEAN);
        if (!$allServicesRequested || !$accessChecker->isAdmin($user)) {
            $serviceId = $user->getService()?->getId() ?? -1;
        }

        $items = $consumableTransferRepository->findPaginated($page, $limit, $search, $consumableId, $serviceId, $statut, $isDelete);
        $total = $consumableTransferRepository->countAll($search, $consumableId, $serviceId, $statut, $isDelete);

        $data = $responseBuilder->buildList($items);

        $meta = [
            'current_page' => $page,
            'limit' => $limit,
            'total_items' => $total,
            'total_pages' => (int) ceil($total / max(1, $limit)),
        ];

        return $apiResponse->success([
            'meta' => $meta,
            'data' => $data,
        ], Response::HTTP_OK, 'Transferts retournés avec succès.');
    }
}
