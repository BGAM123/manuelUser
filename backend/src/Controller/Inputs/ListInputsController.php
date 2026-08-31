<?php

namespace App\Controller\Inputs;

use App\Service\ApiResponseFactory;
use App\Service\InputService;
use App\Service\InputResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inputs')]
#[OA\Tag(name: 'Inputs')]
final class ListInputsController extends AbstractController
{
    #[Route('', name: 'app_input_list', methods: ['GET'])]
    #[OA\Get(
        path: '/inputs',
        summary: 'Lister les valeurs de champs',
        description: 'Retourne la liste paginée des valeurs de champs (inputs).'
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 20)
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
        description: 'Recherche par valeur ou nom de champ'
    )]
    #[OA\Parameter(
        name: 'champ_id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par ID de champ'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des valeurs récupérée avec succès'
    )]
    public function __invoke(
        Request $request,
        InputService $inputService,
        InputResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // ✅ LOG 1 : Début de la méthode
        error_log('=== ListInputsController::__invoke START ===');
        
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));
        $search = $request->query->get('search');
        $champId = $request->query->get('champ_id') ? (int) $request->query->get('champ_id') : null;

        // ✅ LOG 2 : Paramètres reçus
        error_log('Params - page: ' . $page . ', limit: ' . $limit . ', search: ' . $search . ', champId: ' . $champId);

        try {
            if ($champId) {
                $result = $inputService->listByChamp($champId, $page, $limit);
            } else {
                $result = $inputService->list($page, $limit, $search);
            }

            // ✅ LOG 3 : Résultat retourné par le service
            error_log('Result keys: ' . implode(', ', array_keys($result)));
            error_log('Result items count: ' . count($result['items'] ?? []));
            error_log('Result total: ' . ($result['total'] ?? 0));

            $data = [
                'items' => $responseBuilder->buildList($result['items'] ?? []),
                'total' => $result['total'] ?? 0,
                'page' => $result['page'] ?? $page,
                'limit' => $result['limit'] ?? $limit,
                'pages' => $result['pages'] ?? 0,
            ];

            // ✅ LOG 4 : Données finales
            error_log('Data keys: ' . implode(', ', array_keys($data)));

            return $apiResponse->success(
                $data,
                Response::HTTP_OK,
                'Valeurs récupérées avec succès.'
            );
        } catch (\Exception $e) {
            // ✅ LOG 5 : Erreur
            error_log('❌ ERROR: ' . $e->getMessage());
            error_log('❌ TRACE: ' . $e->getTraceAsString());
            return $apiResponse->error(
                'Erreur lors de la récupération des valeurs.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}