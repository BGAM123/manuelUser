<?php

namespace App\Controller\Core\Classe;

use App\Entity\Core\ClasseCourrier;
use App\Repository\Core\ClasseCourrierRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "ClasseCourrier")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private ClasseCourrierRepository $classeCourrierRepository,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/classe-courrier', name: 'app_core_classe_courrier_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/classe-courrier',
        summary: 'Créer une classe de courrier',
        description: 'Crée une nouvelle classe de courrier avec son nom',
        tags: ['ClasseCourrier'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['nom'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Urgent', description: 'Nom de la classe de courrier')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Classe de courrier créée avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Urgent'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-11-21T10:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-11-21T10:30:00+00:00')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 401, description: 'Accès non autorisé')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostClasseCourrier');

        $data = json_decode($request->getContent(), true) ?? [];
        $this->functionService->validate($data);

        try {
            $entity = new ClasseCourrier();
            $entity = $this->crudService->postEntity($entity, $data);

            // Recharger l'entitÃ©
            $refreshedEntity = $this->classeCourrierRepository->find($entity->getId());

            // Log de la crÃ©ation
            $this->actionLogger->logCreate(
                'ClasseCourrier',
                $refreshedEntity->getId(),
                'Nom: ' . $refreshedEntity->getNom()
            );

            return $this->json($refreshedEntity, Response::HTTP_CREATED, [], [
                'groups' => ['classe_courrier:read']
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
