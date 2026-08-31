<?php

namespace App\Controller\Users;

use App\Entity\User;
use App\Repository\GroupeRepository;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/users/{id}/groupes')]
#[OA\Tag(name: 'Users')]
final class UpdateUserGroupesController extends AbstractController
{
    #[Route('', name: 'app_user_groupes_update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/users/{id}/groupes',
        summary: 'Remplacer la liste des groupes d un utilisateur',
        description: 'Remplace intégralement l ensemble des groupes de l utilisateur par la liste fournie (contrairement à AssignGroupeToUserController qui ajoute un seul groupe).'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['group_ids'],
            properties: [
                new OA\Property(property: 'group_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 3])
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Success - Groupes utilisateur mis à jour')]
    #[OA\Response(response: 400, description: 'Bad Request')]
    #[OA\Response(response: 404, description: 'Not Found - Utilisateur ou groupe introuvable')]
    public function __invoke(
        User $user,
        Request $request,
        SerializerInterface $serializer,
        UserRepository $userRepository,
        GroupeRepository $groupeRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['group_ids']) || !is_array($payload['group_ids'])) {
            return $apiResponse->error('group_ids is required and must be an array.', Response::HTTP_BAD_REQUEST);
        }

        $groupeIds = array_unique(array_map('intval', $payload['group_ids']));

        $groupes = [];
        foreach ($groupeIds as $groupeId) {
            $groupe = $groupeRepository->getGroupeById($groupeId);
            if (!$groupe || $groupe->isDelete()) {
                return $apiResponse->error(sprintf('Groupe %d introuvable.', $groupeId), Response::HTTP_NOT_FOUND);
            }
            $groupes[] = $groupe;
        }

        foreach ($user->getAssignedGroupes()->toArray() as $existing) {
            $user->removeAssignedGroupe($existing);
        }
        foreach ($groupes as $groupe) {
            $user->addAssignedGroupe($groupe);
        }

        $userRepository->save($user);

        $data = json_decode($serializer->serialize($user, 'json', ['groups' => ['user:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Groupes utilisateur mis à jour avec succès.');
    }
}