<?php

namespace App\Controller\Core\Salle;

use App\Repository\Core\SalleRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Salle")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private SalleRepository $salleRepository,
    ) {}

    #[Route('/core/salle/{id}', name: 'app_core_salle_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/salle/{id}',
        summary: 'Mettre à jour une salle',
        tags: ['Salle'],
        description: "Met à jour les informations d'une salle existante.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant de la salle', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'Salle de réunion B', description: 'Nouveau nom de la salle'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: false, description: 'Nouveau statut actif'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Salle mise à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Salle mise à jour avec succès'),
                        new OA\Property(
                            property: 'salle',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'nom', type: 'string', example: 'Salle de réunion B'),
                                new OA\Property(property: 'isActive', type: 'boolean', example: false),
                                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-12-02T11:30:00+00:00'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Salle non trouvée.'),
            new OA\Response(
                response: 400,
                description: 'Données invalides ou nom déjà utilisé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Une autre salle utilise déjà ce nom.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PatchSalle');

        $salle = $this->salleRepository->find($id);

        if (!$salle || $salle->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Salle non trouvée.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['code' => 400, 'message' => 'JSON invalide: ' . json_last_error_msg()], 400);
        }

        $this->functionService->validate($data);
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'isDelete']);

        try {
            // VÃ©rifier l'unicitÃ© du nom si modifiÃ©
            if (isset($data['nom']) && $data['nom'] !== $salle->getNom()) {
                $existingSalle = $this->salleRepository->findOneBy(['nom' => $data['nom']]);
                if ($existingSalle && $existingSalle->getId() !== $salle->getId()) {
                    return $this->json(['code' => 400, 'message' => 'Une autre salle utilise déjà ce nom.'], 400);
                }
            }

            // Mettre à jour
            $updatedSalle = $this->crudService->patchEntity($salle, $data);

            return $this->json([
                'message' => 'Salle mise à jour avec succès',
                'salle' => [
                    'id' => $updatedSalle->getId(),
                    'nom' => $updatedSalle->getNom(),
                    'isActive' => $updatedSalle->isActive(),
                    'isDelete' => $updatedSalle->isDelete(),
                    'updatedAt' => $updatedSalle->getUpdatedAt()?->format('c'),
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
