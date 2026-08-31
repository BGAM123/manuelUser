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
final class UpdateTypeOrganigrammeController extends AbstractController
{
    #[Route('/{id}', name: 'app_type_organigramme_update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/type-organigrammes/{id}',
        summary: 'Modifier un type d\'organigramme',
        description: 'Modifie les données d\'un type d\'organigramme existant.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        description: 'Payload pour modifier un type d\'organigramme',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Organigramme Administratif'),
                new OA\Property(property: 'description', type: 'string', example: 'Organisation administrative centrale')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Type d\'organigramme modifié avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Type d\'organigramme updated successfully.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Type d\'organigramme updated successfully.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Organigramme Administratif',
                    'description' => 'Organisation administrative centrale'
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found - Type d\'organigramme non trouvé')]
    #[OA\Response(response: 400, description: 'Bad Request - Erreur de validation')]
    public function __invoke(
        int $id,
        Request $request,
        TypeOrganigrammeRepository $typeOrganigrammeRepository,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        $type = $typeOrganigrammeRepository->find($id);
        if (!$type || $type->isIsDelete()) {
            return $apiResponse->error("Le type d'organigramme demandé est introuvable.", Response::HTTP_NOT_FOUND);
        }

        if (isset($payload['nom'])) {
            $type->setNom($payload['nom']);
        }
        if (isset($payload['description'])) {
            $type->setDescription($payload['description']);
        }

        $errors = $validator->validate($type);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getMessage();
            }
            return $apiResponse->error(implode(' ', $messages), Response::HTTP_BAD_REQUEST);
        }

        $typeOrganigrammeRepository->getEntityManager()->flush();

        return $apiResponse->success(
            [
                'id' => $type->getId(),
                'nom' => $type->getNom(),
                'description' => $type->getDescription()
            ],
            Response::HTTP_OK,
            'Type d\'organigramme updated successfully.'
        );
    }
}
