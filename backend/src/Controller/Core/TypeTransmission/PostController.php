<?php

namespace App\Controller\Core\TypeTransmission;

use App\Entity\Core\TypeTransmission;
use App\Repository\Core\TypeTransmissionRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'TypeTransmission')]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private TypeTransmissionRepository $typeTransmissionRepository,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/type-transmission', name: 'app_core_type_transmission_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/type-transmission',
        summary: 'Creer un type de transmission',
        description: 'Cree un nouveau type de transmission. Le nom est unique.',
        tags: ['TypeTransmission'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Transmission physique'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Cree',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'Type de transmission cree avec succes'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'nom', type: 'string', example: 'Transmission physique'),
                                new OA\Property(property: 'createdAt', type: 'string', example: '2026-04-20T12:00:00+00:00'),
                                new OA\Property(property: 'updatedAt', type: 'string', example: '2026-04-20T12:00:00+00:00'),
                            ]
                        ),
                    ],
                    example: [
                        'code' => 201,
                        'message' => 'Type de transmission cree avec succes',
                        'data' => [
                            'id' => 1,
                            'nom' => 'Transmission physique',
                            'createdAt' => '2026-04-20T12:00:00+00:00',
                            'updatedAt' => '2026-04-20T12:00:00+00:00',
                        ],
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Requete invalide',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Le champ \"nom\" est obligatoire.'),
                    ],
                    example: [
                        'code' => 400,
                        'message' => 'Le champ "nom" est obligatoire.',
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Acces non autorise'),
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostTypeTransmission');

        $data = json_decode($request->getContent(), true) ?? [];
        $this->functionService->validate($data);

        $nom = trim((string) ($data['nom'] ?? ''));
        if ($nom === '') {
            return $this->json(['code' => 400, 'message' => 'Le champ "nom" est obligatoire.'], 400);
        }
        $data['nom'] = $nom;

        $existing = $this->typeTransmissionRepository->findOneBy(['nom' => $nom]);
        if ($existing) {
            return $this->json([
                'code' => 400,
                'message' => 'Un type de transmission avec ce nom existe deja.',
                'data' => [
                    'nom' => $nom,
                    'type_existant_id' => $existing->getId(),
                ],
            ], 400);
        }

        try {
            $entity = new TypeTransmission();
            $entity = $this->crudService->postEntity($entity, $data);

            $this->actionLogger->logCreate(
                'TypeTransmission',
                $entity->getId(),
                'Creation d\'un type de transmission',
                [
                    'nom' => $entity->getNom(),
                ]
            );

            return $this->json([
                'code' => 201,
                'message' => 'Type de transmission cree avec succes',
                'data' => $entity,
            ], 201, [], ['groups' => 'Get:TypeTransmission']);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
