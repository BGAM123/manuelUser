<?php

namespace App\Controller\Core\CategorieCorrespondant;

use App\Entity\Core\CategorieCorrespondant;
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
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/categorie-correspondant', name: 'app_core_categorie_correspondant_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/categorie-correspondant',
        summary: 'Créer une nouvelle catégorie de correspondant',
        description: 'Ajoute une catégorie de correspondant dans le système.',
        tags: ['CategorieCorrespondant'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Ministère'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Catégorie créée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Ministère'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PostCategorieCorrespondant');

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['nom'])) {
            return $this->json(['code' => 400, 'message' => 'Le champ "nom" est requis.'], 400);
        }

        try {
            $categorie = new CategorieCorrespondant();
            $categorie = $this->crudService->postEntity($categorie, [
                'nom' => trim($data['nom']),
            ]);

            // Logger la crÃ©ation avec toutes les donnÃ©es
            $this->actionLogger->logCreate(
                'CategorieCorrespondant',
                $categorie->getId(),
                'Création d\'une catégorie de correspondant',
                [
                    'categorie' => [
                        'id' => $categorie->getId(),
                        'nom' => $categorie->getNom(),
                        'created_at' => $categorie->getCreatedAt()?->format('Y-m-d H:i:s')
                    ],
                    'request_data' => $data,
                    'user_agent' => $request->headers->get('User-Agent'),
                    'ip_address' => $request->getClientIp()
                ]
            );

            return $this->json($categorie, 201, [], ['groups' => ['Get:CategorieCorrespondant']]);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
