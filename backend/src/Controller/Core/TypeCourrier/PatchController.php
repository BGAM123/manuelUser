<?php

namespace App\Controller\Core\TypeCourrier;

use App\Entity\Core\TypeCourrier;
use App\Repository\Core\CategorieCorrespondantRepository;
use App\Repository\Core\TypeCourrierRepository;
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
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private CategorieCorrespondantRepository $categorieRepository,
        private TypeCourrierRepository $typeCourrierRepository,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/type-courrier/{id<([1-9][0-9]*)>}', name: 'app_core_type_courrier_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/type-courrier/{id}',
        summary: 'Modifier un type de courrier',
        description: 'Met à jour les informations d\'un type de courrier existant.',
        tags: ['TypeCourrier'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du type de courrier à modifier',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'Courrier confidentiel'),
                        new OA\Property(property: 'type', type: 'string', example: 'Sortant'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent', description: 'Classe du courrier'),
                        new OA\Property(
                            property: 'idCategories', 
                            type: 'array', 
                            items: new OA\Items(type: 'integer'),
                            example: [1, 2], 
                            description: 'Nouvelles catégories associées'
                        ),
                        new OA\Property(property: 'idTypeParent', type: 'integer', example: 1, description: 'Nouveau type parent (optionnel)')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mise à jour réussie.'),
            new OA\Response(response: 404, description: 'Type de courrier non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function patch(Request $request, ?TypeCourrier $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PatchTypeCourrier');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Type de courrier non trouvé.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['code' => 400, 'message' => 'Données invalides.'], 400);
        }

        $this->functionService->validate($data);
        $data = $this->functionService->excludeFields($data, ['id', 'createdAt', 'updatedAt', 'isDelete']);

        // VÃ©rifier si un autre type de courrier avec ce nom existe dÃ©jÃ 
        if (!empty($data['nom'])) {
            $existingTypeCourrier = $this->typeCourrierRepository->createQueryBuilder('t')
                ->where('t.nom = :nom')
                ->andWhere('t.isDelete = :isDelete')
                ->andWhere('t.id != :currentId')
                ->setParameter('nom', $data['nom'])
                ->setParameter('isDelete', false)
                ->setParameter('currentId', $entity->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if ($existingTypeCourrier) {
                return $this->json([
                    'code' => 400,
                    'message' => 'Un autre type de courrier avec ce nom existe déjà.',
                    'data' => [
                        'nom' => $data['nom'],
                        'type_existant_id' => $existingTypeCourrier->getId()
                    ]
                ], 400);
            }
        }

        // Capture des anciennes donnÃ©es avant modification
        $oldData = [
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
        ];

        // ðŸ”¹ Si des catÃ©gories sont fournies, on remplace toutes les catÃ©gories
        if (array_key_exists('idCategories', $data)) {
            // Vider les catÃ©gories actuelles
            foreach ($entity->getCategories() as $cat) {
                $entity->removeCategorie($cat);
            }
            
            // Ajouter les nouvelles catÃ©gories
            if (!empty($data['idCategories']) && is_array($data['idCategories'])) {
                foreach ($data['idCategories'] as $categorieId) {
                    $categorie = $this->categorieRepository->find($categorieId);
                    if (!$categorie) {
                        return $this->json(['code' => 404, 'message' => "Catégorie avec l'ID {$categorieId} introuvable."], 404);
                    }
                    $entity->addCategorie($categorie);
                }
            }
            unset($data['idCategories']); // Supprimer pour Ã©viter que CrudService le traite
        }

        // ðŸ”¹ Si un parent est fourni, on vÃ©rifie qu'il existe
        if (array_key_exists('idTypeParent', $data)) {
            if (!empty($data['idTypeParent'])) {
                $parent = $this->typeCourrierRepository->find($data['idTypeParent']);
                if (!$parent) {
                    return $this->json(['code' => 404, 'message' => 'Type parent introuvable.'], 404);
                }
                $data['idTypeParent'] = $parent;
            } else {
                // Permettre de dÃ©tacher le parent avec null
                $data['idTypeParent'] = null;
            }
        }

        try {
            $entity = $this->crudService->patchEntity($entity, $data);

            // Capture des nouvelles donnÃ©es aprÃ¨s modification
            $newData = [
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
            ];

            // Log de la mise Ã  jour
            $this->actionLogger->logUpdate(
                'TypeCourrier',
                $entity->getId(),
                'Mise Ã  jour d\'un type de courrier',
                [
                    'before' => $oldData,
                    'after' => $newData,
                ]
            );

            return $this->json($entity, 200, [], ['groups' => 'Get:TypeCourrier']);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()
            ], 500);
        }
    }
}
