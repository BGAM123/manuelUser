<?php

namespace App\Controller\Core\TransmissionReponse;

use App\Entity\Cour\TransmissionReponse;
use App\Repository\Cour\CourrierInterneRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TransmissionReponse")]
class GelController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private UserActionLoggerService $actionLogger,
        private CourrierInterneRepository $courrierInterneRepository,
    ) {}

    #[Route('/core/transmission-reponse/geler/{id<([1-9][0-9]*)>}', name: 'app_core_transmission_reponse_geler', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/transmission-reponse/geler/{id}',
        summary: 'Geler une transmission reponse',
        description: 'Gele une transmission reponse en definissant is_geled a true.',
        tags: ['TransmissionReponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la transmission reponse a geler',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transmission reponse gelee avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Transmission reponse gelee avec succes'),
                        new OA\Property(property: 'is_geled', type: 'boolean', example: true),
                        new OA\Property(property: 'statut', type: 'string', example: 'Classe'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'La transmission reponse est deja gelee.'),
            new OA\Response(response: 404, description: 'Transmission reponse non trouvee.'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function geler(?TransmissionReponse $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GelerTransmissionReponse');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Transmission reponse non trouvee.'], 404);
        }

        if ($entity->isGeled()) {
            return $this->json(['code' => 400, 'message' => 'La transmission reponse est deja gelee.'], 400);
        }

        $entity->setGeled(true);
        $linkedCourrierInterneIds = $entity->getIdCourrierInternes();
        if (is_array($linkedCourrierInterneIds)) {
            foreach ($linkedCourrierInterneIds as $courrierInterneId) {
                if (!is_numeric($courrierInterneId)) {
                    continue;
                }
                $linkedCourrierInterne = $this->courrierInterneRepository->find((int) $courrierInterneId);
                if ($linkedCourrierInterne && !$linkedCourrierInterne->isGeled()) {
                    $linkedCourrierInterne->setGeled(true);
                    $linkedCourrierInterne->refreshStatut();
                }
            }
        }
        $entity->refreshStatut();
        $this->entityManager->flush();

        $this->actionLogger->logUpdate(
            'TransmissionReponse',
            $entity->getId(),
            'Gel d\'une transmission reponse',
            ['is_geled' => true]
        );

        return $this->json([
            'code' => 200,
            'message' => 'Transmission reponse gelee avec succes',
            'is_geled' => $entity->isGeled(),
            'statut' => $entity->getStatut(),
        ], 200);
    }

    #[Route('/core/transmission-reponse/degeler/{id<([1-9][0-9]*)>}', name: 'app_core_transmission_reponse_degeler', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/transmission-reponse/degeler/{id}',
        summary: 'Degeler une transmission reponse',
        description: 'Degele une transmission reponse en definissant is_geled a false.',
        tags: ['TransmissionReponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la transmission reponse a degeler',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transmission reponse degelee avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Transmission reponse degelee avec succes'),
                        new OA\Property(property: 'is_geled', type: 'boolean', example: false),
                        new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'La transmission reponse n\'est pas gelee.'),
            new OA\Response(response: 404, description: 'Transmission reponse non trouvee.'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function degeler(?TransmissionReponse $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'DegelerTransmissionReponse');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Transmission reponse non trouvee.'], 404);
        }

        if (!$entity->isGeled()) {
            return $this->json(['code' => 400, 'message' => 'La transmission reponse n\'est pas gelee.'], 400);
        }

        $entity->setGeled(false);
        $linkedCourrierInterneIds = $entity->getIdCourrierInternes();
        if (is_array($linkedCourrierInterneIds)) {
            foreach ($linkedCourrierInterneIds as $courrierInterneId) {
                if (!is_numeric($courrierInterneId)) {
                    continue;
                }
                $linkedCourrierInterne = $this->courrierInterneRepository->find((int) $courrierInterneId);
                if ($linkedCourrierInterne && $linkedCourrierInterne->isGeled()) {
                    $linkedCourrierInterne->setGeled(false);
                    $linkedCourrierInterne->refreshStatut();
                }
            }
        }
        $entity->refreshStatut();
        $this->entityManager->flush();

        $this->actionLogger->logUpdate(
            'TransmissionReponse',
            $entity->getId(),
            'Degel d\'une transmission reponse',
            ['is_geled' => false]
        );

        return $this->json([
            'code' => 200,
            'message' => 'Transmission reponse degelee avec succes',
            'is_geled' => $entity->isGeled(),
            'statut' => $entity->getStatut(),
        ], 200);
    }
}
