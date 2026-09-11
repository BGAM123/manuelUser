<?php

namespace App\Controller\Core\Classe;

use App\Repository\Core\ClasseCourrierRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "ClasseCourrier")]
class DeleteController extends AbstractController
{
    public function __construct(
        private ClasseCourrierRepository $classeCourrierRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/classe-courrier/{id}', name: 'app_core_classe_courrier_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/classe-courrier/{id}',
        summary: 'Supprimer une classe de courrier',
        description: 'Supprime définitivement une classe de courrier',
        tags: ['ClasseCourrier'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID de la classe de courrier', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Classe de courrier supprimée avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Classe de courrier supprimée avec succès')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Classe de courrier non trouvée'),
            new OA\Response(response: 401, description: 'Accès non autorisé')
        ]
    )]
    public function delete(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteClasseCourrier');

        $entity = $this->classeCourrierRepository->find($id);

        if (!$entity) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Classe de courrier non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $nom = $entity->getNom();
            
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            // Log de la suppression
            $this->actionLogger->logDelete(
                'ClasseCourrier',
                $id,
                sprintf('Suppression: %s', $nom)
            );

            return $this->json([
                'code' => Response::HTTP_OK,
                'message' => 'Classe de courrier supprimée avec succès'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
