<?php

namespace App\Controller\Core\Correspondant;

use App\Entity\Core\Correspondant;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Correspondant")]
class GetController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/correspondant/{id<([1-9][0-9]*)>}', name: 'app_core_correspondant_get', methods: ['GET'])]
    #[OA\Get(
        path: '/core/correspondant/{id}',
        summary: 'Détail d\'un correspondant',
        description: 'Récupère les informations détaillées d\'un correspondant via son identifiant.',
        tags: ['Correspondant'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du correspondant',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détails du correspondant.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont'),
                        new OA\Property(property: 'adresse', type: 'string', example: '10 rue des Lilas, Paris'),
                        new OA\Property(property: 'telephone', type: 'string', example: '0612345678'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                        new OA\Property(property: 'type', type: 'string', example: 'Externe'),
                        new OA\Property(property: 'civilite', type: 'string', example: 'M.'),
                        new OA\Property(property: 'matricule', type: 'string', example: 'EMP-00231'),
                        new OA\Property(
                            property: 'categories',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Particulier')
                                ]
                            ),
                            example: [
                                ['id' => 1, 'nom' => 'Particulier'],
                                ['id' => 3, 'nom' => 'Client'],
                                ['id' => 5, 'nom' => 'VIP']
                            ]
                        ),
                        new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-02-15T11:00:00+00:00')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Correspondant non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function show(?Correspondant $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCorrespondant');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Correspondant non trouvé.'], 404);
        }

        $data = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'adresse' => $entity->getAdresse(),
            'telephone' => $entity->getTelephone(),
            'email' => $entity->getEmail(),
            'type' => $entity->getType(),
            'civilite' => $entity->getCivilite(),
            'matricule' => $entity->getMatricule(),
            'categories' => array_map(fn($cat) => [
                'id' => $cat->getId(),
                'nom' => $cat->getNom()
            ], $entity->getCategories()->toArray()),
            'isDelete' => $entity->isDelete(),
            'createdAt' => $entity->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $entity->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        // âœ… LOG ASYNCHRONE - Consultation d'un correspondant
        $this->actionLogger->logView(
            'Correspondant',
            $entity->getId(),
            'Consultation des détails du correspondant',
            [
                'correspondant' => $data
            ]
        );

        return $this->json($data, 200);
    }
}
