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

#[Route('/users/{userId}/groupes/{groupeId}')]
#[OA\Tag(name: 'Users')]
final class RemoveGroupeFromUserController extends AbstractController
{
    #[Route('', name: 'app_user_remove_groupe', methods: ['DELETE'])]
    #[OA\Delete(path: '/users/{userId}/groupes/{groupeId}', summary: 'Retirer un utilisateur d\'un groupe')]
    #[OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'groupeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 404, description: 'Not Found')]
    public function __invoke(
        int $userId,
        int $groupeId,
        UserRepository $userRepository,
        GroupeRepository $groupeRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $user = $userRepository->getUserById($userId);
        if (!$user || $user->isDelete()) {
            return $apiResponse->error('Utilisateur non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $groupe = $groupeRepository->getGroupeById($groupeId);
        if (!$groupe) {
            return $apiResponse->error('Groupe non trouvé.', Response::HTTP_NOT_FOUND);
        }

        if (!$user->getAssignedGroupes()->contains($groupe)) {
            return $apiResponse->error("Cet utilisateur n'appartient pas à ce groupe.", Response::HTTP_NOT_FOUND);
        }

        $user->removeAssignedGroupe($groupe);
        $userRepository->save($user);

        return $apiResponse->success(null, Response::HTTP_OK, 'Utilisateur retiré du groupe avec succès.');
    }
}