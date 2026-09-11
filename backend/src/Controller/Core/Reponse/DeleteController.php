<?php

namespace App\Controller\Core\Reponse;

use App\Entity\Cour\Reponse;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Reponse")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/reponse/{id<([1-9][0-9]*)>}', name: 'app_core_reponse_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/reponse/{id}',
        summary: 'Supprime une réponse (physique)',
        tags: ['Reponse'],
        description: "Supprime définitivement une réponse de la base de données.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Réponse supprimée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Réponse supprimée avec succès.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Réponse non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(Reponse $entity): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteReponse');

        // Sauvegarder les donnÃ©es avant suppression pour le log
        $deletedData = [
            'id' => $entity->getId(),
            'objet' => $entity->getObjet(),
            'commentairePublic' => $entity->getCommentairePublic(),
            'classeCourrier' => $entity->getClasseCourrier(),
            'typeTransmission' => $entity->getTypeTransmission(),
            'typesCourrierIds' => $entity->getTypesCourrierIds(),
            'courrierIds' => $entity->getCourrierIds(),
            'typeReponse' => $entity->getTypeReponse()?->getNom(),
            'serviceDestinataire' => $entity->getIdServiceDestinataire()?->getNom(),
            'redacteur' => $entity->getIdRedacteur()?->getUserIdentifier(),
            'dateReponse' => $entity->getDateReponse()?->format('Y-m-d'),
        ];

        $this->crudService->deleteEntity(Reponse::class, $entity->getId());

        // Logger la suppression
        $this->actionLogger->logDelete(
            'Reponse',
            $entity->getId(),
            'Suppression physique d\'une réponse',
            [
                'deleted_data' => $deletedData,
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Réponse supprimée avec succès.'], 200);
    }
}
