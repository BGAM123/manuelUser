<?php

namespace App\Controller\Core\TypeCourrier;

use App\Entity\Core\TypeCourrier;
use App\Repository\Core\TypeCourrierRepository;
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

#[OA\Tag(name: "TypeCourrier")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private TypeCourrierRepository $typeCourrierRepository,
        private CategorieCorrespondantRepository $categorieRepository,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/type-courrier', name: 'app_core_type_courrier_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/type-courrier',
        summary: 'Créer un type de courrier',
        description: 'Crée un nouveau type de courrier, avec option de rattacher un parent (sous-type).',
        tags: ['TypeCourrier'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Courrier administratif'),
                    new OA\Property(property: 'type', type: 'string', example: 'Entrant', description: 'Type du courrier'),
                    new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent', description: 'Classe du courrier'),
                    new OA\Property(
                        property: 'idCategories', 
                        type: 'array', 
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3], 
                        description: 'IDs des catégories associées'
                    ),
                    new OA\Property(property: 'idTypeParent', type: 'integer', example: 1, description: 'ID du type parent (optionnel)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Créé'),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 401, description: 'Accès non autorisé')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostTypeCourrier');

        $data = json_decode($request->getContent(), true) ?? [];
        $this->functionService->validate($data);

        try {
            // VÃ©rifier si un type de courrier avec ce nom existe dÃ©jÃ 
            if (!empty($data['nom'])) {
                $existingTypeCourrier = $this->typeCourrierRepository->findOneBy([
                    'nom' => $data['nom'],
                    'isDelete' => false
                ]);

                if ($existingTypeCourrier) {
                    return $this->json([
                        'code' => 400,
                        'message' => 'Un type de courrier avec ce nom existe déjà.',
                        'data' => [
                            'nom' => $data['nom'],
                            'type_existant_id' => $existingTypeCourrier->getId()
                        ]
                    ], 400);
                }
            }

            $entity = new TypeCourrier();

            // GÃ©rer les catÃ©gories (plusieurs)
            if (!empty($data['idCategories']) && is_array($data['idCategories'])) {
                foreach ($data['idCategories'] as $categorieId) {
                    $categorie = $this->categorieRepository->find($categorieId);
                    if (!$categorie) {
                        return $this->json(['code' => 404, 'message' => "Catégorie avec l'ID {$categorieId} introuvable."], 404);
                    }
                    $entity->addCategorie($categorie);
                }
                unset($data['idCategories']); // Supprimer pour éviter que CrudService le traite
            }

            $parent = !empty($data['idTypeParent'])
                ? $this->typeCourrierRepository->find($data['idTypeParent'])
                : null;

            if (!empty($data['idTypeParent']) && !$parent) {
                return $this->json(['code' => 404, 'message' => 'Type parent introuvable.'], 404);
            }

            $data['idTypeParent'] = $parent;

            $entity = $this->crudService->postEntity($entity, $data);

            // Recharger l'entitÃ© avec toutes ses relations
            $refreshedEntity = $this->typeCourrierRepository->findOneWithRelations($entity->getId());

            // Log de la crÃ©ation
            $this->actionLogger->logCreate(
                'TypeCourrier',
                $entity->getId(),
                'CrÃ©ation d\'un type de courrier',
                [
                    'nom' => $entity->getNom(),
                    'type' => $entity->getType(),
                    'classe_courrier' => $entity->getClasseCourrier(),
                    'categories' => $entity->getCategories()->map(fn($cat) => [
                        'id' => $cat->getId(),
                        'nom' => $cat->getNom(),
                    ])->toArray(),
                    'parent' => $entity->getIdTypeParent() ? [
                        'id' => $entity->getIdTypeParent()->getId(),
                        'nom' => $entity->getIdTypeParent()->getNom(),
                    ] : null,
                ]
            );

            return $this->json([
                'code' => 201,
                'message' => 'Type de courrier créé avec succès',
                'data' => $refreshedEntity
            ], 201, [], ['groups' => 'Get:TypeCourrier']);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
