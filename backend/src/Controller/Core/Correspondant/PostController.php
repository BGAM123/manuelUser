<?php

namespace App\Controller\Core\Correspondant;

use App\Entity\Core\Correspondant;
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

#[OA\Tag(name: "Correspondant")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private CategorieCorrespondantRepository $categorieCorrespondantRepository,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/correspondant', name: 'app_core_correspondant_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/correspondant',
        summary: 'Créer un correspondant',
        description: 'Crée un nouveau correspondant et associe éventuellement à une ou plusieurs catégories existantes.',
        tags: ['Correspondant'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['nom'],
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont'),
                        new OA\Property(property: 'adresse', type: 'string', example: '10 Rue des Lilas, Paris'),
                        new OA\Property(property: 'telephone', type: 'string', example: '0612345678'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                        new OA\Property(property: 'type', type: 'string', example: 'Particulier'),
                        new OA\Property(property: 'civilite', type: 'string', example: 'M.'),
                        new OA\Property(property: 'matricule', type: 'string', example: 'EMP-00231'),
                        new OA\Property(
                            property: 'categorieIds',
                            type: 'array',
                            items: new OA\Items(type: 'integer'),
                            example: [1, 3, 5],
                            description: 'Tableau des IDs des catégories à associer (peut être vide)'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Correspondant créé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 12),
                        new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                        new OA\Property(property: 'telephone', type: 'string', example: '0612345678'),
                        new OA\Property(
                            property: 'categorieIds',
                            type: 'array',
                            items: new OA\Items(type: 'integer'),
                            example: [1, 3, 5]
                        ),
                        new OA\Property(
                            property: 'categorieNoms',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['Particulier', 'Client', 'VIP']
                        ),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PostCorrespondant');

        $data = json_decode($request->getContent(), true) ?? [];
        
        // âœ… Convertir les chaÃ®nes vides en null AVANT la validation
        $data = array_map(fn($value) => $value === '' ? null : $value, $data);
        
        $this->functionService->validate($data);

        try {
            $correspondant = new Correspondant();

            // ðŸ”¹ Gestion des catÃ©gories
            if (!empty($data['categorieIds']) && is_array($data['categorieIds'])) {
                foreach ($data['categorieIds'] as $categorieId) {
                    $categorie = $this->categorieCorrespondantRepository->find($categorieId);
                    if (!$categorie) {
                        return $this->json([
                            'code' => 404, 
                            'message' => "Catégorie avec l'ID {$categorieId} non trouvée."
                        ], 404);
                    }
                    $correspondant->addCategorie($categorie);
                }
                unset($data['categorieIds']); // Retirer pour Ã©viter qu'il soit traitÃ© par crudService
            }
 
            $correspondant = $this->crudService->postEntity($correspondant, $data);

            // âœ… LOG ASYNCHRONE - CrÃ©ation d'un correspondant
            $this->actionLogger->logCreate(
                'Correspondant',
                $correspondant->getId(),
                'Création d\'un nouveau correspondant',
                [
                    'correspondant' => [
                        'id' => $correspondant->getId(),
                        'nom' => $correspondant->getNom(),
                        'email' => $correspondant->getEmail(),
                        'telephone' => $correspondant->getTelephone(),
                        'adresse' => $correspondant->getAdresse(),
                        'type' => $correspondant->getType(),
                        'civilite' => $correspondant->getCivilite(),
                        'matricule' => $correspondant->getMatricule(),
                        'categories' => array_map(fn($cat) => [
                            'id' => $cat->getId(),
                            'nom' => $cat->getNom()
                        ], $correspondant->getCategories()->toArray()),
                    ],
                    'request_data' => array_diff_key($data, array_flip(['password'])) // Exclure les donnÃ©es sensibles
                ]
            );

            return $this->json($correspondant, 201, [], ['groups' => 'Get:Correspondant']);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}