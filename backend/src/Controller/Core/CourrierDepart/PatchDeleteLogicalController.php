<?php

namespace App\Controller\Core\CourrierDepart;

use App\Entity\Cour\CourrierDepart;
use OpenApi\Attributes as OA;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierDepart")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    // ðŸ”» SUPPRESSION LOGIQUE
    #[Route('/core/courrier-depart/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_courrier_depart_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/courrier-depart/delete-logical/{id}',
        summary: 'Suppression logique un courrier de départ',
        description: 'Marque un courrier de départ comme supprimé sans le retirer de la base de données.',
        tags: ['CourrierDepart'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier de départ',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier de départ marqué comme supprimé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier de départ supprimé logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier de départ non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?CourrierDepart $entity = null): Response
    {
        $user = $this->getUser();
        $this->accessChecker->checker($user, $this->isGranted('ROLE_USER'), 'DeleteCourrierDepart');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Courrier de départ non trouvé.'], 404);
        }

        // Sauvegarder les données avant suppression logique pour le log
        $deletedData = [
            'id' => $entity->getId(),
            'numeroReference' => $entity->getNumeroReference(),
            'classeCourrier' => $entity->getClasseCourrier(),
            'categorie' => $entity->getCategorie(),
            'dateSignature' => $entity->getDateSignature()?->format('Y-m-d'),
        ];

        $this->functionService->updateBooleanField(CourrierDepart::class, $entity->getId(), 'isDelete', true);

        // Logger la suppression logique
        $this->actionLogger->logDelete(
            'CourrierDepart',
            $entity->getId(),
            'Suppression logique d\'un courrier de départ',
            [
                'courrier' => $deletedData,
                'type' => 'logical'
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Courrier de départ supprimé logiquement avec succès.'], 200);
    }

    // ðŸ” RESTAURATION LOGIQUE
    #[Route('/core/courrier-depart/restore-logical/{id<([1-9][0-9]*)>}', name: 'app_core_courrier_depart_restore_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/courrier-depart/restore-logical/{id}',
        summary: 'Restaure logiquement un courrier de départ',
        description: 'Restaure un courrier de départ précédemment marqué comme supprimé.',
        tags: ['CourrierDepart'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier de départ',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier de départ restauré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier de départ restauré logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier de départ non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function restore(?CourrierDepart $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'RestoreCourrierDepart');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Courrier de départ non trouvé.'], 404);
        }

        // Sauvegarder les données pour le log
        $restoredData = [
            'id' => $entity->getId(),
            'numeroReference' => $entity->getNumeroReference(),
            'classeCourrier' => $entity->getClasseCourrier(),
            'categorie' => $entity->getCategorie(),
        ];

        $this->functionService->updateBooleanField(CourrierDepart::class, $entity->getId(), 'isDelete', false);

        // Logger la restauration
        $this->actionLogger->logUpdate(
            'CourrierDepart',
            $entity->getId(),
            'Restauration logique d\'un courrier de départ',
            [
                'courrier' => $restoredData,
                'type' => 'restore'
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Courrier de départ restauré logiquement avec succès.'], 200);
    }
}
