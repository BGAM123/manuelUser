<?php

namespace App\Controller\Services;

use App\Repository\ServiceRepository;
use App\Service\ApiResponseFactory;
use App\Entity\Service;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/services')]
#[OA\Tag(name: 'Services')]
final class ServiceChildrenController extends AbstractController
{
    #[Route('/{id}/children', name: 'app_service_children', methods: ['GET'])]
    #[OA\Get(
        path: '/services/{id}/children',
        summary: 'Récupérer un service et ses enfants directs',
        description: 'Retourne un service donné ainsi que la liste de ses enfants directs (premier niveau uniquement).'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 2)]
    #[OA\Response(
        response: 200,
        description: 'Success - Service et enfants retournés',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Service with direct children returned successfully.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Service with direct children returned successfully.',
                'data' => [
                    'service' => [
                        'id' => 2,
                        'nom' => 'Informatique',
                        'sigle' => 'IT',
                        'code' => 'IT',
                        'type_service' => 'Service',
                        'ordre' => 1,
                        'is_active' => true,
                        'parent_id' => null
                    ],
                    'children' => [
                        [
                            'id' => 3,
                            'nom' => 'Support',
                            'sigle' => 'SUP',
                            'code' => 'SUP',
                            'type_service' => 'Poste',
                            'ordre' => 1,
                            'is_active' => true,
                            'parent_id' => 2
                        ]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found - Service non trouvé')]
    public function __invoke(
        int $id,
        ServiceRepository $serviceRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $service = $serviceRepository->getServiceById($id);
        if (!$service) {
            return $apiResponse->error('Le service demandé est introuvable.', Response::HTTP_NOT_FOUND);
        }

        $children = $serviceRepository->getDirectChildren($id);

        $result = [
            'service' => json_decode($serializer->serialize($service, 'json', ['groups' => ['service:detail']]), true),
            'children' => json_decode($serializer->serialize($children, 'json', ['groups' => ['service:detail']]), true),
        ];

        return $apiResponse->success($result, Response::HTTP_OK, 'Service with direct children returned successfully.');
    }
}
