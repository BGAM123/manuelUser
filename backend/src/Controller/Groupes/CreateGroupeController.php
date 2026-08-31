<?php

namespace App\Controller\Groupes;

use App\Repository\GroupeRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/groupes')]
#[OA\Tag(name: 'Groupes')]
final class CreateGroupeController extends AbstractController
{
    #[Route('', name: 'app_groupe_create', methods: ['POST'])]
    #[OA\Post(path: '/groupes', summary: 'Créer un groupe', description: 'Crée un nouveau groupe avec éventuellement des permissions associées.')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['nom'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'SECRETAIRE'),
                new OA\Property(property: 'description', type: 'string', example: 'Socle commun de permissions'),
                new OA\Property(property: 'is_active', type: 'boolean', example: true),
                new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3])
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Groupe créé avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Groupe créé avec succès.',
                'data' => ['id' => 1, 'nom' => 'SECRETAIRE', 'description' => 'Socle commun de permissions', 'is_active' => true]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request - Payload invalide',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    #[OA\Response(
        response: 409,
        description: 'Conflict - Ce nom de groupe existe déjà',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 409, 'message' => 'Ce nom de groupe est déjà utilisé.', 'data' => null])
    )]
    public function __invoke(
        Request $request,
        GroupeRepository $groupeRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (!empty($payload['nom']) && $groupeRepository->existsByNom((string) $payload['nom'])) {
            return $apiResponse->error('Ce nom de groupe est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        try {
            $groupe = $groupeRepository->buildGroupeFromPayload($payload);
        } catch (\InvalidArgumentException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($groupe);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $groupeRepository->save($groupe);

        $data = json_decode($serializer->serialize($groupe, 'json', ['groups' => ['groupe:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_CREATED, 'Groupe créé avec succès.');
    }
}