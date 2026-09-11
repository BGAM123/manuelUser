<?php

namespace App\Controller\Core\CourrierDepart;

use App\Entity\Cour\CourrierDepart;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierDepart")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/courrier-depart/{id<([1-9][0-9]*)>}', name: 'app_core_courrier_depart_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/courrier-depart/{id}',
        summary: 'Suppression physique un courrier de départ',
        description: "Supprime définitivement un courrier de départ de la base de données (à utiliser avec prudence).",
        tags: ['CourrierDepart'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier de départ à supprimer',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Suppression effectuée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 204),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier de départ supprimé avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier de départ non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?CourrierDepart $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteCourrierDepart');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Courrier de départ non trouvé.'], 404);
        }

        // Sauvegarder les données avant suppression pour le log
        $deletedData = [
            'id' => $entity->getId(),
            'numeroReference' => $entity->getNumeroReference(),
            'classeCourrier' => $entity->getClasseCourrier(),
            'categorie' => $entity->getCategorie(),
            'dateSignature' => $entity->getDateSignature()?->format('Y-m-d'),
            'document' => $entity->getDocument(),
        ];

        $this->crudService->deleteEntity(CourrierDepart::class, $entity->getId());

        // Logger la suppression avec les donnÃ©es du courrier supprimÃ©
        $this->actionLogger->logDelete(
            'CourrierDepart',
            $deletedData['id'],
            'Suppression physique d\'un courrier départ',
            [
                'deleted_courrier' => $deletedData
            ]
        );

        return $this->json(['code' => 204, 'message' => 'Courrier de départ supprimé avec succès.'], 204);
    }
}
