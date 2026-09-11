<?php

namespace App\Controller\Core\TypeReponse;

use App\Repository\Core\TypeReponseRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeReponse")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private TypeReponseRepository $typeReponseRepository,
    ) {}

    // âœ… Route principale (existante)
    #[Route('/core/type-reponse', name: 'app_core_type_reponse_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/type-reponse',
        summary: 'Lister les types de réponse',
        tags: ['TypeReponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des types de réponse récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 50),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Réponse directe'),
                                    new OA\Property(property: 'description', type: 'string', example: 'Réponse envoyée directement'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function collection(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCollectionTypeReponse');

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 10)));
        $search = $request->query->get('search');
        $activeOnly = $request->query->getBoolean('active_only', false);
        $withCount = $request->query->getBoolean('with_count', false);
        $hierarchical = $request->query->getBoolean('hierarchical', false);

        if ($hierarchical) {
            $parentTypes = $this->typeReponseRepository->createQueryBuilder('t')
                ->leftJoin('t.sousTypes', 'st')
                ->addSelect('st')
                ->where('t.typeParent IS NULL')
                ->andWhere('t.isDelete = false')
                ->orderBy('t.nom', 'ASC')
                ->getQuery()
                ->getResult();
            
            $formattedData = array_map(function ($typeReponse) {
                return [
                    'id' => $typeReponse->getId(),
                    'nom' => $typeReponse->getNom(),
                    'description' => $typeReponse->getDescription(),
                    'isActive' => $typeReponse->isActive(),
                    'createdAt' => $typeReponse->getCreatedAt()?->format('c'),
                    'sousTypes' => array_map(function ($sousType) {
                        return [
                            'id' => $sousType->getId(),
                            'nom' => $sousType->getNom(),
                            'description' => $sousType->getDescription(),
                            'isActive' => $sousType->isActive(),
                        ];
                    }, $typeReponse->getSousTypes()->toArray())
                ];
            }, $parentTypes);
            
            return $this->json([
                'data' => $formattedData,
                'total' => count($formattedData),
            ], 200);
        }

        if ($activeOnly) {
            $data = $this->typeReponseRepository->findActive();
            $total = count($data);
            $data = array_slice($data, ($page - 1) * $limit, $limit);
        } elseif ($withCount) {
            $countData = $this->typeReponseRepository->countReponsesParType();
            $total = count($countData);
            $data = array_slice($countData, ($page - 1) * $limit, $limit);
        } else {
            $data = $this->typeReponseRepository->findWithPagination($page, $limit, $search);
            $total = $this->typeReponseRepository->countTotal($search);
        }

        $totalPages = ceil($total / $limit);

        $formattedData = [];
        foreach ($data as $typeReponse) {
            $itemData = [
                'id' => $typeReponse->getId(),
                'nom' => $typeReponse->getNom(),
                'description' => $typeReponse->getDescription(),
                'isActive' => $typeReponse->isActive(),
                'createdAt' => $typeReponse->getCreatedAt()?->format('c'),
                'typeParent' => $typeReponse->getTypeParent() ? [
                    'id' => $typeReponse->getTypeParent()->getId(),
                    'nom' => $typeReponse->getTypeParent()->getNom()
                ] : null,
                'sousTypes' => array_map(fn($sousType) => [
                    'id' => $sousType->getId(),
                    'nom' => $sousType->getNom(),
                ], $typeReponse->getSousTypes()->toArray())
            ];

            if ($withCount) {
                $itemData['nombreReponses'] = $typeReponse->getReponses()->count();
            }

            $formattedData[] = $itemData;
        }

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
            'data' => $formattedData,
        ], 200);
    }
    
// âœ… ROUTE 1 : RÃ©cupÃ©rer les types sans parent (types racines)
#[Route('/core/type-reponse/parents', name: 'app_core_type_reponse_get_parents', methods: ['GET'])]
#[OA\Get(
    path: '/core/type-reponse/parents',
    summary: 'Lister les types de réponse racines (sans parent)',
    tags: ['TypeReponse'],
    description: "Retourne tous les types de réponse qui n'ont pas de parent (typeParent IS NULL).",
    security: [["bearerAuth" => []]],
    responses: [
        new OA\Response(response: 200, description: 'Types racines récupérés avec succès.'),
        new OA\Response(response: 401, description: 'Accès non autorisé.')
    ]
)]
public function getParentTypes(): Response
{
    $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetParentTypeReponse');

    $types = $this->typeReponseRepository->createQueryBuilder('t')
        ->leftJoin('t.sousTypes', 'st')
        ->addSelect('st')
        ->where('t.typeParent IS NULL')
        ->andWhere('t.isDelete = false')
        ->orderBy('t.nom', 'ASC')
        ->getQuery()
        ->getResult();

    $data = array_map(fn($t) => [
        'id' => $t->getId(),
        'nom' => $t->getNom(),
        'description' => $t->getDescription(),
        'isActive' => $t->isActive(),
        'createdAt' => $t->getCreatedAt()?->format('c'),
        'nombreSousTypes' => count($t->getSousTypes()->filter(fn($s) => !$s->isDelete())),
    ], $types);

    return $this->json([
        'total' => count($data),
        'data' => $data
    ], 200);
}

// âœ… ROUTE 2 : RÃ©cupÃ©rer tous les enfants dâ€™un type parent donnÃ©
#[Route('/core/type-reponse/{id}/children', name: 'app_core_type_reponse_get_children', methods: ['GET'])]
#[OA\Get(
    path: '/core/type-reponse/{id}/children',
    summary: 'Lister les sous-types d\'un type parent',
    tags: ['TypeReponse'],
    description: "Retourne tous les sous-types d\'un type de réponse parent donné.",
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant du type parent', schema: new OA\Schema(type: 'integer')),
    ],
    security: [["bearerAuth" => []]],
    responses: [
        new OA\Response(response: 200, description: 'Sous-types récupérés avec succès.'),
        new OA\Response(response: 404, description: 'Type parent introuvable.'),
        new OA\Response(response: 401, description: 'Accès non autorisé.')
    ]
)]
public function getChildren(int $id): Response
{
    $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTypeReponseChildren');

    $parent = $this->typeReponseRepository->find($id);

    if (!$parent || $parent->isDelete()) {
        return $this->json(['code' => 404, 'message' => 'Type parent introuvable.'], 404);
    }

    $children = $parent->getSousTypes()->filter(fn($c) => !$c->isDelete());

    $data = array_map(fn($child) => [
        'id' => $child->getId(),
        'nom' => $child->getNom(),
        'description' => $child->getDescription(),
        'isActive' => $child->isActive(),
        'createdAt' => $child->getCreatedAt()?->format('c'),
    ], $children->toArray());

    return $this->json([
        'parent' => [
            'id' => $parent->getId(),
            'nom' => $parent->getNom(),
        ],
        'total' => count($data),
        'data' => $data
    ], 200);
}
}
