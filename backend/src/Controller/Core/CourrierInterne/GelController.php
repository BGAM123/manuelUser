<?php

namespace App\Controller\Core\CourrierInterne;

use App\Entity\Cour\CourrierInterne;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierInterne")]
class GelController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/courrier-interne/geler/{id<([1-9][0-9]*)>}', name: 'app_core_courrier_interne_geler', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/courrier-interne/geler/{id}',
        summary: 'Geler un courrier interne',
        description: 'Gele un courrier interne en definissant is_geled a true.',
        tags: ['CourrierInterne'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier interne a geler',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier interne gele avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier interne gele avec succes'),
                        new OA\Property(property: 'is_geled', type: 'boolean', example: true),
                        new OA\Property(property: 'statut', type: 'string', example: 'Classe'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Le courrier interne est deja gele.'),
            new OA\Response(response: 404, description: 'Courrier interne non trouve.'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function geler(?CourrierInterne $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GelerReponse');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Courrier interne non trouve.'], 404);
        }

        if ($entity->isGeled()) {
            return $this->json(['code' => 400, 'message' => 'Le courrier interne est deja gele.'], 400);
        }

        $entity->setGeled(true);
        $entity->refreshStatut();
        $this->entityManager->flush();

        $this->actionLogger->logUpdate(
            'CourrierInterne',
            $entity->getId(),
            'Gel d\'un courrier interne',
            ['is_geled' => true]
        );

        return $this->json([
            'code' => 200,
            'message' => 'Courrier interne gele avec succes',
            'is_geled' => $entity->isGeled(),
            'statut' => $entity->getStatut(),
        ], 200);
    }

    #[Route('/core/courrier-interne/degeler/{id<([1-9][0-9]*)>}', name: 'app_core_courrier_interne_degeler', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/courrier-interne/degeler/{id}',
        summary: 'Degeler un courrier interne',
        description: 'Degele un courrier interne en definissant is_geled a false.',
        tags: ['CourrierInterne'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier interne a degeler',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier interne degele avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier interne degele avec succes'),
                        new OA\Property(property: 'is_geled', type: 'boolean', example: false),
                        new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Le courrier interne n\'est pas gele.'),
            new OA\Response(response: 404, description: 'Courrier interne non trouve.'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function degeler(?CourrierInterne $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'DegelerReponse');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Courrier interne non trouve.'], 404);
        }

        if (!$entity->isGeled()) {
            return $this->json(['code' => 400, 'message' => 'Le courrier interne n\'est pas gele.'], 400);
        }

        $entity->setGeled(false);
        $entity->refreshStatut();
        $this->entityManager->flush();

        $this->actionLogger->logUpdate(
            'CourrierInterne',
            $entity->getId(),
            'Degel d\'un courrier interne',
            ['is_geled' => false]
        );

        return $this->json([
            'code' => 200,
            'message' => 'Courrier interne degele avec succes',
            'is_geled' => $entity->isGeled(),
            'statut' => $entity->getStatut(),
        ], 200);
    }
}
