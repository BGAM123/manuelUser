<?php

namespace App\Controller\Core\Reponse;

use App\Entity\Cour\Reponse;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Reponse")]
class GelController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/reponse/geler/{id<([1-9][0-9]*)>}', name: 'app_core_reponse_geler', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/reponse/geler/{id}',
        summary: 'Geler une reponse',
        description: 'Gele une reponse en definissant is_geled a true.',
        tags: ['Reponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la reponse a geler',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reponse gelee avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Reponse gelee avec succes'),
                        new OA\Property(property: 'is_geled', type: 'boolean', example: true),
                        new OA\Property(property: 'statut', type: 'string', example: 'Classé'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'La reponse est deja gelee.'),
            new OA\Response(response: 404, description: 'Reponse non trouvee.'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function geler(?Reponse $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GelerReponse');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Reponse non trouvee.'], 404);
        }

        if ($entity->isGeled()) {
            return $this->json(['code' => 400, 'message' => 'La reponse est deja gelee.'], 400);
        }

        $entity->setGeled(true);
        $entity->refreshStatut();
        $this->entityManager->flush();

        $this->actionLogger->logUpdate(
            'Reponse',
            $entity->getId(),
            'Gel d\'une reponse',
            ['is_geled' => true]
        );

        return $this->json([
            'code' => 200,
            'message' => 'Reponse gelee avec succes',
            'is_geled' => $entity->isGeled(),
            'statut' => $entity->getStatut(),
        ], 200);
    }

    #[Route('/core/reponse/degeler/{id<([1-9][0-9]*)>}', name: 'app_core_reponse_degeler', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/reponse/degeler/{id}',
        summary: 'Degeler une reponse',
        description: 'Degele une reponse en definissant is_geled a false.',
        tags: ['Reponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la reponse a degeler',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reponse degelee avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Reponse degelee avec succes'),
                        new OA\Property(property: 'is_geled', type: 'boolean', example: false),
                        new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'La reponse n\'est pas gelee.'),
            new OA\Response(response: 404, description: 'Reponse non trouvee.'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function degeler(?Reponse $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'DegelerReponse');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Reponse non trouvee.'], 404);
        }

        if (!$entity->isGeled()) {
            return $this->json(['code' => 400, 'message' => 'La reponse n\'est pas gelee.'], 400);
        }

        $entity->setGeled(false);
        $entity->refreshStatut();
        $this->entityManager->flush();

        $this->actionLogger->logUpdate(
            'Reponse',
            $entity->getId(),
            'Degel d\'une reponse',
            ['is_geled' => false]
        );

        return $this->json([
            'code' => 200,
            'message' => 'Reponse degelee avec succes',
            'is_geled' => $entity->isGeled(),
            'statut' => $entity->getStatut(),
        ], 200);
    }
}
