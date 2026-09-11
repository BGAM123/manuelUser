<?php

namespace App\Controller\Core\Parametre;

use App\Repository\Core\ServiceRepository;
use App\Repository\Core\UserRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Repository\Core\CategorieCorrespondantRepository;
use App\Repository\Core\TypeCourrierRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/core/list')]
#[OA\Tag(name: "Paramètres - Listes Sélecteurs")]
class ListController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker
    ) {}

    // âœ… MODIFIÃ‰ : Liste des services avec filtre isVisibleInTransmission
    #[Route('/service', name: 'app_core_list_service', methods: ['GET'])]
    #[OA\Get(
        path: '/core/list/service',
        summary: 'Liste des services actifs',
        description: 'Retourne tous les services actifs et non supprimés pour affichage dans un sélecteur. Peut filtrer par visibilité dans transmission.',
        security: [["bearerAuth" => []]],
        tags: ['Paramètres - Listes Sélecteurs'],
        parameters: [
            new OA\Parameter(
                name: 'visibleInTransmission',
                in: 'query',
                required: false,
                description: 'Filtrer par visibilité dans transmission (true/false). Par défaut : tous les services.',
                schema: new OA\Schema(type: 'string', enum: ['true', 'false'])
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK')
        ]
    )]
    public function listService(Request $request, ServiceRepository $repo): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'ListService');

        $qb = $repo->createQueryBuilder('s')
            ->where('s.isDelete = false')
            ->andWhere('s.isActive = true');

        // âœ… Filtre optionnel sur isVisibleInTransmission
        $visibleInTransmission = $request->query->get('visibleInTransmission');
        if ($visibleInTransmission !== null) {
            $qb->andWhere('s.isVisibleInTransmission = :visible')
               ->setParameter('visible', filter_var($visibleInTransmission, FILTER_VALIDATE_BOOLEAN));
        }

        $services = $qb->orderBy('s.nom', 'ASC')->getQuery()->getResult();

        $data = array_map(fn($s) => [
            'id' => $s->getId(),
            'nom' => $s->getNom(),
            'libelle' => $s->getSigle() ?? $s->getNom(),
            'isDirection' => $s->isDirection(),
            'isVisibleInTransmission' => $s->isVisibleInTransmission(),
        ], $services);

        return $this->json($data);
    }

    #[Route('/service/hierarchy', name: 'app_core_list_service_hierarchy', methods: ['GET'])]
    #[OA\Get(
        path: '/core/list/service/hierarchy',
        summary: 'Liste des services selon hiérarchie utilisateur',
        description: 'Retourne les services accessibles selon la position du service utilisateur. Si direction : son service + descendants + toutes directions. Sinon : tous les services de sa direction parente.',
        security: [["bearerAuth" => []]],
        tags: ['Paramètres - Listes Sélecteurs'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'nom', type: 'string', example: 'Direction Générale'),
                            new OA\Property(property: 'sigle', type: 'string', example: 'DG'),
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function listServiceHierarchy(ServiceRepository $repo): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'ListServiceHierarchy');

        $user = $this->getUser();
        /** @var \App\Entity\Core\User $user */
        $userService = $user->getIdService(); // ou getService() selon votre entitÃ©

        if (!$userService) {
            return $this->json(['error' => 'Utilisateur non rattaché à un service'], 400);
        }

        $serviceIds = [];

        // ðŸ¢ CAS 1 : le service utilisateur est une direction
        if ($userService->isDirection()) {

            // Son service + descendants
            $serviceIds[] = $userService->getId();
            $serviceIds = array_merge($serviceIds, $this->getDescendantIds($userService, $repo));

            // + toutes les directions actives
            $directions = $repo->createQueryBuilder('s')
                ->select('s.id')
                ->where('s.isDirection = true')
                ->andWhere('s.isDelete = false')
                ->andWhere('s.isActive = true')
                ->getQuery()
                ->getResult();

            foreach ($directions as $dir) {
                $serviceIds[] = $dir['id'];
            }

        } else {
            // ðŸ§­ CAS 2 : simple chef de service (non direction)
            // On remonte jusquâ€™Ã  trouver la direction parente
            $directionParente = $this->getParentDirection($userService);

            if (!$directionParente) {
                $directionParente = $this->getRootService($userService);
            }

            // Tous les services sous cette direction
            $serviceIds[] = $directionParente->getId();
            $serviceIds = array_merge($serviceIds, $this->getDescendantIds($directionParente, $repo));
        }

        $serviceIds = array_unique($serviceIds);

        if (empty($serviceIds)) {
            return $this->json([]);
        }

        // RÃ©cupÃ©ration finale
        $services = $repo->createQueryBuilder('s')
            ->where('s.id IN (:ids)')
            ->andWhere('s.isDelete = false')
            ->andWhere('s.isActive = true')
            ->setParameter('ids', $serviceIds)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(fn($s) => [
            'id' => $s->getId(),
            'nom' => $s->getNom(),
            'libelle' => $s->getSigle() ?? $s->getNom(),
            'isDirection' => $s->isDirection(),
        ], $services);

        return $this->json($data);
    }
    private function getDescendantIds($service, ServiceRepository $repo): array
    {
        $ids = [];

        $children = $repo->createQueryBuilder('s')
            ->where('s.idServiceParent = :parent')
            ->andWhere('s.isDelete = false')
            ->setParameter('parent', $service)
            ->getQuery()
            ->getResult();

        foreach ($children as $child) {
            $ids[] = $child->getId();
            $ids = array_merge($ids, $this->getDescendantIds($child, $repo));
        }

        return $ids;
    }
    private function getParentDirection($service)
    {
        $current = $service;
        while ($current) {
            if ($current->isDirection()) {
                return $current;
            }
            $current = $current->getIdServiceParent();
        }
        return null;
    }
    private function getRootService($service)
    {
        $current = $service;
        while ($current->getIdServiceParent() !== null) {
            $current = $current->getIdServiceParent();
        }
        return $current;
    }


    // ðŸ”¹ Liste des correspondants
    #[Route('/correspondant', name: 'app_core_list_correspondant', methods: ['GET'])]
    #[OA\Get(
        path: '/core/list/correspondant',
        summary: 'Liste des correspondants',
        description: 'Retourne tous les correspondants, avec possibilité de filtrer par catégorie.',
        security: [["bearerAuth" => []]],
        tags: ['Paramètres - Listes Sélecteurs'],
        parameters: [
            new OA\Parameter(name: 'categorie', in: 'query', required: false, description: 'ID de la catégorie à filtrer.', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK')
        ]
    )]
    public function listCorrespondant(Request $request, CorrespondantRepository $repo): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'ListCorrespondant');

        $categorie = $request->query->get('categorie');
        $qb = $repo->createQueryBuilder('c')
            ->leftJoin('c.categories', 'cat')
            ->addSelect('cat')
            ->where('c.isDelete = false');

        if ($categorie) {
            $qb->andWhere('cat.id = :cat')
               ->setParameter('cat', $categorie);
        }

        $correspondants = $qb->orderBy('c.nom', 'ASC')->getQuery()->getResult();

        $data = array_map(fn($c) => [
            'id' => $c->getId(),
            'nom' => $c->getNom(),
            'libelle' => $c->getNom(),
            'categorie' => $c->getCategories()->count() > 0 
                ? $c->getCategories()->first()->getNom()
                : null,
        ], $correspondants);

        return $this->json($data);
    }

    // ðŸ”¹ Liste des catégories
    #[Route('/categorie', name: 'app_core_list_categorie', methods: ['GET'])]
    #[OA\Get(
        path: '/core/list/categorie',
        summary: 'Liste des catégories de correspondants',
        description: 'Retourne toutes les catégories de correspondants disponibles.',
        security: [["bearerAuth" => []]],
        tags: ['Paramètres - Listes Sélecteurs'],
        responses: [
            new OA\Response(response: 200, description: 'OK')
        ]
    )]
    public function listCategorie(CategorieCorrespondantRepository $repo): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'ListCategorie');

        $categories = $repo->createQueryBuilder('c')
            ->where('c.isDelete = false')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()->getResult();

        $data = array_map(fn($c) => [
            'id' => $c->getId(),
            'nom' => $c->getNom(),
            'libelle' => $c->getNom(),
        ], $categories);

        return $this->json($data);
    }

    // ðŸ”¹ Liste des types de courrier
    #[Route('/type-courrier', name: 'app_core_list_type_courrier', methods: ['GET'])]
    #[OA\Get(
        path: '/core/list/type-courrier',
        summary: 'Liste des types de courrier',
        description: 'Retourne tous les types de courrier, avec possibilité de filtrer par catégorie ou par type (entrant, sortant, interne...).',
        security: [["bearerAuth" => []]],
        tags: ['Paramètres - Listes Sélecteurs'],
        parameters: [
            new OA\Parameter(name: 'categorie', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK')
        ]
    )]
    public function listTypeCourrier(Request $request, TypeCourrierRepository $repo): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'ListTypeCourrier');

        $categorie = $request->query->get('categorie');
        $type = $request->query->get('type');

        $qb = $repo->createQueryBuilder('t')
            ->leftJoin('t.categories', 'cat')
            ->addSelect('cat')
            ->where('t.isDelete = false');

        if ($categorie) {
            $qb->andWhere('cat.id = :cat')->setParameter('cat', $categorie);
        }
        if ($type) {
            $qb->andWhere('t.type = :type')->setParameter('type', $type);
        }

        $types = $qb->orderBy('t.nom', 'ASC')->getQuery()->getResult();

        $data = array_map(fn($t) => [
            'id' => $t->getId(),
            'nom' => $t->getNom(),
            'type' => $t->getType(),
            'categorie' => $t->getCategories()->count() > 0 
                ? $t->getCategories()->first()->getNom()
                : null,
        ], $types);

        return $this->json($data);
    }


 // âœ… Liste des types de courrier racines (sans parent)
