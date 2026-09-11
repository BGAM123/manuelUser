<?php

namespace App\Controller\Core\Salle;

use App\Entity\Core\Salle;
use App\Repository\Core\SalleRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Salle")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private SalleRepository $salleRepository,
    ) {}

    #[Route('/core/salle', name: 'app_core_salle_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/salle',
        summary: 'Créer une nouvelle salle',
        tags: ['Salle'],
        description: "Crée une nouvelle salle.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'Salle de réunion A', description: 'Nom de la salle (obligatoire)'),
                    ],
                    required: ['nom']
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Salle créée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Salle de réunion A'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-12-02T10:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-12-02T10:30:00+00:00'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Données invalides ou nom de salle déjà existant.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Ce nom de salle existe déjà.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostSalle');

        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['code' => 400, 'message' => 'JSON invalide: ' . json_last_error_msg()], 400);
        }

        $this->functionService->validate($data);
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'isDelete', 'isActive']);

        try {
            // VÃ©rifier l'unicitÃ© du nom
            if (empty($data['nom'])) {
                return $this->json(['code' => 400, 'message' => 'Le nom de la salle est obligatoire.'], 400);
            }

            $existingSalle = $this->salleRepository->findOneBy(['nom' => $data['nom']]);
            if ($existingSalle) {
                return $this->json(['code' => 400, 'message' => 'Ce nom de salle existe déjà.'], 400);
            }

            // Créer la salle
            $salle = new Salle();
            $salle = $this->crudService->postEntity($salle, $data);

            return $this->json($salle, 201, [], ['groups' => 'Get:Salle']);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
