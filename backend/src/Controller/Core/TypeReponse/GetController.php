<?php

namespace App\Controller\Core\TypeReponse;

use App\Repository\Core\TypeReponseRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeReponse")]
class GetController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private TypeReponseRepository $typeReponseRepository,
    ) {}

    #[Route('/core/type-reponse/{id}', name: 'app_core_type_reponse_get', methods: ['GET'])]
    #[OA\Get(
        path: '/core/type-reponse/{id}',
        summary: 'Récupérer un type de réponse par son ID',
        tags: ['TypeReponse'],
        description: "Récupère les détails d'un type de réponse spécifique avec le nombre de réponses associées.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant du type de réponse', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Type de réponse trouvée.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Accusé de réception'),
                        new OA\Property(property: 'description', type: 'string', example: 'Confirmation de réception d\'un courrier'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-11-04T10:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-11-04T10:30:00+00:00'),
                        new OA\Property(property: 'nombreReponses', type: 'integer', example: 15, description: 'Nombre de réponses utilisant ce type'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Type de réponse non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function data(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTypeReponse');

        $typeReponse = $this->typeReponseRepository->find($id);

        if (!$typeReponse || $typeReponse->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Type de réponse non trouvée.'], 404);
        }

        // Enrichir avec le nombre de réponses
        $responseData = [
            'id' => $typeReponse->getId(),
            'nom' => $typeReponse->getNom(),
            'description' => $typeReponse->getDescription(),
            'isActive' => $typeReponse->isActive(),
            'typeParent' => $typeReponse->getTypeParent() ? [
                'id' => $typeReponse->getTypeParent()->getId(),
                'nom' => $typeReponse->getTypeParent()->getNom()
            ] : null,
            'createdAt' => $typeReponse->getCreatedAt()?->format('c'),
            'updatedAt' => $typeReponse->getUpdatedAt()?->format('c'),
            'nombreReponses' => $typeReponse->getReponses()->count(),
        ];

        return $this->json($responseData, 200);
    }
}
