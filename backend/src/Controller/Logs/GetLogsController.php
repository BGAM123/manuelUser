<?php

namespace App\Controller\Logs;

use App\Service\ApiResponseFactory;
use App\Service\LogService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/logs', name: 'app_logs', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Logs')]
final class GetLogsController extends AbstractController
{
    #[OA\Get(
        path: '/logs',
        summary: 'Consulter les actions API tracées',
        description: 'Lit les événements API_ACTION du fichier de log Symfony de l’environnement courant.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))]
    #[OA\Parameter(name: 'user_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'username', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'method', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'action', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'module', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'resource_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'status_code', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'ip', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'date_start', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'date_end', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Response(response: 200, description: 'Logs récupérés avec succès.')]
    #[OA\Response(response: 401, description: 'Authentification requise.')]
    #[OA\Response(response: 403, description: 'Accès réservé aux utilisateurs autorisés.')]
    public function __invoke(Request $request, LogService $logService, ApiResponseFactory $apiResponse): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $dateStart = $request->query->get('date_start');
        $dateEnd = $request->query->get('date_end');

        if ($page < 1 || $limit < 1 || $dateStart !== null && !$this->isDate($dateStart) || $dateEnd !== null && !$this->isDate($dateEnd)) {
            return $apiResponse->error('Les paramètres de pagination ou de date sont invalides.', Response::HTTP_BAD_REQUEST);
        }

        $filters = array_filter([
            'user_id' => $request->query->get('user_id'),
            'username' => $request->query->get('username'),
            'method' => $request->query->get('method'),
            'action' => $request->query->get('action'),
            'module' => $request->query->get('module'),
            'resource_id' => $request->query->get('resource_id'),
            'status_code' => $request->query->get('status_code'),
            'ip' => $request->query->get('ip'),
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return $apiResponse->success(
            $logService->search($page, $limit, $filters),
            Response::HTTP_OK,
            'Logs récupérés avec succès.'
        );
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
