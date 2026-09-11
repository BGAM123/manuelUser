<?php

namespace App\Controller\Core\Service;

use App\Entity\Core\Service;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Service")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/service/{id<([1-9][0-9]*)>}', name: 'app_core_service_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/service/{id}',
        summary: 'Supprimer un service',
        description: 'Supprime définitivement un service de la base de données.',
        tags: ['Service'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du service à supprimer',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Suppression réussie.'),
            new OA\Response(response: 404, description: 'Service non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?Service $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteService');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Service non trouvé.'], 404);
        }

        // Sauvegarder les données avant suppression pour le log
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

        try {
            $this->crudService->deleteEntity(Service::class, $entity->getId());

            // Logger la suppression
            $this->actionLogger->logDelete(
                'Service',
                $entity->getId(),
                'Suppression physique d\'un service',
                [
                    'deleted_data' => $deletedData,
                ]
            );

            return $this->json(['code' => 204, 'message' => 'Service supprimé avec succès.'], 204);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
            ], 500);
        }
    }
}
