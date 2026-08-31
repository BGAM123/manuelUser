<?php

namespace App\Controller\TypeOrganigrammes;

use App\Entity\TypeOrganigramme;
use App\Repository\TypeOrganigrammeRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/type-organigrammes')]
#[OA\Tag(name: 'TypeOrganigrammes')]
final class CreateTypeOrganigrammeController extends AbstractController
{
    #[Route('', name: 'app_type_organigramme_create', methods: ['POST'])]
    #[OA\Post(
        path: '/type-organigrammes',
        summary: 'Créer un type d\'organigramme',
        description: 'Crée un nouveau type d\'organigramme.'
    )]
    #[OA\RequestBody(
        description: 'Payload pour créer un type d\'organigramme',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['nom'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Organigramme Administratif'),
                new OA\Property(property: 'description', type: 'string', example: 'Organisation administrative centrale')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Type d\'organigramme créé avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 201),
                new OA\Property(property: 'message', type: 'string', example: 'Type d\'organigramme créé avec succès.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Type d\'organigramme créé avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Organigramme Administratif',
                    'description' => 'Organisation administrative centrale'
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Erreur de validation')]
    public function __invoke(
        Request $request,
        TypeOrganigrammeRepository $typeOrganigrammeRepository,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        $type = new TypeOrganigramme();
        $type->setNom($payload['nom'] ?? null);
        $type->setDescription($payload['description'] ?? null);

        $errors = $validator->validate($type);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getMessage();
            }
            return $apiResponse->error(implode(' ', $messages), Response::HTTP_BAD_REQUEST);
        }

        $typeOrganigrammeRepository->getEntityManager()->persist($type);
        $typeOrganigrammeRepository->getEntityManager()->flush();

        return $apiResponse->success(
            [
                'id' => $type->getId(),
                'nom' => $type->getNom(),
                'description' => $type->getDescription()
            ],
            Response::HTTP_CREATED,
            'Type d\'organigramme créé avec succès.'
        );
    }
}
