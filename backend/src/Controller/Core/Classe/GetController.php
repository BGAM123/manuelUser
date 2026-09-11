<?php

namespace App\Controller\Core\Classe;

use App\Repository\Core\ClasseCourrierRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "ClasseCourrier")]
class GetController extends AbstractController
{
    public function __construct(
        private ClasseCourrierRepository $classeCourrierRepository,
        private AccessCheckerService $accessChecker
    ) {}

    #[Route('/core/classe-courrier/{id}', name: 'app_core_classe_courrier_get', methods: ['GET'])]
    #[OA\Get(
        path: '/core/classe-courrier/{id}',
        summary: 'Récupérer une classe de courrier',
        description: 'Retourne les détails d\'une classe de courrier spécifique',
        tags: ['ClasseCourrier'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID de la classe de courrier', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Classe de courrier récupérée avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Urgent'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Classe de courrier non trouvée'),
            new OA\Response(response: 401, description: 'Accès non autorisé')
        ]
    )]
    public function get(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetClasseCourrier');

        $entity = $this->classeCourrierRepository->find($id);

        if (!$entity) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Classe de courrier non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($entity, Response::HTTP_OK, [], [
            'groups' => ['classe_courrier:read']
        ]);
    }
}
