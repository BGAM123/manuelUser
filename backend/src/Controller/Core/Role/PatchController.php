<?php

namespace App\Controller\Core\Role;

use App\Repository\Core\RoleRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Role")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private RoleRepository $roleRepository,
    ) {}

    #[Route('/core/role/{id<([1-9][0-9]*)>}', name: 'app_core_role_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/role/{id}',
        summary: 'Met à jour un rôle existant',
        tags: ['Role'],
        description: "Met à jour les informations d'un rôle (sans toucher aux permissions).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Gestionnaire Principal'),
                    new OA\Property(property: 'description', type: 'string', example: 'Peut gérer tous les courriers'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Rôle mis à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Rôle mis à jour avec succès'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Rôle non trouvé.'),
            new OA\Response(response: 400, description: 'Erreur de validation.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PatchRole');

        $role = $this->roleRepository->find($id);

        if (!$role) {
            return $this->json(['code' => 404, 'message' => 'Rôle non trouvé.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->functionService->validate($data);
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt']);

        try {
            // ðŸ’¾ Mise Ã  jour
            $this->crudService->patchEntity($role, $data);

            return $this->json([
                'message' => 'Rôle mis à jour avec succès',
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}