<?php

namespace App\Controller\Core\TypeCourrier;

use App\Entity\Core\TypeCourrier;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeCourrier")]
class GetController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/type-courrier/{id<([1-9][0-9]*)>}', name: 'app_core_type_courrier_get', methods: ['GET'])]
    #[OA\Get(
        path: '/core/type-courrier/{id}',
        summary: 'Consulter un type de courrier',
        description: 'Retourne les détails complets d\'un type de courrier à partir de son ID.',
        tags: ['TypeCourrier'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du type de courrier',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Type de courrier trouvé',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Courrier administratif'),
                        new OA\Property(property: 'type', type: 'string', example: 'Entrant'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                        new OA\Property(property: 'categorie', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 2),
                            new OA\Property(property: 'nom', type: 'string', example: 'Administration')
                        ]),
                        new OA\Property(property: 'parent', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'nom', type: 'string', example: 'Courrier général')
                        ]),
                        new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-02-14T10:00:00')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Type de courrier non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function get(?TypeCourrier $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTypeCourrier');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Type de courrier non trouvé.'], 404);
        }

        $data = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'type' => $entity->getType(),
            'classeCourrier' => $entity->getClasseCourrier(),

            // Regroupe les catÃ©gories (plusieurs)
            'categories' => $entity->getCategories()->map(function($cat) {
                return [
                    'id' => $cat->getId(),
                    'nom' => $cat->getNom(),
                ];
            })->toArray(),

            // Regroupe le parent
            'parent' => $entity->getIdTypeParent() ? [
                'id' => $entity->getIdTypeParent()->getId(),
                'nom' => $entity->getIdTypeParent()->getNom(),
            ] : null,

            'isDelete' => $entity->isDelete(),
            'createdAt' => $entity->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $entity->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        // Log de la consultation
        $this->actionLogger->logView(
            'TypeCourrier',
            $entity->getId(),
            'Consultation d\'un type de courrier',
            $data
        );

        return $this->json($data, 200);
    }
}
