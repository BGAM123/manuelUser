<?php

namespace App\Controller\Core\CourrierInterne;

use App\Entity\Cour\CourrierInterne;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierInterne")]
class DeleteController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/courrier-interne/{id<([1-9][0-9]*)>}', name: 'app_core_courrier_interne_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/courrier-interne/{id}',
        summary: 'Suppression logique d\'un courrier interne',
        description: 'Marque un courrier interne comme supprime sans le retirer de la base de donnees.',
        tags: ['CourrierInterne'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier interne supprime logiquement avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier interne supprime logiquement avec succes.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier interne non trouve.'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function delete(?CourrierInterne $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'DeleteLogicalReponse');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Courrier interne non trouve.'], 404);
        }

        // Sauvegarder les donnees avant suppression logique pour le log
        $deletedData = [
            'id' => $entity->getId(),
            'objet' => $entity->getObjet(),
            'commentairePublic' => $entity->getCommentairePublic(),
            'classeCourrier' => $entity->getClasseCourrier(),
            'typeTransmission' => $entity->getTypeTransmission(),
            'typeReponse' => $entity->getTypeReponse()?->getNom(),
            'serviceDestinataire' => $entity->getIdServiceDestinataire()?->getNom(),
            'redacteur' => $entity->getIdRedacteur()?->getUserIdentifier(),
            'dateReponse' => $entity->getDateReponse()?->format('Y-m-d'),
        ];

        $this->functionService->updateBooleanField(CourrierInterne::class, $entity->getId(), 'isDelete', true);

        // Logger la suppression logique
        $this->actionLogger->logDelete(
            'CourrierInterne',
            $entity->getId(),
            'Suppression logique d\'un courrier interne',
            [
                'deleted_data' => $deletedData,
                'type' => 'logical',
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Courrier interne supprime logiquement avec succes.'], 200);
    }
}
