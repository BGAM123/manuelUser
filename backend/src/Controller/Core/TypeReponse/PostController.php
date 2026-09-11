<?php

namespace App\Controller\Core\TypeReponse;

use App\Entity\Core\TypeReponse;
use App\Repository\Core\TypeReponseRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeReponse")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private TypeReponseRepository $typeReponseRepository,
    ) {}

    #[Route('/core/type-reponse', name: 'app_core_type_reponse_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/type-reponse',
        summary: 'Créer un nouveau type de réponse',
        tags: ['TypeReponse'],
        description: "Crée un nouveau type de réponse pour les courriers.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'Accusé de réception', description: 'Nom du type de réponse (obligatoire)'),
                        new OA\Property(property: 'description', type: 'string', example: 'Confirmation de réception d\'un courrier', description: 'Description détaillée (optionnel)'),
                        new OA\Property(property: 'idTypeParent', type: 'integer', example: 1, description: 'ID du type parent pour créer un sous-type (optionnel)'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true, description: 'Statut actif (optionnel, true par défaut)'),
                    ],
                    required: ['nom']
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Type de réponse créé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Accusé de réception'),
                        new OA\Property(property: 'description', type: 'string', example: 'Confirmation de réception d\'un courrier'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-11-04T10:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-11-04T10:30:00+00:00'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Données invalides ou nom déjà existant.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Un type de réponse avec ce nom existe déjà.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostTypeReponse');

        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['code' => 400, 'message' => 'JSON invalide: ' . json_last_error_msg()], 400);
        }

        $this->functionService->validate($data);
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt']);

        try {
            // VÃ©rifier l'unicitÃ© du nom
            if (empty($data['nom'])) {
                return $this->json(['code' => 400, 'message' => 'Le nom est obligatoire.'], 400);
            }

            $existingType = $this->typeReponseRepository->findByNom($data['nom']);
            if ($existingType) {
                return $this->json(['code' => 400, 'message' => 'Un type de réponse avec ce nom existe déjà.'], 400);
            }

            // Gestion du type parent (sous-type)
            if (!empty($data['idTypeParent'])) {
                $typeParent = $this->typeReponseRepository->find($data['idTypeParent']);
                if (!$typeParent) {
                    return $this->json(['code' => 404, 'message' => 'Type parent introuvable.'], 404);
                }
                $data['typeParent'] = $typeParent;
                unset($data['idTypeParent']); // Ã‰viter les conflits
            }

            // CrÃ©er le type de rÃ©ponse
            $typeReponse = new TypeReponse();
            $typeReponse = $this->crudService->postEntity($typeReponse, $data);

            return $this->json($typeReponse, 201, [], ['groups' => 'Get:TypeReponse']);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
