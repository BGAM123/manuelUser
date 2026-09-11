<?php

namespace App\Controller\Core\Service;

use App\Entity\Core\Service;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Service")]
class GetController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/service/{id<([1-9][0-9]*)>}', name: 'app_core_service_get', methods: ['GET'])]
    #[OA\Get(
        path: '/core/service/{id}',
        summary: 'Récupérer un service par ID',
        description: 'Retourne les détails complets un service spécifique.',
        tags: ['Service'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du service',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détails du service récupérés avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Direction Générale'),
                        new OA\Property(property: 'sigle', type: 'string', example: 'DG'),
                        new OA\Property(property: 'emailService', type: 'string', example: 'contact@dg.gov'),
                        new OA\Property(property: 'telephone', type: 'string', example: '0022860000000'),
                        new OA\Property(property: 'numeroOrdre', type: 'integer', example: 1, description: 'Numéro d\'ordre pour le tri'),
                        new OA\Property(property: 'typeService', type: 'string', example: 'service', description: 'Type de service (poste ou service)'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(property: 'isDirection', type: 'boolean', example: false),
                        new OA\Property(property: 'isVisibleInTransmission', type: 'boolean', example: false),
                        new OA\Property(
                            property: 'idChefService',
                            type: 'object',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 5),
                                new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont')
                            ]
                        ),
                        new OA\Property(
                            property: 'idServiceParent',
                            type: 'object',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 2),
                                new OA\Property(property: 'nom', type: 'string', example: 'Direction Technique')
                            ]
                        ),
                        new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-02-20T10:00:00+00:00')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Service non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function get(?Service $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetService');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Service non trouvé.'], 404);
        }

        $data = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'sigle' => $entity->getSigle(),
            'emailService' => $entity->getEmailService(),
            'telephone' => $entity->getTelephone(),
            'numeroOrdre' => $entity->getNumeroOrdre(),
            'typeService' => $entity->getTypeService(),
            'isActive' => $entity->isActive(),
            'isDirection' => $entity->isDirection(),
            'isVisibleInTransmission' => $entity->isVisibleInTransmission(),
            
            // âœ… Chef du service : retourne {id, nom} ou null
            'idChefService' => $entity->getChefService() ? [
                'id' => $entity->getChefService()->getId(),
                'nom' => $entity->getChefService()->getFullName()
            ] : null,
            
            // âœ… Service parent : retourne {id, nom} ou null
            'idServiceParent' => $entity->getIdServiceParent() ? [
                'id' => $entity->getIdServiceParent()->getId(),
                'nom' => $entity->getIdServiceParent()->getNom()
            ] : null,
            
            'isDelete' => $entity->isDelete(),
            'createdAt' => $entity->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $entity->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        // Logger la consultation
        $this->actionLogger->logView(
            'Service',
            $entity->getId(),
            'Consultation d\'un service',
            [
                'service' => $data,
            ]
        );

        return $this->json($data, 200);
    }
}
