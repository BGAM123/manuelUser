<?php

namespace App\Controller\Groupes;

use App\Entity\Groupe;
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
final class UpdateGroupeController extends AbstractController
{
    #[Route('/{id}', name: 'app_groupe_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(path: '/groupes/{id}', summary: 'Remplacer un groupe')]
    #[OA\Patch(path: '/groupes/{id}', summary: 'Mettre à jour partiellement un groupe')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'SECRETAIRE'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'is_active', type: 'boolean'),
                new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'integer'), description: 'Remplace intégralement la liste des permissions du groupe')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Groupe mis à jour avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Groupe mis à jour avec succès.',
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
        response: 404,
        description: 'Not Found - Groupe non trouvé',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    #[OA\Response(
        response: 409,
        description: 'Conflict - Ce nom de groupe existe déjà',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 409, 'message' => 'Ce nom de groupe est déjà utilisé.', 'data' => null])
    )]
    public function __invoke(
        Groupe $groupe,
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

        if (!empty($payload['nom']) && $groupeRepository->existsByNom((string) $payload['nom'], $groupe->getId())) {
            return $apiResponse->error('Ce nom de groupe est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        try {
            $groupeRepository->applyPayloadToGroupe($groupe, $payload);
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
        return $apiResponse->success($data, Response::HTTP_OK, 'Groupe mis à jour avec succès.');
    }
}