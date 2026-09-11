<?php

namespace App\Controller\Core\Classe;

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
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private ClasseCourrierRepository $classeCourrierRepository,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/classe-courrier/{id}', name: 'app_core_classe_courrier_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/classe-courrier/{id}',
        summary: 'Mettre à  jour une classe de courrier',
        description: 'Met à jour partiellement une classe de courrier existante',
        tags: ['ClasseCourrier'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID de la classe de courrier', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Très urgent', description: 'Nom de la classe de courrier')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Classe de courrier mise à jour avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Très urgent'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Classe de courrier non trouvée'),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 401, description: 'Accès non autorisé')
        ]
    )]
    public function update(int $id, Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PatchClasseCourrier');

        $entity = $this->classeCourrierRepository->find($id);

        if (!$entity) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Classe de courrier non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->functionService->validate($data);

        try {
            $oldNom = $entity->getNom();
            
            $entity = $this->crudService->patchEntity($entity, $data);

            // Recharger l'entitÃ©
            $refreshedEntity = $this->classeCourrierRepository->find($entity->getId());

            // Log de la mise Ã  jour
            $this->actionLogger->logUpdate(
                'ClasseCourrier',
                $refreshedEntity->getId(),
                sprintf('Modification: %s -> %s', $oldNom, $refreshedEntity->getNom())
            );

            return $this->json($refreshedEntity, Response::HTTP_OK, [], [
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
