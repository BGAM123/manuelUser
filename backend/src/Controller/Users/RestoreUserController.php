<?php

namespace App\Controller\Users;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users/restore', name: 'app_user_restore', methods: ['POST'])]
#[OA\Tag(name: 'Users')]
final class RestoreUserController extends AbstractController
{
    #[OA\Post(
        path: '/users/restore',
        summary: 'Restaurer des utilisateurs supprimés',
        description: 'Restaure un ou plusieurs utilisateurs précédemment supprimés logiquement (isDelete = true). Les IDs sont fournis dans le corps de la requête sous forme de tableau. Si un utilisateur n\'existe pas ou n\'est pas supprimé, il est ignoré.'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'ids',
                    type: 'array',
                    items: new OA\Items(type: 'integer'),
                    description: 'Liste des IDs des utilisateurs à restaurer'
                )
            ],
            example: ['ids' => [1, 2, 5]]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Utilisateurs restaurés avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => '2 utilisateur(s) restauré(s) avec succès.',
                'data' => [
                    'restored' => [1, 2],
                    'skipped' => [5],
                    'skipped_reasons' => [5 => 'Utilisateur non supprimé']
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'La validation a échoué.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        Request $request,
        UserRepository $userRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
            return $apiResponse->error('Le paramètre "ids" est requis et doit être un tableau non vide.', Response::HTTP_BAD_REQUEST);
        }

        $ids = $data['ids'];
        $restored = [];
        $skipped = [];
        $skippedReasons = [];

        foreach ($ids as $id) {
            if (!is_int($id)) {
                $skipped[] = $id;
                $skippedReasons[$id] = 'ID invalide';
                continue;
            }

            $user = $userRepository->find($id);

            if (!$user) {
                $skipped[] = $id;
                $skippedReasons[$id] = 'Utilisateur introuvable';
                continue;
            }

            if (!$user->isDelete()) {
                $skipped[] = $id;
                $skippedReasons[$id] = 'Utilisateur non supprimé';
                continue;
            }

            $user->setIsDelete(false);
            $userRepository->save($user);
            $restored[] = $id;
        }

        $message = count($restored) . ' utilisateur(s) restauré(s) avec succès.';
        if (count($skipped) > 0) {
            $message .= ' ' . count($skipped) . ' utilisateur(s) ignoré(s).';
        }

        return $apiResponse->success(
            [
                'restored' => $restored,
                'skipped' => $skipped,
                'skipped_reasons' => $skippedReasons
            ],
            Response::HTTP_OK,
            $message
        );
    }
}
