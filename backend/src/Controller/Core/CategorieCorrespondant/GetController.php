<?php

namespace App\Controller\Core\CategorieCorrespondant;

use App\Repository\Core\CategorieCorrespondantRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CategorieCorrespondant")]
class GetController extends AbstractController
{
    public function __construct(
        private CategorieCorrespondantRepository $categorieRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/categorie-correspondant/{id<\d+>}', name: 'app_core_categorie_correspondant_get', methods: ['GET'])]
    #[OA\Get(
        path: '/core/categorie-correspondant/{id}',
        summary: 'Récupérer une catégorie de correspondant par ID',
        description: 'Retourne les détails d\'une catégorie de correspondant spécifique.',
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
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catégorie trouvée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Ministère'),
                        new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-02-15T10:12:00+00:00')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Catégorie non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getCategorie(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCategorieCorrespondant');

        $categorie = $this->categorieRepository->find($id);

        if (!$categorie) {
            return $this->json(['code' => 404, 'message' => 'Catégorie non trouvée.'], 404);
        }

        // Logger la consultation
        $this->actionLogger->logView(
            'CategorieCorrespondant',
            $categorie->getId(),
            'Consultation d\'une catégorie de correspondant',
            [
                'categorie' => [
                    'id' => $categorie->getId(),
                    'nom' => $categorie->getNom(),
                    'is_delete' => $categorie->isDelete(),
                    'created_at' => $categorie->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'updated_at' => $categorie->getUpdatedAt()?->format('Y-m-d H:i:s')
                ]
            ]
        );

        return $this->json([
            'id' => $categorie->getId(),
            'nom' => $categorie->getNom(),
            'isDelete' => $categorie->isDelete(),
            'createdAt' => $categorie->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $categorie->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ], 200);
    }
}
