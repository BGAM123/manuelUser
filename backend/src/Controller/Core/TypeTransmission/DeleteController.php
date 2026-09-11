<?php

namespace App\Controller\Core\TypeTransmission;

use App\Entity\Core\TypeTransmission;
use App\Service\Core\AccessCheckerService;
use App\Service\Core\CrudService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'TypeTransmission')]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker
    ) {}

    #[Route('/core/type-transmission/{id<([1-9][0-9]*)>}', name: 'app_core_type_transmission_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/type-transmission/{id}',
        summary: 'Supprimer un type de transmission',
        description: 'Supprime definitivement un type de transmission de la base de donnees.',
        tags: ['TypeTransmission'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Suppression reussie.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 204),
                        new OA\Property(property: 'message', type: 'string', example: 'Type de transmission supprime avec succes.'),
                    ],
                    example: [
                        'code' => 204,
                        'message' => 'Type de transmission supprime avec succes.',
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Type de transmission non trouve.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Type de transmission non trouve.'),
                    ],
                    example: [
                        'code' => 404,
                        'message' => 'Type de transmission non trouve.',
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Acces non autorise.'),
        ]
    )]
    public function delete(?TypeTransmission $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteTypeTransmission');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Type de transmission non trouve.'], 404);
        }

        try {
            $this->crudService->deleteEntity(TypeTransmission::class, $entity->getId());
            return $this->json(['code' => 204, 'message' => 'Type de transmission supprime avec succes.'], 204);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage(),
            ], 500);
        }
    }
}