#[Route('/type-courrier/parents', name: 'app_core_list_type_courrier_parents', methods: ['GET'])]
#[OA\Get(
    path: '/core/list/type-courrier/parents',
    summary: 'Lister les types de courrier racines (sans parent)',
    description: 'Retourne tous les types de courrier n\'ayant pas de parent (idTypeParent IS NULL).',
    security: [["bearerAuth" => []]],
    tags: ['Paramètres - Listes Sélecteurs'],
    responses: [
        new OA\Response(response: 200, description: 'Liste des types racines récupérée avec succès.'),
        new OA\Response(response: 401, description: 'Accès non autorisé.')
    ]
)]
public function listTypeCourrierParents(TypeCourrierRepository $repo): Response
{
    $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'ListTypeCourrierParents');

    $parents = $repo->createQueryBuilder('t')
        ->leftJoin('t.typeEnfants', 'child')
        ->addSelect('child')
        ->where('t.idTypeParent IS NULL')
        ->andWhere('t.isDelete = false')
        ->orderBy('t.nom', 'ASC')
        ->getQuery()
        ->getResult();

    $data = array_map(fn($t) => [
        'id' => $t->getId(),
        'nom' => $t->getNom(),
        'type' => $t->getType(),
        'categorie' => $t->getCategories()->count() > 0 
            ? $t->getCategories()->first()->getNom()
            : null,
        'nombreEnfants' => count($t->getTypeEnfants()->filter(fn($e) => !$e->isDelete())),
    ], $parents);

    return $this->json([
        'total' => count($data),
        'data' => $data,
    ], 200);
}

