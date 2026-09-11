<?php

namespace App\Controller\Core\Transmission;

use App\Entity\Cour\Reponse;
use App\Entity\Cour\Transmission;
use App\Repository\Cour\ReponseRepository;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Transmission")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
        private ReponseRepository $reponseRepository,
    ) {}

    #[Route('/core/transmission/{id<([1-9][0-9]*)>}', name: 'app_core_transmission_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/transmission/{id}',
        summary: 'Supprime une transmission (physique)',
        tags: ['Transmission'],
        description: "Supprime définitivement une transmission de la base de données.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la transmission',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Transmission supprimée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 204),
                        new OA\Property(property: 'message', type: 'string', example: 'Transmission supprimée avec succès.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Transmission non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(Transmission $entity): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteTransmission');

        $transmissionId = $entity->getId();

        foreach ($this->reponseRepository->findIdsByTransmissionId($transmissionId) as $reponseId) {
            $this->crudService->deleteEntity(Reponse::class, $reponseId);
        }

        $this->crudService->deleteEntity(Transmission::class, $transmissionId);

        return $this->json(['code' => 204, 'message' => 'Transmission supprimée avec succès.'], 204);
    }
}
