<?php

namespace App\Controller\Core\TypeCourrier;

use App\Repository\Core\TypeCourrierRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeCourrier")]
class GetSousTypesController extends AbstractController
{
    public function __construct(
        private TypeCourrierRepository $typeCourrierRepository,
        private AccessCheckerService $accessChecker
    ) {}

    #[Route('/core/type-courrier/type/{id}', name: 'app_core_type_courrier_sous_types', methods: ['GET'])]
    #[OA\Get(
        path: '/core/type-courrier/type/{id}',
        summary: 'Récupérer les sous-types d\'un type de courrier',
        tags: ['TypeCourrier'],
        description: 'Retourne la liste des sous-types (enfants) d\'un type de courrier parent spécifique.',
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du type de courrier parent',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Recherche textuelle dans les sous-types',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'isDelete',
                in: 'query',
                required: false,
                description: 'Filtrer par statut de suppression (true/false)',
                schema: new OA\Schema(type: 'string', enum: ['true', 'false'])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sous-types récupérés avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'parent',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'nom', type: 'string', example: 'Courrier administratif'),
                                new OA\Property(property: 'type', type: 'string', example: 'ENTRANT')
                            ]
                        ),
                        new OA\Property(property: 'total', type: 'integer', example: 5),
                        new OA\Property(
                            property: 'sousTypes',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 2),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Demande de congé'),
                                    new OA\Property(property: 'type', type: 'string', example: 'ENTRANT'),
                                    new OA\Property(property: 'idCategorie', type: 'integer', example: 1),
                                    new OA\Property(property: 'categorieNom', type: 'string', example: 'Administratif')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Type de courrier parent non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getSousTypes(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetSousTypesTypeCourrier');

        // RÃ©cupÃ©rer le type parent
        $typeParent = $this->typeCourrierRepository->find($id);
        if (!$typeParent) {
            return $this->json([
                'code' => 404,
                'message' => 'Type de courrier parent non trouvé.'
            ], 404);
        }

        // Paramètres de filtrage
        $search = $request->query->get('search');
        $isDelete = $request->query->get('isDelete');

        // Construire la requête pour les sous-types
        $qb = $this->typeCourrierRepository->createQueryBuilder('tc')
            ->where('tc.idTypeParent = :parentId')
            ->setParameter('parentId', $id);

        // Filtrer par suppression logique
        if ($isDelete !== null) {
            $qb->andWhere('tc.isDelete = :isDelete')
               ->setParameter('isDelete', $isDelete === 'true');
        }

        // Filtrer par recherche
        if (!empty($search)) {
            $qb->andWhere('tc.search LIKE :search OR tc.nom LIKE :search OR tc.type LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('tc.nom', 'ASC');

        $sousTypes = $qb->getQuery()->getResult();

        // Construire la rÃ©ponse
        $sousTypesData = [];
        foreach ($sousTypes as $sousType) {
            $sousTypesData[] = [
                'id' => $sousType->getId(),
                'nom' => $sousType->getNom(),
                'type' => $sousType->getType(),
                'categories' => $sousType->getCategories()->map(fn($cat) => [
                    'id' => $cat->getId(),
                    'nom' => $cat->getNom(),
                ])->toArray(),
                'idTypeParent' => $sousType->getIdTypeParent()?->getId(),
                'typeParentNom' => $sousType->getIdTypeParent()?->getNom(),
                'createdAt' => $sousType->getCreatedAt()?->format('c'),
                'updatedAt' => $sousType->getUpdatedAt()?->format('c'),
                'isDelete' => $sousType->isDelete()
            ];
        }

        return $this->json([
            'parent' => [
                'id' => $typeParent->getId(),
                'nom' => $typeParent->getNom(),
                'type' => $typeParent->getType(),
                'categories' => $typeParent->getCategories()->map(fn($cat) => [
                    'id' => $cat->getId(),
                    'nom' => $cat->getNom(),
                ])->toArray(),
            ],
            'total' => count($sousTypesData),
            'sousTypes' => $sousTypesData
        ], 200);
    }
}
