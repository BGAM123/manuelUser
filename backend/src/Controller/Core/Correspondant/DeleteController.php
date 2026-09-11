<?php

namespace App\Controller\Core\Correspondant;

use App\Entity\Core\Correspondant;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Correspondant")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/correspondant/{id<([1-9][0-9]*)>}', name: 'app_core_correspondant_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/correspondant/{id}',
        summary: 'Suppression définitive d\'un correspondant',
        description: 'Supprime définitivement un correspondant de la base de données (non réversible).',
        tags: ['Correspondant'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du correspondant à supprimer',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Correspondant supprimé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 204),
                        new OA\Property(property: 'message', type: 'string', example: 'Correspondant supprimé avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Correspondant non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?Correspondant $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteCorrespondant');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Correspondant non trouvé.'], 404);
        }

        // âœ… Capturer les donnÃ©es AVANT suppression pour le log
        $deletedData = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'email' => $entity->getEmail(),
            'telephone' => $entity->getTelephone(),
            'adresse' => $entity->getAdresse(),
            'type' => $entity->getType(),
            'civilite' => $entity->getCivilite(),
            'matricule' => $entity->getMatricule(),
            'categories' => array_map(fn($cat) => [
                'id' => $cat->getId(),
                'nom' => $cat->getNom()
            ], $entity->getCategories()->toArray())
        ];

        $correspondantId = $entity->getId();

        $this->crudService->deleteEntity(Correspondant::class, $entity->getId());

        // âœ… LOG ASYNCHRONE - Suppression dÃ©finitive d'un correspondant
        $this->actionLogger->logDelete(
            'Correspondant',
            $correspondantId,
            'Suppression définitive du correspondant',
            [
                'deleted_data' => $deletedData
            ]
        );

        return $this->json(['code' => 204, 'message' => 'Correspondant supprimé avec succès.'], 204);
    }
}
