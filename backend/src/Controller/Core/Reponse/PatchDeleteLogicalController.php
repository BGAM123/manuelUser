<?php

namespace App\Controller\Core\Reponse;

use App\Entity\Cour\Reponse;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Reponse")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    // ðŸ”» SUPPRESSION LOGIQUE
    #[Route('/core/reponse/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_reponse_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/reponse/delete-logical/{id}',
        summary: 'Suppression logique d\'une réponse',
        description: 'Marque une réponse comme supprimée sans la retirer de la base de données.',
        tags: ['Reponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Réponse marquée comme supprimée.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Réponse supprimée logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Réponse non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?Reponse $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'DeleteLogicalReponse');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Réponse non trouvée.'], 404);
        }

        // Sauvegarder les données avant suppression logique
        $deletedData = [
            'id' => $entity->getId(),
            'objet' => $entity->getObjet(),
            'commentairePublic' => $entity->getCommentairePublic(),
            'classeCourrier' => $entity->getClasseCourrier(),
            'typeTransmission' => $entity->getTypeTransmission(),
            'typeReponse' => $entity->getTypeReponse()?->getNom(),
            'serviceDestinataire' => $entity->getIdServiceDestinataire()?->getNom(),
            'dateReponse' => $entity->getDateReponse()?->format('Y-m-d'),
        ];

        $this->functionService->updateBooleanField(Reponse::class, $entity->getId(), 'isDelete', true);

        // Logger la suppression logique
        $this->actionLogger->logDelete(
            'Reponse',
            $entity->getId(),
            'Suppression logique d\'une réponse',
            [
                'deleted_data' => $deletedData,
                'type' => 'logical',
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Réponse supprimée logiquement avec succès.'], 200);
    }

    // ðŸ” RESTAURATION LOGIQUE
    #[Route('/core/reponse/restore-logical/{id<([1-9][0-9]*)>}', name: 'app_core_reponse_restore_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/reponse/restore-logical/{id}',
        summary: 'Restaure logiquement une réponse',
        description: 'Restaure une réponse prédémment marquée comme supprimée.',
        tags: ['Reponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Réponse restaurée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Réponse restaurée logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Réponse non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function restore(?Reponse $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'RestoreLogicalReponse');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Réponse non trouvée.'], 404);
        }

        // Sauvegarder les données avant restauration
        $restoredData = [
            'id' => $entity->getId(),
            'objet' => $entity->getObjet(),
            'commentairePublic' => $entity->getCommentairePublic(),
            'classeCourrier' => $entity->getClasseCourrier(),
            'typeTransmission' => $entity->getTypeTransmission(),
            'typeReponse' => $entity->getTypeReponse()?->getNom(),
            'serviceDestinataire' => $entity->getIdServiceDestinataire()?->getNom(),
            'dateReponse' => $entity->getDateReponse()?->format('Y-m-d'),
        ];

        $this->functionService->updateBooleanField(Reponse::class, $entity->getId(), 'isDelete', false);

        // Logger la restauration
        $this->actionLogger->logUpdate(
            'Reponse',
            $entity->getId(),
            'Restauration logique d\'une réponse',
            [
                'restored_data' => $restoredData,
                'type' => 'restore',
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Réponse restaurée logiquement avec succès.'], 200);
    }
}
