<?php

namespace App\Controller\Core\TypeCourrier;

use App\Repository\Core\TypeCourrierRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;

#[OA\Tag(name: "TypeCourrier")]
class GetCollectionAvecSousTypesController extends AbstractController
{
    public function __construct(
        private TypeCourrierRepository $typeCourrierRepository,
        private AccessCheckerService $accessChecker,
        private SerializerInterface $serializer
    ) {}

    #[Route('/core/type-courrier/type', name: 'app_core_type_courrier_avec_sous_types', methods: ['GET'])]
    #[OA\Get(
        path: '/core/type-courrier/type',
        summary: 'Lister les types de courrier avec leurs sous-types',
        tags: ['TypeCourrier'],
        description: 'Retourne la liste hiérarchique des types de courrier avec leurs sous-types imbriqués.',
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Recherche textuelle dans le nom ou type',
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
                description: 'Liste des types avec sous-types récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'total', type: 'integer', example: 15),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Courrier administratif'),
                                    new OA\Property(property: 'type', type: 'string', example: 'ENTRANT'),
                                    new OA\Property(
                                        property: 'sousTypes',
                                        type: 'array',
                                        items: new OA\Items(
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 2),
                                                new OA\Property(property: 'nom', type: 'string', example: 'Demande de congé'),
                                                new OA\Property(property: 'type', type: 'string', example: 'ENTRANT')
                                            ]
                                        )
                                    )
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getCollectionAvecSousTypes(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCollectionTypeCourrier');

        // ParamÃ¨tres de filtrage
        $search = $request->query->get('search');
        $isDelete = $request->query->get('isDelete');

        // Construire la requÃªte pour les types parents (sans parent)
        $qb = $this->typeCourrierRepository->createQueryBuilder('tc')
            ->where('tc.idTypeParent IS NULL');

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

        $typesParents = $qb->getQuery()->getResult();

        // Construire la rÃ©ponse avec sous-types
        $data = [];
        foreach ($typesParents as $typeParent) {
            $sousTypes = [];
            
            // RÃ©cupÃ©rer les sous-types pour ce parent
            foreach ($typeParent->getTypeEnfants() as $sousType) {
                // Appliquer les mÃªmes filtres aux sous-types
                $includeChild = true;
                
                if ($isDelete !== null && $sousType->isDelete() !== ($isDelete === 'true')) {
                    $includeChild = false;
                }
                
                if (!empty($search)) {
                    $searchInChild = stripos($sousType->getNom(), $search) !== false ||
                                   stripos($sousType->getType(), $search) !== false ||
                                   stripos($sousType->getSearch(), $search) !== false;
                    if (!$searchInChild) {
                        $includeChild = false;
                    }
                }
                
                if ($includeChild) {
                    $sousTypes[] = [
                        'id' => $sousType->getId(),
                        'nom' => $sousType->getNom(),
                        'type' => $sousType->getType(),
                        'categories' => $sousType->getCategories()->map(fn($cat) => [
                            'id' => $cat->getId(),
                            'nom' => $cat->getNom(),
                        ])->toArray(),
                        'createdAt' => $sousType->getCreatedAt()?->format('c'),
                        'updatedAt' => $sousType->getUpdatedAt()?->format('c')
                    ];
                }
            }

            $data[] = [
                'id' => $typeParent->getId(),
                'nom' => $typeParent->getNom(),
                'type' => $typeParent->getType(),
                'categories' => $typeParent->getCategories()->map(fn($cat) => [
                    'id' => $cat->getId(),
                    'nom' => $cat->getNom(),
                ])->toArray(),
                'sousTypes' => $sousTypes,
                'sousTypesCount' => count($sousTypes),
                'createdAt' => $typeParent->getCreatedAt()?->format('c'),
                'updatedAt' => $typeParent->getUpdatedAt()?->format('c')
            ];
        }

        return $this->json([
            'total' => count($data),
            'data' => $data
        ], 200);
    }
}
