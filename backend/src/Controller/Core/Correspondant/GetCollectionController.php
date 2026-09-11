<?php

namespace App\Controller\Core\Correspondant;

use App\Repository\Core\CorrespondantRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Correspondant")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private CorrespondantRepository $correspondantRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/correspondant', name: 'app_core_correspondant_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/correspondant',
        summary: 'Liste des correspondants',
        description: 'Récupère la liste des correspondants avec possibilité de filtrer par catégorie, type ou statut (isDelete).',
        tags: ['Correspondant'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'idCategorie', in: 'query', required: false, description: 'ID de la catégorie', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', in: 'query', required: false, description: 'Type de correspondant', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'isDelete', in: 'query', required: false, description: 'Inclure les correspondants supprimés (true/false)', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Terme de recherche global', schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des correspondants.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont'),
                            new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                            new OA\Property(property: 'telephone', type: 'string', example: '0612345678'),
                            new OA\Property(
                                property: 'categories',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'nom', type: 'string', example: 'Particulier')
                                    ]
                                )
                            ),
                            new OA\Property(property: 'type', type: 'string', example: 'Externe'),
                            new OA\Property(property: 'isDelete', type: 'boolean', example: false)
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCollectionCorrespondant');

        // RÃ©cupÃ©ration des paramÃ¨tres de recherche
        $isDelete = $request->query->get('isDelete', false);
        $search = $request->query->get('search');
        $idCategorie = $request->query->get('idCategorie');
        $type = $request->query->get('type');

        // Construction des critÃ¨res
        $criteria = [];

        if ($idCategorie) {
            $criteria['idCategorie'] = (int) $idCategorie;
        }
        
        if ($type) {
            $criteria['type'] = $type;
        }
        
        if ($isDelete !== null) {
            $criteria['isDelete'] = filter_var($isDelete, FILTER_VALIDATE_BOOLEAN);
        }

        // Utilisation de la mÃ©thode avec filtres et recherche
        $entities = $this->correspondantRepository->findByFilters($criteria, $search);

        $data = array_map(fn($c) => [
            'id' => $c->getId(),
            'nom' => $c->getNom(),
            'email' => $c->getEmail(),
            'telephone' => $c->getTelephone(),
            'categories' => array_map(fn($cat) => [
                'id' => $cat->getId(),
                'nom' => $cat->getNom()
            ], $c->getCategories()->toArray()),
            'type' => $c->getType(),
            'isDelete' => $c->isDelete(),
        ], $entities);

        // âœ… LOG ASYNCHRONE - Consultation de la liste des correspondants
        $this->actionLogger->logView(
            'Correspondant',
            null,
            'Consultation de la liste des correspondants',
            [
                'total' => count($data),
                'filters' => [
                    'idCategorie' => $idCategorie,
                    'type' => $type,
                    'isDelete' => $isDelete,
                    'search' => $search,
                ],
                'correspondants' => array_slice($data, 0, 100) // Limiter Ã  100 pour Ã©viter des logs trop volumineux
            ]
        );

        return $this->json($data, 200);
    }
}
