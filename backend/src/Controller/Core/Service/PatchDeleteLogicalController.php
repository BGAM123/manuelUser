<?php

namespace App\Controller\Core\Service;

use App\Entity\Core\Service;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Service")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    // ðŸ”» SUPPRESSION LOGIQUE
    #[Route('/core/service/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_service_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/service/delete-logical/{id}',
        summary: 'Suppression logique d\'un service',
        description: 'Marque un service comme supprimé sans le retirer de la base de données.',
        tags: ['Service'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du service',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service marqué comme supprimé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Service supprimé logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Service non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?Service $entity = null): Response
    {
        $user = $this->getUser();
        $this->accessChecker->checker($user, $this->isGranted('ROLE_ADMIN'), 'DeleteService');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Service non trouvé.'], 404);
        }

        // Sauvegarder les données avant suppression logique
        $deletedData = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'sigle' => $entity->getSigle(),
            'emailService' => $entity->getEmailService(),
            'telephone' => $entity->getTelephone(),
            'isActive' => $entity->isActive(),
            'isDirection' => $entity->isDirection(),
            'isVisibleInTransmission' => $entity->isVisibleInTransmission(),
            'chefService' => $entity->getChefService()?->getFullName(),
            'serviceParent' => $entity->getIdServiceParent()?->getNom(),
        ];

        $this->functionService->updateBooleanField(Service::class, $entity->getId(), 'isDelete', true);

        // Logger la suppression logique
        $this->actionLogger->logDelete(
            'Service',
            $entity->getId(),
            'Suppression logique d\'un service',
            [
                'deleted_data' => $deletedData,
                'type' => 'logical',
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Service supprimé logiquement avec succès.'], 200);
    }

    // ðŸ” RESTAURATION LOGIQUE
    #[Route('/core/service/restore-logical/{id<([1-9][0-9]*)>}', name: 'app_core_service_restore_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/service/restore-logical/{id}',
        summary: 'Restauration logique d\'un service',
        description: 'Restaure un service précédemment marqué comme supprimé.',
        tags: ['Service'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du service',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service restaurÃ© avec succÃ¨s.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Service restauré logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Service non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function restore(?Service $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'RestoreService');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Service non trouvÃ©.'], 404);
        }

        // Sauvegarder les donnÃ©es avant restauration
        $restoredData = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'sigle' => $entity->getSigle(),
            'emailService' => $entity->getEmailService(),
            'telephone' => $entity->getTelephone(),
            'isActive' => $entity->isActive(),
            'isDirection' => $entity->isDirection(),
            'isVisibleInTransmission' => $entity->isVisibleInTransmission(),
            'chefService' => $entity->getChefService()?->getFullName(),
            'serviceParent' => $entity->getIdServiceParent()?->getNom(),
        ];

        $this->functionService->updateBooleanField(Service::class, $entity->getId(), 'isDelete', false);

        // Logger la restauration
        $this->actionLogger->logUpdate(
            'Service',
            $entity->getId(),
            'Restauration logique d\'un service',
            [
                'restored_data' => $restoredData,
                'type' => 'restore',
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Service restauré logiquement avec succès.'], 200);
    }
}
