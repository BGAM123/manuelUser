<?php

namespace App\Controller\Core\Salle;

use App\Repository\Core\SalleRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Salle")]
class GetController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private SalleRepository $salleRepository,
    ) {}

    #[Route('/core/salle/{id}', name: 'app_core_salle_get', methods: ['GET'])]
    #[OA\Get(
        path: '/core/salle/{id}',
        summary: 'Récupérer une salle par son ID',
        tags: ['Salle'],
        description: "Récupère les détails d'une salle spécifique.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant de la salle', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Salle trouvée.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Salle de réunion A'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-12-02T10:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-12-02T10:30:00+00:00'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Salle non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function data(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetSalle');

        $salle = $this->salleRepository->find($id);

        if (!$salle || $salle->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Salle non trouvée.'], 404);
        }

        $responseData = [
            'id' => $salle->getId(),
            'nom' => $salle->getNom(),
            'isActive' => $salle->isActive(),
            'isDelete' => $salle->isDelete(),
            'createdAt' => $salle->getCreatedAt()?->format('c'),
            'updatedAt' => $salle->getUpdatedAt()?->format('c'),
        ];

        return $this->json($responseData, 200);
    }
}
