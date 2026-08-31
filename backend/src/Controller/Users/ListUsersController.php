<?php

namespace App\Controller\Users;

use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use App\Service\PaginationFactory;
use App\Service\UserMatriculesService;
use App\Serializer\PaginatedCollectionNormalizer;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/users', name: 'app_user_list', methods: ['GET'])]
#[OA\Tag(name: 'Users')]
final class ListUsersController extends AbstractController
{
    public function listMatricules(
        Request $request,
        UserMatriculesService $userMatriculesService,
        ApiResponseFactory $apiResponse
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 10)));
        $search = $request->query->get('search');

        return $apiResponse->success(
            $userMatriculesService->getPaginated($page, $limit, $search),
            Response::HTTP_OK,
            'Matricules récupérés avec succès.'
        );
    }

    #[OA\Get(
        path: '/users',
        summary: 'Lister tous les utilisateurs',
        description: 'Retourne la liste paginée de tous les utilisateurs.'
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'le numéro de page pour la navigation dans le framework',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Nombre maximum d utilisateurs retournés par page',
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Parameter(
        name: 'is_active',
        in: 'query',
        description: 'Filtre les utilisateurs actifs (true) ou désactivés (false).',
        schema: new OA\Schema(type: 'boolean')
    )]
    #[OA\Parameter(
        name: 'is_dlet',
        in: 'query',
        description: 'Filtre les utilisateurs supprimés (true) ou non supprimés (false). Par défaut: false.',
        schema: new OA\Schema(type: 'boolean')
    )]
    #[OA\Parameter(
        name: 'service_id',
        in: 'query',
        description: 'Filtrer les utilisateurs par structure/service.',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'twoFactorEnabled',
        in: 'query',
        description: 'Filtrer les utilisateurs par statut de double authentification (true/false).',
        schema: new OA\Schema(type: 'boolean')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - liste paginée des utilisateurs retournée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Users list returned successfully.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Users list returned successfully.',
                'data' => [
                    'meta' => [
                        'current_page' => 1,
                        'limit' => 10,
                        'total_items' => 42,
                        'total_pages' => 5
                    ],
                    'data' => [
                        [
                            'id' => 1,
                            'firstName' => 'alex',
                            'lastName' => 'nengue',
                            'email' => 'nengue382@gmail.com',
                            'matricule' => 'MAT-001',
                            'cni' => '123456789',
                            'is_active' => true,
                            'is_delete' => false,
                            'twoFactorEnabled' => true,
                            'createdAt' => '27-07-2026 14:30:45',
                            'service' => [
                                'id' => 2,
                                'nom' => 'Comptabilité',
                                'sigle' => 'COMP',
                                'type_service' => 'poste',
                                'ordre' => 1,
                                'is_active' => true
                            ],
                            'assignedRoles' => [
                                [
                                    'id' => 3,
                                    'nom' => 'Responsable Structure'
                                ]
                            ]
                        ],
                        [
                            'id' => 2,
                            'firstName' => 'marie',
                            'lastName' => 'dupont',
                            'email' => 'marie.dupont@example.com',
                            'matricule' => 'MAT-002',
                            'is_active' => true,
                            'twoFactorEnabled' => false,
                            'createdAt' => '26-07-2026 09:15:30',
                            'service' => [
                                'id' => 3,
                                'nom' => 'Ressources Humaines',
                                'sigle' => 'RH',
                                'type_service' => 'direction',
                                'ordre' => 2,
                                'is_active' => true
                            ],
                            'assignedRoles' => [
                                [
                                    'id' => 2,
                                    'nom' => 'Gestionnaire'
                                ]
                            ]
                            
                        ]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    public function __invoke(
        Request $request,
        UserRepository $userRepository,
        SerializerInterface $serializer,
        PaginationFactory $paginationFactory,
        ApiResponseFactory $apiResponse
    ): Response {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $isActiveParam = $request->query->get('is_active');
        $isDletParam = $request->query->get('is_dlet');
        $serviceId = $request->query->has('service_id') ? $request->query->getInt('service_id') : null;
        $twoFactorEnabledParam = $request->query->get('twoFactorEnabled');

        $limit = $limit > 100 ? 100 : $limit;
        $page = $page < 1 ? 1 : $page;
        $limit = $limit < 1 ? 10 : $limit;

        $isActive = null;

        // ✅ Filtre is_dlet (par défaut: false si non spécifié)
        $isDelete = false; // Valeur par défaut : ne pas afficher les utilisateurs supprimés
        if (null !== $isDletParam) {
            $isDelete = filter_var($isDletParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            // Si le paramètre est invalide, on garde la valeur par défaut
            if (null === $isDelete) {
                $isDelete = false;
            }
        }

        if (null !== $isActiveParam) {
            $isActive = filter_var($isActiveParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $twoFactorEnabled = null;
        if (null !== $twoFactorEnabledParam) {
            $twoFactorEnabled = filter_var($twoFactorEnabledParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $users = $userRepository->findPaginatedUsers($page, $limit, $isActive, $serviceId, $twoFactorEnabled, $isDelete);
        $totalItems = $userRepository->countAllUsers($isActive, $serviceId, $twoFactorEnabled, $isDelete);

        // Transformer les utilisateurs avec le normalizer personnalisé
        $serializedUsers = [];
        $normalizer = new \App\Serializer\UserListNormalizer();
        foreach ($users as $user) {
            $serializedUsers[] = $normalizer->normalize($user, 'json', ['_user_list' => true]);
        }

        // Construire la réponse paginée uniforme
        $paginatedData = $paginationFactory->createPaginatedResponse($serializedUsers, $page, $limit, $totalItems);

        return $apiResponse->success($paginatedData, Response::HTTP_OK, 'Users list returned successfully.');
    }
}
