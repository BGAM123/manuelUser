<?php

namespace App\Controller\Core\TransmissionReponse;

use App\Entity\Cour\Reponse;
use App\Entity\Cour\TransmissionReponse;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TransmissionReponse")]
class PatchDeleteLogicalController extends AbstractController
{
    public function __construct(
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
        private TransmissionReponseRepository $transmissionReponseRepository,
    ) {}

    // Suppression logique
    #[Route('/core/transmission-reponse/delete-logical/{id<([1-9][0-9]*)>}', name: 'app_core_transmission_reponse_delete_logical', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/transmission-reponse/delete-logical/{id}',
        summary: 'Suppression logique d\'une transmission reponse',
        description: 'Marque une transmission reponse comme supprimée sans la retirer de la base de données.',
        tags: ['TransmissionReponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transmission reponse marquée comme supprimée.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Transmission reponse supprimée logiquement avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Transmission reponse non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function delete(?TransmissionReponse $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'DeleteLogicalTransmissionReponse');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Transmission reponse non trouvée.'], 404);
        }

        $reponseId = null;
        $reponseIds = $entity->getIdReponses() ?? [];
        if (is_array($reponseIds) && !empty($reponseIds)) {
            $reponseIds = array_values(array_unique(array_map('intval', array_filter($reponseIds, 'is_numeric'))));
            if (!empty($reponseIds)) {
                $reponseId = (int) end($reponseIds);
            }
        }

        $deletedData = [
            'id' => $entity->getId(),
            'objet' => $entity->getObjet(),
            'commentairePublic' => $entity->getCommentairePublic(),
            'classeCourrier' => $entity->getClasseCourrier(),
            'typeTransmission' => $entity->getTypeTransmission(),
            'typeReponse' => $entity->getTypeReponse()?->getNom(),
            'serviceDestinataire' => $entity->getIdServiceDestinataire()?->getNom(),
            'dateReponse' => $entity->getDateReponse()?->format('Y-m-d'),
            'reponseId' => $reponseId,
        ];

        if ($reponseId) {
            $otherActiveTransmissionReponses = $this->transmissionReponseRepository->countActiveByReponseIdExcludingId($reponseId, $entity->getId());
            if ($otherActiveTransmissionReponses === 0) {
                $this->functionService->updateBooleanField(Reponse::class, $reponseId, 'isDelete', true);
            }
        }

        $this->functionService->updateBooleanField(TransmissionReponse::class, $entity->getId(), 'isDelete', true);

        $this->actionLogger->logDelete(
            'TransmissionReponse',
            $entity->getId(),
            'Suppression logique d\'une transmission reponse',
            [
                'deleted_data' => $deletedData,
                'type' => 'logical',
            ]
        );

        return $this->json(['code' => 200, 'message' => 'Transmission reponse supprimée logiquement avec succès.'], 200);
    }
}
