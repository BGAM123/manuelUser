<?php

namespace App\Controller\Core\Permission;

use App\Entity\Core\Permission;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Permission")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/permission', name: 'app_core_permission_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/permission',
        summary: 'Créer une nouvelle permission',
        tags: ['Permission'],
        description: "Crée une nouvelle permission.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['nom'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'GetCourrier'),
                    new OA\Property(property: 'description', type: 'string', example: 'Permet de voir les courriers'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Permission créée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'message', type: 'string', example: 'Permission créée avec succès'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostPermission');

        $data = json_decode($request->getContent(), true);
        $this->functionService->validate($data);

        try {
            // ðŸ’¾ CrÃ©er la Permission
            $permission = new Permission();
            $permission = $this->crudService->postEntity($permission, $data);

            return $this->json([
                'id' => $permission->getId(),
                'message' => 'Permission créée avec succès',
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}