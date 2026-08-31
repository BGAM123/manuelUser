<?php

namespace App\Controller\Users;

use App\Entity\User;
use App\Service\ApiResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Users')]
final class TwoFactorController extends AbstractController
{
    #[Route('/users/{id}/two-factor', name: 'app_user_two_factor', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/users/{id}/two-factor',
        summary: 'Activer ou désactiver la double authentification',
        description: 'Active ou désactive la double authentification (2FA) pour un utilisateur.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        description: 'Payload pour modifier le statut 2FA',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['twoFactorEnabled'],
            properties: [
                new OA\Property(property: 'twoFactorEnabled', type: 'boolean', example: true)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Double authentification mise à jour',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Double authentification mise à jour avec succès.'),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'twoFactorEnabled', type: 'boolean', example: true)
                ])
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null]))]
    public function updateTwoFactor(
        User $user,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['twoFactorEnabled'])) {
            return $apiResponse->error('twoFactorEnabled field is required.', Response::HTTP_BAD_REQUEST);
        }

        // Valider que c'est un booléen
        if (!is_bool($payload['twoFactorEnabled'])) {
            return $apiResponse->error('twoFactorEnabled must be a boolean.', Response::HTTP_BAD_REQUEST);
        }

        // Mettre à jour le statut 2FA
        $user->setTwoFactorEnabled($payload['twoFactorEnabled']);

        // Si on désactive la 2FA, nettoyer les codes OTP existants
        if (!$payload['twoFactorEnabled']) {
            $user->setOtpCode(null);
            $user->setOtpExpiresAt(null);
        }

        // Valider l'entité
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return $apiResponse->error(
                implode(', ', $errorMessages),
                Response::HTTP_BAD_REQUEST
            );
        }

        // Persister et flusher
        $entityManager->persist($user);
        $entityManager->flush();

        return $apiResponse->success(
            [
                'twoFactorEnabled' => $user->isTwoFactorEnabled(),
            ],
            Response::HTTP_OK,
            'Double authentification mise à jour avec succès.'
        );
    }
}
