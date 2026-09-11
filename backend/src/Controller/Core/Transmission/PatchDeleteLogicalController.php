<?php

namespace App\Controller\Core\Transmission;

use App\Entity\Cour\Reponse;
use App\Entity\Cour\Transmission;
use App\Repository\Cour\ReponseRepository;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Transmission")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private ReponseRepository $reponseRepository,
    ) {}

    // 🔻 SUPPRESSION LOGIQUE
    #[Route('/core/transmission/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_transmission_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/transmission/delete-logical/{id}',
        summary: 'Suppression logique d\'une transmission',
        description: 'Marque une transmission comme supprimée sans la retirer de la base de données.',
        tags: ['Transmission'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la transmission',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transmission marquée comme supprimée.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Transmission supprimée logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Transmission non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?Transmission $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'DeleteLogicalTransmission');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Transmission non trouvée.'], 404);
        }

        $transmissionId = $entity->getId();

        foreach ($this->reponseRepository->findIdsByTransmissionId($transmissionId, false) as $reponseId) {
            $this->functionService->updateBooleanField(Reponse::class, $reponseId, 'isDelete', true);
        }

        $this->functionService->updateBooleanField(Transmission::class, $transmissionId, 'isDelete', true);

        return $this->json(['code' => 200, 'message' => 'Transmission supprimée logiquement avec succès.'], 200);
    }

    // 🔁 RESTAURATION LOGIQUE
    #[Route('/core/transmission/restore-logical/{id<([1-9][0-9]*)>}', name: 'app_core_transmission_restore_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/transmission/restore-logical/{id}',
        summary: 'Restaure logiquement une transmission',
        description: 'Restaure une transmission précédemment marquée comme supprimée.',
        tags: ['Transmission'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la transmission',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transmission restaurée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Transmission restaurée logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Transmission non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function restore(?Transmission $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'RestoreLogicalTransmission');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Transmission non trouvée.'], 404);
        }

        $transmissionId = $entity->getId();

        foreach ($this->reponseRepository->findIdsByTransmissionId($transmissionId, true) as $reponseId) {
            $this->functionService->updateBooleanField(Reponse::class, $reponseId, 'isDelete', false);
        }

        $this->functionService->updateBooleanField(Transmission::class, $transmissionId, 'isDelete', false);

        return $this->json(['code' => 200, 'message' => 'Transmission restaurée logiquement avec succès.'], 200);
    }
}
