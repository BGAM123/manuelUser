<?php

namespace App\Controller\Core\TypeReponse;

use App\Repository\Core\TypeReponseRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeReponse")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private TypeReponseRepository $typeReponseRepository,
    ) {}

    #[Route('/core/type-reponse/{id}', name: 'app_core_type_reponse_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/type-reponse/{id}',
        summary: 'Mettre à jour un type de réponse',
        tags: ['TypeReponse'],
        description: "Met à jour les informations d'un type de réponse existant.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant du type de réponse', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'Accusé de réception modifié', description: 'Nouveau nom du type'),
                        new OA\Property(property: 'description', type: 'string', example: 'Description mise à jour', description: 'Nouvelle description'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: false, description: 'Nouveau statut actif'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Type de réponse mis à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Type de réponse mis à jour avec succès'),
                        new OA\Property(
                            property: 'typeReponse',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'nom', type: 'string', example: 'Accusé de réception modifié'),
                                new OA\Property(property: 'description', type: 'string', example: 'Description mise à jour'),
                                new OA\Property(property: 'isActive', type: 'boolean', example: false),
                                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-11-04T11:30:00+00:00'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Type de réponse non trouvée.'),
            new OA\Response(
                response: 400,
                description: 'Données invalides ou nom déjà utilisé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Un autre type de réponse utilise déjà ce nom.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PatchTypeReponse');

        $typeReponse = $this->typeReponseRepository->find($id);

        if (!$typeReponse || $typeReponse->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Type de réponse non trouvée.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['code' => 400, 'message' => 'JSON invalide: ' . json_last_error_msg()], 400);
        }

        $this->functionService->validate($data);
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt']);

        try {
            // VÃ©rifier l'unicitÃ© du nom si modifiÃ©
            if (isset($data['nom']) && $data['nom'] !== $typeReponse->getNom()) {
                $existingType = $this->typeReponseRepository->findByNom($data['nom']);
                if ($existingType && $existingType->getId() !== $typeReponse->getId()) {
                    return $this->json(['code' => 400, 'message' => 'Un autre type de réponse utilise déjà ce nom.'], 400);
                }
            }

            // Mettre à jour
            $updatedTypeReponse = $this->crudService->patchEntity($typeReponse, $data);

            return $this->json([
                'message' => 'Type de réponse mis à jour avec succès',
                'typeReponse' => [
                    'id' => $updatedTypeReponse->getId(),
                    'nom' => $updatedTypeReponse->getNom(),
                    'description' => $updatedTypeReponse->getDescription(),
                    'isActive' => $updatedTypeReponse->isActive(),
                    'updatedAt' => $updatedTypeReponse->getUpdatedAt()?->format('c'),
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
