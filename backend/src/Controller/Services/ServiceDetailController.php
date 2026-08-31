<?php

namespace App\Controller\Services;

use App\Repository\ServiceRepository;
use App\Service\ApiResponseFactory;
use App\Entity\Service;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/services')]
#[OA\Tag(name: 'Services')]
final class ServiceDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_service_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/services/{id}',
        summary: 'Détails d\'un service',
        description: 'Retourne les informations détaillées d\'un service, y compris son parent et ses types d\'organigramme.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 5)]
    #[OA\Response(
        response: 200,
        description: 'Success - détails du service retournés',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Service detail returned successfully.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Service detail returned successfully.',
                'data' => [
                    'id' => 5,
                    'nom' => 'Comptabilité',
                    'sigle' => 'COMP',
                    'code' => 'COMP',
                    'type_service' => 'Poste',
                    'ordre' => 1,
                    'is_active' => true,
                    'parent_id' => [
                        'id' => 4,
                        'nom' => 'Comptabilité'
                    ],
                    'typeOrganigrammes' => [
                        ['id' => 1, 'nom' => 'Organigramme Administratif'],
                        ['id' => 2, 'nom' => 'Organigramme Fonctionnel']
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

        // Transformer le service avec le normalizer personnalisé
        $serviceData = $serializer->normalize($service, 'json', ['_service_flat' => true]);
        return $apiResponse->success($serviceData, Response::HTTP_OK, 'Service detail returned successfully.');
    }
}
