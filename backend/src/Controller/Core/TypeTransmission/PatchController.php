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
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private TypeTransmissionRepository $typeTransmissionRepository,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/type-transmission/{id<([1-9][0-9]*)>}', name: 'app_core_type_transmission_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/type-transmission/{id}',
        summary: 'Modifier un type de transmission',
        description: 'Met a jour les informations d\'un type de transmission existant.',
        tags: ['TypeTransmission'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'Transmission numerique'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mise a jour reussie.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Transmission numerique'),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2026-04-20T12:00:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', example: '2026-04-20T12:05:00+00:00'),
                    ],
                    example: [
                        'id' => 1,
                        'nom' => 'Transmission numerique',
                        'createdAt' => '2026-04-20T12:00:00+00:00',
                        'updatedAt' => '2026-04-20T12:05:00+00:00',
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
            new OA\Response(response: 404, description: 'Type de transmission non trouve.'),
            new OA\Response(response: 401, description: 'Acces non autorise.'),
        ]
    )]
    public function patch(Request $request, ?TypeTransmission $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PatchTypeTransmission');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Type de transmission non trouve.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['code' => 400, 'message' => 'Donnees invalides.'], 400);
        }

        $this->functionService->validate($data);
        $data = $this->functionService->excludeFields($data, ['id', 'createdAt', 'updatedAt', 'isDelete']);

        if (array_key_exists('nom', $data)) {
            $nom = trim((string) ($data['nom'] ?? ''));
            if ($nom === '') {
                return $this->json(['code' => 400, 'message' => 'Le champ "nom" est obligatoire.'], 400);
            }

            $existing = $this->typeTransmissionRepository->createQueryBuilder('t')
                ->where('t.nom = :nom')
                ->andWhere('t.id != :currentId')
                ->setParameter('nom', $nom)
                ->setParameter('currentId', $entity->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if ($existing) {
                return $this->json([
                    'code' => 400,
                    'message' => 'Un autre type de transmission avec ce nom existe deja.',
                    'data' => [
                        'nom' => $nom,
                        'type_existant_id' => $existing->getId(),
                    ],
                ], 400);
            }

            $data['nom'] = $nom;
        }

        $oldData = [
            'nom' => $entity->getNom(),
            'isDelete' => $entity->isDelete(),
        ];

        try {
            $entity = $this->crudService->patchEntity($entity, $data);

            $newData = [
                'nom' => $entity->getNom(),
                'isDelete' => $entity->isDelete(),
            ];

            $this->actionLogger->logUpdate(
                'TypeTransmission',
                $entity->getId(),
                'Mise a jour d\'un type de transmission',
                [
                    'before' => $oldData,
                    'after' => $newData,
                ]
            );

            return $this->json($entity, 200, [], ['groups' => 'Get:TypeTransmission']);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la mise a jour : ' . $e->getMessage(),
            ], 500);
        }
    }
}