// âœ… Liste des enfants dâ€™un type de courrier donnÃ©
#[Route('/type-courrier/{id}/children', name: 'app_core_list_type_courrier_children', methods: ['GET'])]
#[OA\Get(
    path: '/core/list/type-courrier/{id}/children',
    summary: 'Lister les sous-types d\'un type de courrier parent',
    description: 'Retourne la liste des sous-types d\'un type parent donné.',
    security: [["bearerAuth" => []]],
    tags: ['Paramètres - Listes Sélecteurs'],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant du type parent', schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Liste des sous-types du parent.'),
        new OA\Response(response: 404, description: 'Type parent introuvable.'),
        new OA\Response(response: 401, description: 'Accès non autorisé.'),
    ]
)]
public function listTypeCourrierChildren(int $id, TypeCourrierRepository $repo): Response
{
    $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'ListTypeCourrierChildren');

    $parent = $repo->find($id);
    if (!$parent || $parent->isDelete()) {
        return $this->json(['message' => 'Type parent introuvable.'], 404);
    }

    $children = $parent->getTypeEnfants()->filter(fn($c) => !$c->isDelete());

    $data = array_map(fn($child) => [
        'id' => $child->getId(),
        'nom' => $child->getNom(),
        'type' => $child->getType(),
        'categorie' => $child->getCategories()->count() > 0 
            ? $child->getCategories()->first()->getNom()
            : null,
        'createdAt' => $child->getCreatedAt()?->format('Y-m-d H:i:s'),
    ], $children->toArray());

    return $this->json([
        'parent' => [
            'id' => $parent->getId(),
            'nom' => $parent->getNom(),
        ],
        'total' => count($data),
        'data' => $data,
    ], 200);
}

    // ðŸ”¹ Liste des utilisateurs
    #[Route('/user', name: 'app_core_list_user', methods: ['GET'])]
    #[OA\Get(
        path: '/core/list/user',
        summary: 'Liste des utilisateurs actifs',
        description: 'Retourne la liste des utilisateurs actifs et non supprimés.',
        security: [["bearerAuth" => []]],
        tags: ['Paramètres - Listes Sélecteurs'],
        responses: [
            new OA\Response(response: 200, description: 'OK')
        ]
    )]
    public function listUser(UserRepository $repo): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'ListUser');

        $users = $repo->createQueryBuilder('u')
            ->where('u.isDelete = false')
            ->andWhere('u.isActive = true')
            ->orderBy('u.firstName', 'ASC')
            ->getQuery()->getResult();

        $data = array_map(fn($u) => [
            'id' => $u->getId(),
            'nom' => $u->getFullName(),
        ], $users);

        return $this->json($data);
    }


    // ðŸ”¹ Liste des catÃ©gories avec leurs correspondants (provenances)
    #[Route('/categorie/provenances', name: 'app_core_list_categorie_with_correspondants', methods: ['GET'])]
    #[OA\Get(
        path: '/core/list/categorie/provenances',
        summary: 'Liste des catégories avec leurs correspondants (provenance)',
        description: 'Retourne toutes les catégories non supprimées avec la liste de leurs correspondants non supprimés.',
        security: [["bearerAuth" => []]],
        tags: ['Paramètres - Listes Sélecteurs'],
        responses: [
            new OA\Response(response: 200, description: 'OK')
        ]
    )]
    public function listCategorieWithCorrespondants(CategorieCorrespondantRepository $repo): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'ListCategorieWithCorrespondants');

        $categories = $repo->createQueryBuilder('c')
            ->leftJoin('c.correspondants', 'corr')
            ->addSelect('corr')
            ->where('c.isDelete = false')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];

        foreach ($categories as $cat) {

            // Filtrer correspondants non supprimÃ©s
            $correspondants = $cat->getCorrespondants()
                ->filter(fn($c) => !$c->isDelete())
                ->toArray();

            // Tri alphabÃ©tique des correspondants
            usort($correspondants, fn($a, $b) => strcmp($a->getNom() ?? '', $b->getNom() ?? ''));

            $data[] = [
                'id' => $cat->getId(),
                'nom' => $cat->getNom(),
                'libelle' => $cat->getNom(),
                'correspondants' => array_map(fn($c) => [
                    'id' => $c->getId(),
                    'nom' => $c->getNom(),
                ], $correspondants)
            ];
        }

        return $this->json($data);
    }

}