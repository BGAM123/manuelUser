<?php

namespace App\Controller\Groupes;

use App\Entity\Groupe;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/groupes')]
#[OA\Tag(name: 'Groupes')]
final class GroupeDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_groupe_detail', methods: ['GET'])]
    #[OA\Get(path: '/groupes/{id}', summary: 'Détails d\'un groupe (avec ses permissions)', description: 'Retourne les détails d un groupe spécifique, incluant ses permissions associées.')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - détails du groupe retournés',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Détails du groupe retournés avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'SECRETAIRE',
                    'description' => 'Socle commun de permissions',
                    'is_active' => true,
                    'permissions' => [
                        ['id' => 2, 'nom' => 'gerer_biens', 'description' => 'Gérer les biens du patrimoine', 'is_active' => true]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found - Groupe non trouvé')]
    public function __invoke(
        Groupe $groupe,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($groupe->isDelete()) {
            return $apiResponse->error('Groupe non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($groupe, 'json', ['groups' => ['groupe:detail']]), true);

        $users = [];
        foreach ($groupe->getUsers() as $user) {
            if ($user->isDelete()) {
                continue;
            }

            $users[] = [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'matricule' => $user->getMatricule(),
                'is_active' => $user->isActive(),
            ];
        }

        $data['users'] = $users;

        return $apiResponse->success($data, Response::HTTP_OK, 'Détails du groupe retournés avec succès.');
    }
}