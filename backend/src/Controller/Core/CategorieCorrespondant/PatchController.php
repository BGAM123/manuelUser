<?php

namespace App\Controller\Core\CategorieCorrespondant;

use App\Entity\Core\CategorieCorrespondant;
use App\Repository\Core\CategorieCorrespondantRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CategorieCorrespondant")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private CategorieCorrespondantRepository $categorieRepository,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/categorie-correspondant/{id<\d+>}', name: 'app_core_categorie_correspondant_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/categorie-correspondant/{id}',
        summary: 'Modifier une catégorie de correspondant',
        description: 'Met à jour le nom ou dâ€™autres attributs d\'une catégorie existante.',
        tags: ['CategorieCorrespondant'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la catégorie',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Institution publique'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Catégorie mise à jour avec succès.'),
            new OA\Response(response: 404, description: 'Catégorie non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PatchCategorieCorrespondant');

        $categorie = $this->categorieRepository->find($id);

        if (!$categorie) {
            return $this->json(['code' => 404, 'message' => 'Catégorie non trouvée.'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data)) {
            return $this->json(['code' => 400, 'message' => 'Aucune donnée à mettre à jour.'], 400);
        }

        // Sauvegarder l'état avant modification
        $oldData = [
            'id' => $categorie->getId(),
            'nom' => $categorie->getNom(),
            'is_delete' => $categorie->isDelete(),
            'updated_at' => $categorie->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];

        try {
            $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt']);
            $categorie = $this->crudService->patchEntity($categorie, $data);

            // Logger la modification avec les changements
            $this->actionLogger->logUpdate(
                'CategorieCorrespondant',
                $categorie->getId(),
                'Modification d\'une catégorie de correspondant',
                [
                    'changes' => $data,
                    'old_data' => $oldData,
                    'new_data' => [
                        'id' => $categorie->getId(),
                        'nom' => $categorie->getNom(),
                        'is_delete' => $categorie->isDelete(),
                        'updated_at' => $categorie->getUpdatedAt()?->format('Y-m-d H:i:s')
                    ],
                    'user_agent' => $request->headers->get('User-Agent'),
                    'ip_address' => $request->getClientIp()
                ]
            );

            return $this->json([
                'code' => 200,
                'message' => 'Catégorie mise à jour avec succès.',
                'data' => [
                    'id' => $categorie->getId(),
                    'nom' => $categorie->getNom(),
                    'updatedAt' => $categorie->getUpdatedAt()?->format('Y-m-d H:i:s')
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
