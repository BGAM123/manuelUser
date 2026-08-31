<?php

namespace App\Controller\Groupes;

use App\Repository\GroupeRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/groupes/{groupeId}/permissions')]
#[OA\Tag(name: 'Groupes')]
final class ListGroupePermissionsController extends AbstractController
{
    #[Route('', name: 'app_groupe_list_permissions', methods: ['GET'])]
    #[OA\Get(
        path: '/groupes/{groupeId}/permissions',
        summary: 'Lister les permissions d un groupe',
        description: 'Retourne la liste des permissions associées au groupe {groupeId}.'
    )]
    #[OA\Parameter(name: 'groupeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Permissions du groupe retournées',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Permissions du groupe retournées avec succès.',
                'data' => [
                    ['id' => 2, 'nom' => 'gerer_biens', 'description' => 'Gérer les biens', 'is_active' => true]
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Groupe introuvable.', 'data' => null]))]
    public function __invoke(
        int $groupeId,
        GroupeRepository $groupeRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $groupe = $groupeRepository->getGroupeById($groupeId);
        if (!$groupe || $groupe->isDelete()) {
            return $apiResponse->error('Groupe introuvable.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($groupe->getPermissions(), 'json', ['groups' => ['permission:list']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Permissions du groupe retournées avec succès.');
    }
}
