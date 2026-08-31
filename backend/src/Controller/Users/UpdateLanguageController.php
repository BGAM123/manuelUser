<?php

declare(strict_types=1);

namespace App\Controller\Users;

use App\DTO\UpdateLanguageDTO;
use App\Entity\User;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/profile')]
#[OA\Tag(name: 'Profile')]
final class UpdateLanguageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer,
        private readonly ApiResponseFactory $apiResponse
    ) {
    }

    #[Route('/language', name: 'app_profile_update_language', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/profile/language',
        summary: 'Changer la langue de l\'utilisateur connecté',
        description: 'Met à jour la préférence de langue de l\'utilisateur authentifié.'
    )]
    #[OA\RequestBody(
        description: 'Nouvelle langue',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['langue'],
            properties: [
                new OA\Property(
                    property: 'langue',
                    type: 'string',
                    enum: ['fr', 'en', 'es', 'de', 'it'],
                    example: 'en',
                    description: 'Code de langue: fr (Français), en (Anglais), es (Espagnol), de (Allemand), it (Italien)'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Langue mise à jour avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Langue mise à jour avec succès.'
                ),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'langue', type: 'string', example: 'en'),
                        new OA\Property(property: 'email', type: 'string', example: 'john.doe@example.com')
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request - Validation échouée',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'La validation a échoué.',
                'data' => [
                    'langue' => 'Langue non supportée. Choisissez parmi: fr, en, es, de, it'
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized - L\'utilisateur n\'est pas authentifié',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 401,
                'message' => 'Unauthorized.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        #[CurrentUser] User $user,
        Request $request
    ): JsonResponse {
        try {
            // Désérialiser et valider le DTO
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                UpdateLanguageDTO::class,
                'json'
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }
                throw new ValidationFailedException($errorMessages);
            }

            // Mettre à jour la langue
            $user->setLangue($dto->getLangue());
            $this->entityManager->flush();

            // Retourner la réponse
            return $this->apiResponse->success(
                [
                    'langue' => $user->getLangue(),
                    'email' => $user->getEmail()
                ],
                Response::HTTP_OK,
                'Langue mise à jour avec succès.'
            );
        } catch (ValidationFailedException $e) {
            return $this->apiResponse->error(
                'La validation a échoué.',
                Response::HTTP_BAD_REQUEST,
                $e->getErrors()
            );
        } catch (\Exception $e) {
            return $this->apiResponse->error(
                'Une erreur est survenue lors de la mise à jour de la langue.',
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}