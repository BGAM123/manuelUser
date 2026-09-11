<?php

namespace App\Controller\Core\Permission;

use App\Repository\Core\PermissionRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Permission")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private PermissionRepository $permissionRepository,
    ) {}

    #[Route('/core/permission/{id<([1-9][0-9]*)>}', name: 'app_core_permission_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/permission/{id}',
        summary: 'Met à jour une permission existante',
        tags: ['Permission'],
        description: "Met à jour les informations d'une permission.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'GetAllCourriers'),
                    new OA\Property(property: 'description', type: 'string', example: 'Permet de voir tous les courriers'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Permission mise à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Permission mise à jour avec succès'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Permission non trouvée.'),
            new OA\Response(response: 400, description: 'Erreur de validation.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PatchPermission');

        $permission = $this->permissionRepository->find($id);

        if (!$permission) {
            return $this->json(['code' => 404, 'message' => 'Permission non trouvée.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->functionService->validate($data);

        try {
            // ðŸ’¾ Mise Ã  jour
            $this->crudService->patchEntity($permission, $data);

            return $this->json([
                'message' => 'Permission mise à jour avec succès',
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}