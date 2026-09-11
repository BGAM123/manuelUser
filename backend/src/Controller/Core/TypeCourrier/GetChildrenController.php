<?php

namespace App\Controller\Core\TypeCourrier;

use App\Repository\Core\TypeCourrierRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeCourrier")]
class GetChildrenController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private TypeCourrierRepository $typeCourrierRepository,
    ) {}

    #[Route('/core/type-courrier/{id}/children', name: 'app_core_type_courrier_get_children', methods: ['GET'])]
    #[OA\Get(
        path: '/core/type-courrier/{id}/children',
        summary: 'Récupérer tous les types de courrier enfants',
        description: 'Retourne tous les types de courrier enfants d\'un type parent donné, incluant tous les niveaux de la hiérarchie (enfants directs, petits-enfants, etc.).',
        tags: ['TypeCourrier'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du type de courrier parent pour lequel récupérer les enfants',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des types de courrier enfants récupérée avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'parentTypeId', type: 'integer', example: 1),
                        new OA\Property(property: 'parentTypeName', type: 'string', example: 'Courrier administratif'),
                        new OA\Property(
                            property: 'children',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 3),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Note de service'),
                                    new OA\Property(property: 'type', type: 'string', example: 'Entrant'),
                                    new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                                    new OA\Property(
                                        property: 'idCategorie',
                                        type: 'object',
                                        nullable: true,
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 2),
                                            new OA\Property(property: 'nom', type: 'string', example: 'Administration'),
                                        ]
                                    ),
                                    new OA\Property(property: 'level', type: 'integer', example: 1, description: 'Niveau dans la hiérarchie (1 = enfant direct, 2 = petit-enfant, etc.)'),
                                    new OA\Property(
                                        property: 'parentId',
                                        type: 'integer',
                                        nullable: true,
                                        example: 1,
                                        description: 'ID du type parent direct'
                                    ),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
                                ]
                            )
                        ),
                        new OA\Property(property: 'totalChildren', type: 'integer', example: 5)
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé'),
            new OA\Response(response: 404, description: 'Type de courrier non trouvé')
        ]
    )]
    public function getChildren(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetChildrenTypeCourrier');

        try {
            $typeCourrier = $this->typeCourrierRepository->find($id);

            if (!$typeCourrier) {
                return $this->json([
                    'code' => 404,
                    'message' => 'Type de courrier non trouvé'
                ], 404);
            }

            $allChildren = [];
            
            // RÃ©cupÃ©rer rÃ©cursivement tous les enfants
            $this->collectChildren($typeCourrier, $allChildren, 1);

            return $this->json([
                'parentTypeId' => $typeCourrier->getId(),
                'parentTypeName' => $typeCourrier->getNom(),
                'children' => $allChildren,
                'totalChildren' => count($allChildren)
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la récupération des types de courrier enfants: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * MÃ©thode rÃ©cursive pour collecter tous les types de courrier enfants Ã  tous les niveaux
     */
    private function collectChildren($parentType, array &$allChildren, int $level): void
    {
        $directChildren = $parentType->getTypeEnfants();

        foreach ($directChildren as $child) {
            $childData = [
                'id' => $child->getId(),
                'nom' => $child->getNom(),
                'type' => $child->getType(),
                'classeCourrier' => $child->getClasseCourrier(),
                'level' => $level,
                'parentId' => $parentType->getId(),
                'createdAt' => $child->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $child->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];

            // Ajouter les informations des catÃ©gories (plusieurs)
            $childData['categories'] = $child->getCategories()->map(function($cat) {
                return [
                    'id' => $cat->getId(),
                    'nom' => $cat->getNom(),
                ];
            })->toArray();

            $allChildren[] = $childData;
            
            // RÃ©cursivement rÃ©cupÃ©rer les enfants de cet enfant
            $this->collectChildren($child, $allChildren, $level + 1);
        }
    }
}
