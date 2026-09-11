<?php

namespace App\Controller\Core\Coffre;

use App\Entity\Core\Coffre;
use App\Repository\Core\CoffreRepository;
use App\Repository\Core\SalleRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Coffre")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private CoffreRepository $coffreRepository,
        private SalleRepository $salleRepository,
    ) {}

    #[Route('/core/coffre', name: 'app_core_coffre_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/coffre',
        summary: 'Créer un ou plusieurs coffres',
        tags: ['Coffre'],
        description: "Crée un ou plusieurs coffres dans une salle. Vous pouvez créer plusieurs coffres en une seule requête en fournissant un tableau.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'idSalle', type: 'integer', example: 1, description: 'ID de la salle où se trouvent les coffres (obligatoire)'),
                        new OA\Property(
                            property: 'coffres',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'nom', type: 'string', example: 'Coffre A1', description: 'Nom du coffre (obligatoire et unique par salle)'),
                                    new OA\Property(property: 'tailleMaximale', type: 'integer', example: 20, description: 'Capacité maximale (optionnel, défaut: 20)'),
                                ],
                                required: ['nom']
                            ),
                            description: 'Tableau des coffres à créer',
                            example: [
                                ['nom' => 'Coffre A1', 'tailleMaximale' => 20],
                                ['nom' => 'Coffre A2', 'tailleMaximale' => 25],
                                ['nom' => 'Coffre A3']
                            ]
                        ),
                    ],
                    required: ['idSalle', 'coffres']
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Coffre(s) crée(s) avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: '3 coffre(s) crée(s) avec succès'),
                        new OA\Property(property: 'total', type: 'integer', example: 3),
                        new OA\Property(
                            property: 'coffres',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Coffre A1'),
                                    new OA\Property(property: 'nombrePlaceActuelle', type: 'integer', example: 0),
                                    new OA\Property(property: 'tailleMaximale', type: 'integer', example: 20),
                                    new OA\Property(property: 'idSalle', type: 'integer', example: 1),
                                    new OA\Property(property: 'placesDisponibles', type: 'integer', example: 20),
                                    new OA\Property(property: 'isPlein', type: 'boolean', example: false),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Données invalides ou nom déjà existant dans la salle.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Ce nom de coffre existe déjà dans cette salle.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Salle non trouvée.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'La salle spécifiée n\'existe pas.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostCoffre');

        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['code' => 400, 'message' => 'JSON invalide: ' . json_last_error_msg()], 400);
        }

        try {
            // VÃ©rifier que idSalle est fourni
            if (empty($data['idSalle'])) {
                return $this->json(['code' => 400, 'message' => 'L\'ID de la salle est obligatoire.'], 400);
            }

            // VÃ©rifier que le tableau coffres est fourni et non vide
            if (empty($data['coffres']) || !is_array($data['coffres'])) {
                return $this->json(['code' => 400, 'message' => 'Le tableau des coffres est obligatoire et ne peut pas être vide.'], 400);
            }

            // VÃ©rifier que la salle existe
            $salle = $this->salleRepository->find($data['idSalle']);
            if (!$salle || $salle->isDelete()) {
                return $this->json(['code' => 404, 'message' => 'La salle spécifiée n\'existe pas.'], 404);
            }

            $coffresCreated = [];
            $erreurs = [];

            // Parcourir chaque coffre Ã  crÃ©er
            foreach ($data['coffres'] as $index => $coffreData) {
                // VÃ©rifier que le nom est fourni
                if (empty($coffreData['nom'])) {
                    $erreurs[] = "Coffre #" . ($index + 1) . ": Le nom est obligatoire.";
                    continue;
                }

                // VÃ©rifier l'unicitÃ© du nom DANS LA MÃŠME SALLE
                $existingCoffre = $this->coffreRepository->findOneBy([
                    'nom' => $coffreData['nom'],
                    'idSalle' => $data['idSalle']
                ]);
                if ($existingCoffre) {
                    $erreurs[] = "Coffre #" . ($index + 1) . " ({$coffreData['nom']}): Ce nom existe déjà dans cette salle.";
                    continue;
                }

                // DÃ©finir la taille maximale par dÃ©faut si non fournie
                if (!isset($coffreData['tailleMaximale']) || $coffreData['tailleMaximale'] === null) {
                    $coffreData['tailleMaximale'] = 20;
                }

                // VÃ©rifier que tailleMaximale est positive
                if ($coffreData['tailleMaximale'] < 0) {
                    $erreurs[] = "Coffre #" . ($index + 1) . " ({$coffreData['nom']}): La taille maximale doit être positive.";
                    continue;
                }

                // PrÃ©parer les donnÃ©es du coffre
                $coffreDataToCreate = [
                    'nom' => $coffreData['nom'],
                    'nombrePlaceActuelle' => 0,
                    'tailleMaximale' => $coffreData['tailleMaximale'],
                    'idSalle' => $data['idSalle'],
                ];

                // CrÃ©er le coffre
                $coffre = new Coffre();
                $coffre = $this->crudService->postEntity($coffre, $coffreDataToCreate);

                $coffresCreated[] = [
                    'id' => $coffre->getId(),
                    'nom' => $coffre->getNom(),
                    'nombrePlaceActuelle' => $coffre->getNombrePlaceActuelle(),
                    'tailleMaximale' => $coffre->getTailleMaximale(),
                    'idSalle' => $coffre->getIdSalle(),
                    'placesDisponibles' => $coffre->getPlacesDisponibles(),
                    'tauxRemplissage' => $coffre->getTauxRemplissage(),
                    'isPlein' => $coffre->isPlein(),
                    'isActive' => $coffre->isActive(),
                    'createdAt' => $coffre->getCreatedAt()?->format('c'),
                ];
            }

            // Si aucun coffre n'a Ã©tÃ© crÃ©Ã© et qu'il y a des erreurs
            if (empty($coffresCreated) && !empty($erreurs)) {
                return $this->json([
                    'code' => 400,
                    'message' => 'Aucun coffre n\'a pu être crée.',
                    'erreurs' => $erreurs
                ], 400);
            }

            // PrÃ©parer la rÃ©ponse
            $response = [
                'message' => count($coffresCreated) . ' coffre(s) crée(s) avec succès',
                'total' => count($coffresCreated),
                'coffres' => $coffresCreated
            ];

            // Ajouter les erreurs s'il y en a
            if (!empty($erreurs)) {
                $response['avertissements'] = $erreurs;
                $response['message'] .= ' (avec ' . count($erreurs) . ' erreur(s))';
            }

            return $this->json($response, 201);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
