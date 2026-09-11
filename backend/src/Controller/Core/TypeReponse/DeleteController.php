<?php

namespace App\Controller\Core\TypeReponse;

use App\Repository\Core\TypeReponseRepository;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeReponse")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
        private TypeReponseRepository $typeReponseRepository,
    ) {}

    #[Route('/core/type-reponse/{id}', name: 'app_core_type_reponse_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/type-reponse/{id}',
        summary: 'Supprimer un type de réponse',
        tags: ['TypeReponse'],
        description: "Supprime logiquement un type de réponse (soft delete). Les réponses existantes conservent leur type.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant du type de réponse', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Type de réponse supprimé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Type de réponse supprimé avec succès'),
                        new OA\Property(property: 'nombreReponsesAffectees', type: 'integer', example: 15, description: 'Nombre de réponses qui utilisaient ce type'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Type de réponse non trouvé.'),
            new OA\Response(
                response: 409,
                description: 'Impossible de supprimer ce type car il est utilisé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 409),
                        new OA\Property(property: 'message', type: 'string', example: 'Vous ne pouvez pas supprimer ce type car il est utilisé par 15 réponses.'),
                        new OA\Property(property: 'nombreReponses', type: 'integer', example: 15),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteTypeReponse');

        $typeReponse = $this->typeReponseRepository->find($id);

        if (!$typeReponse || $typeReponse->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Type de réponse non trouvé.'], 404);
        }

        try {
            $nombreReponses = $typeReponse->getReponses()->count();

            // Option 1: Interdire la suppression si le type est utilisÃ©
            if ($nombreReponses > 0) {
                return $this->json([
                    'code' => 409,
                    'message' => "Vous ne pouvez pas supprimer ce type car il est utilisé par {$nombreReponses} réponse(s).",
                    'nombreReponses' => $nombreReponses
                ], 409);
            }

            // Option 2: Permettre la suppression logique (commentez l'option 1 et dÃ©commentez celle-ci)
            /*
            // Suppression logique
            $updatedTypeReponse = $this->crudService->patchEntity($typeReponse, ['isDelete' => true]);
            
            return $this->json([
                'message' => 'Type de rÃ©ponse supprimÃ© avec succÃ¨s',
                'nombreReponsesAffectees' => $nombreReponses
            ], 200);
            */

            // Si aucune rÃ©ponse n'utilise ce type, on peut le supprimer
            $this->crudService->patchEntity($typeReponse, ['isDelete' => true]);

            return $this->json([
                'message' => 'Type de réponse supprimé avec succès',
                'nombreReponsesAffectees' => 0
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
