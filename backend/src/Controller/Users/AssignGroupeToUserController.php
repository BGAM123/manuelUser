<?php

namespace App\Controller\Users;

use App\Repository\GroupeRepository;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/users/{userId}/groupes/{groupeId}')]
#[OA\Tag(name: 'Users')]
final class AssignGroupeToUserController extends AbstractController
{
    #[Route('', name: 'app_user_assign_groupe', methods: ['POST'])]
    #[OA\Post(path: '/users/{userId}/groupes/{groupeId}', summary: 'Affecter un utilisateur à un groupe')]
    #[OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'groupeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 404, description: 'Not Found')]
    #[OA\Response(response: 409, description: 'Conflict - Déjà membre de ce groupe')]
    public function __invoke(
        int $userId,
        int $groupeId,
        UserRepository $userRepository,
        GroupeRepository $groupeRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $user = $userRepository->getUserById($userId);
        if (!$user || $user->isDelete()) {
            return $apiResponse->error('Utilisateur non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $groupe = $groupeRepository->getGroupeById($groupeId);
        if (!$groupe || $groupe->isDelete()) {
            return $apiResponse->error('Groupe non trouvé.', Response::HTTP_NOT_FOUND);
        }

        if ($user->getAssignedGroupes()->contains($groupe)) {
            return $apiResponse->error('Cet utilisateur appartient déjà à ce groupe.', Response::HTTP_CONFLICT);
        }

        $user->addAssignedGroupe($groupe);
        $userRepository->save($user);

        $data = json_decode($serializer->serialize($user, 'json', ['groups' => ['user:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_CREATED, 'Utilisateur affecté au groupe avec succès.');
    }
}