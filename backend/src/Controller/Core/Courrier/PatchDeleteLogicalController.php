<?php

namespace App\Controller\Core\Courrier;

use App\Entity\Cour\Courrier;
use OpenApi\Attributes as OA;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierArrive")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
    ) {}

    // ðŸ”» SUPPRESSION LOGIQUE
    #[Route('/core/courrier/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_courrier_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/courrier/delete-logical/{id}',
        summary: 'Suppression logique d\'un courrier entrant',
        description: 'Marque un courrier comme supprimé sans le retirer de la base de données.',
        tags: ['CourrierArrive'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier marqué comme supprimé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier supprimé logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?Courrier $entity = null): Response
    {
        $user = $this->getUser();
        $this->accessChecker->checker($user, $this->isGranted('ROLE_USER'), 'DeleteCourrier');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Courrier non trouvé.'], 404);
        }

        // Supprimer totalement toutes les transmissions associées à ce courrier
        foreach ($entity->getTransmissions() as $transmission) {
            $this->entityManager->remove($transmission);
        }
        
        // Marquer le courrier comme supprimé logiquement
        $this->functionService->updateBooleanField(Courrier::class, $entity->getId(), 'isDelete', true);
        
        // Persister les suppressions de transmissions
        $this->entityManager->flush();

        return $this->json(['code' => 200, 'message' => 'Courrier supprimé logiquement avec succès.'], 200);
    }

    // ðŸ” RESTAURATION LOGIQUE
    #[Route('/core/courrier/restore-logical/{id<([1-9][0-9]*)>}', name: 'app_core_courrier_restore_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/courrier/restore-logical/{id}',
        summary: 'Restaure logiquement un courrier entrant',
        description: 'Restaure un courrier précédemment marqué comme supprimé.',
        tags: ['CourrierArrive'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier restauré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier restauré logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function restore(?Courrier $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'RestoreCourrier');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Courrier non trouvé.'], 404);
        }

        $this->functionService->updateBooleanField(Courrier::class, $entity->getId(), 'isDelete', false);

        return $this->json(['code' => 200, 'message' => 'Courrier restauré logiquement avec succès.'], 200);
    }
}
