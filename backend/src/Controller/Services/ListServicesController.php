<?php

namespace App\Controller\Services;

use App\Repository\ServiceRepository;
use App\Entity\Service;
use App\Service\ApiResponseFactory;
use App\Service\ServiceHierarchyBuilder;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/services')]
#[OA\Tag(name: 'Services')]
final class ListServicesController extends AbstractController
{
    #[Route('', name: 'app_service_list', methods: ['GET'])]
    #[OA\Get(
        path: '/services',
        summary: 'Lister les services',
        description: 'Retourne la liste paginée des services avec filtres.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    // #[OA\Parameter(name: 'parent_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string', description: 'Recherche textuelle sur le nom, le sigle ou le code du service'))]
    #[OA\Parameter(name: 'parent_id', in: 'query', schema: new OA\Schema(type: 'string', description: 'Filtrer par parent ID(s) - séparés par des virgules pour multiples IDs (ex: 1,2,3)'))]
    #[OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'type_organigramme_id', in: 'query', schema: new OA\Schema(type: 'integer', description: 'Filtrer par type d\'organigramme'))]
    #[OA\Response(
        response: 200,
        description: 'Success - liste paginée des services (liste plate sans hiérarchie)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Services list returned successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object'
                )
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Services list returned successfully.',
                'data' => [
                    'meta' => [
                        'current_page' => 1,
                        'limit' => 10,
                        'total_items' => 1,
                        'total_pages' => 1,
                    ],
                    'data' => [
                        [
                            'id' => 5,
                            'nom' => 'Comptabilité',
                            'sigle' => 'COMP',
                            'code' => 'COMP',
                            'type_service' => 'Poste',
                            'ordre' => 1,
                            'is_active' => true,
                            'parent_id' => [
                                'id' => 2,
                                'nom' => 'Comptabilité'
                            ],
                            'typeOrganigrammes' => [
                                ['id' => 1, 'nom' => 'Organigramme Administratif'],
                                ['id' => 2, 'nom' => 'Organigramme Fonctionnel']
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
        ServiceRepository $serviceRepository,
        ServiceHierarchyBuilder $hierarchyBuilder,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);

        $parentIdParam = $request->query->get('parent_id');
        $isActiveParam = $request->query->get('is_active');
        $typeOrganigrammeIdParam = $request->query->get('type_organigramme_id');
        $searchParam = $request->query->get('search');

        $isActive = null;
        if (null !== $isActiveParam) {
            $isActive = filter_var($isActiveParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        // Traiter parent_id pour accepter plusieurs IDs séparés par des virgules
        $parentIds = null;
        if (null !== $parentIdParam && $parentIdParam !== '') {
            $parts = array_filter(array_map('trim', explode(',', $parentIdParam)));
            if (!empty($parts)) {
                $parentIds = array_filter(array_map('intval', $parts), function($id) {
                    return $id > 0;
                });
                $parentIds = !empty($parentIds) ? array_values($parentIds) : null;
            }
        }
        $typeOrganigrammeId = null;
        if (null !== $typeOrganigrammeIdParam) {
            $typeOrganigrammeId = (int) $typeOrganigrammeIdParam;
        }

        if ($typeOrganigrammeId) {
    $services = $serviceRepository->findByTypeOrganigramme($typeOrganigrammeId, $page, $limit, $isActive, $parentIds, $searchParam);
    $total = $serviceRepository->countByTypeOrganigramme($typeOrganigrammeId, $isActive, $parentIds, $searchParam);
} else {
    $services = $serviceRepository->findPaginatedServices($page, $limit, $isActive, $parentIds, $searchParam);
    $total = $serviceRepository->countAllServices($isActive, $parentIds, $searchParam);
}

        // Transformer les services avec le normalizer personnalisé
        $serializedServices = [];
        foreach ($services as $service) {
            $serializedServices[] = $serializer->normalize($service, 'json', ['_service_flat' => true]);
        }

        $payload = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
            'data' => $serializedServices,
        ];

        return $apiResponse->success($payload, Response::HTTP_OK, 'Services list returned successfully.');
    }
}
