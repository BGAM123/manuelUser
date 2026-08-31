<?php

namespace App\Controller\Users;

use App\Service\ApiResponseFactory;
use App\Service\UserMatriculesService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users/matricules', name: 'app_user_matricules', methods: ['GET'], priority: 10)]
#[OA\Tag(name: 'Users')]
final class UserMatriculesController extends AbstractController
{
    #[OA\Get(
        path: '/users/matricules',
        summary: 'Lister les matricules des utilisateurs',
        description: 'Retourne une liste paginée des matricules des utilisateurs avec possibilité de recherche.'
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'Numéro de page (défaut: 1)',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Nombre d\'éléments par page (défaut: 10)',
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        description: 'Terme de recherche dans les matricules (optionnel)',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Liste des matricules',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Matricules récupérés avec succès.'),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'matricule', type: 'string', example: 'MAT-001')
                    ])),
                    new OA\Property(property: 'pagination', type: 'object', properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 25),
                        new OA\Property(property: 'pages', type: 'integer', example: 3)
                    ])
                ])
            ]
        )
    )]
    public function __invoke(
        Request $request,
        ListUsersController $listUsersController,
        UserMatriculesService $userMatriculesService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $response = $listUsersController->listMatricules($request, $userMatriculesService, $apiResponse);
        $response->headers->set('Deprecation', 'true');

        return $response;
    }
}
