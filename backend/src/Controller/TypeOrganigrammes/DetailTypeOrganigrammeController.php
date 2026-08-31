<?php

namespace App\Controller\TypeOrganigrammes;

use App\Entity\TypeOrganigramme;
use App\Repository\TypeOrganigrammeRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/type-organigrammes')]
#[OA\Tag(name: 'TypeOrganigrammes')]
final class DetailTypeOrganigrammeController extends AbstractController
{
    #[Route('/{id}', name: 'app_type_organigramme_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/type-organigrammes/{id}',
        summary: 'Récupérer le détail d\'un type d\'organigramme',
        description: 'Retourne les détails complets d\'un type d\'organigramme.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Détails du type d\'organigramme',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Type d\'organigramme retrieved successfully.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Type d\'organigramme retrieved successfully.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Organigramme Administratif',
                    'description' => 'Organisation administrative centrale'
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found - Type d\'organigramme non trouvé')]
    public function __invoke(
        int $id,
        TypeOrganigrammeRepository $typeOrganigrammeRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $type = $typeOrganigrammeRepository->find($id);

        if (!$type || $type->isIsDelete()) {
            return $apiResponse->error("Le type d'organigramme demandé est introuvable.", Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            [
                'id' => $type->getId(),
                'nom' => $type->getNom(),
                'description' => $type->getDescription()
            ],
            Response::HTTP_OK,
            'Type d\'organigramme retrieved successfully.'
        );
    }
}
