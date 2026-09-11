<?php

namespace App\Controller\Core\TypeTransmission;

use App\Entity\Core\TypeTransmission;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'TypeTransmission')]
class GetController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/type-transmission/{id<([1-9][0-9]*)>}', name: 'app_core_type_transmission_get', methods: ['GET'])]
    #[OA\Get(
        path: '/core/type-transmission/{id}',
        summary: 'Consulter un type de transmission',
        description: 'Retourne les details d\'un type de transmission a partir de son ID.',
        tags: ['TypeTransmission'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Type de transmission trouve.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Transmission physique'),
                        new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2026-04-20 12:00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', example: '2026-04-20 12:00:00'),
                    ],
                    example: [
                        'id' => 1,
                        'nom' => 'Transmission physique',
                        'isDelete' => false,
                        'createdAt' => '2026-04-20 12:00:00',
                        'updatedAt' => '2026-04-20 12:00:00',
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
    public function get(?TypeTransmission $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTypeTransmission');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Type de transmission non trouve.'], 404);
        }

        $data = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'isDelete' => $entity->isDelete(),
            'createdAt' => $entity->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $entity->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        $this->actionLogger->logView(
            'TypeTransmission',
            $entity->getId(),
            'Consultation d\'un type de transmission',
            $data
        );

        return $this->json($data, 200);
    }
}
