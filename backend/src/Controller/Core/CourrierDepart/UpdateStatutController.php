<?php

namespace App\Controller\Core\CourrierDepart;

use App\Repository\Cour\CourrierDepartRepository;
use App\Service\Core\CrudService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierDepart")]
class UpdateStatutController extends AbstractController
{
    public function __construct(
        private CourrierDepartRepository $courrierDepartRepository,
        private CrudService $crudService,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/courrier-depart/{id<\\d+>}/statut', name: 'app_core_courrier_depart_update_statut', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/courrier-depart/{id}/statut',
        summary: 'Mettre a jour le statut d\'un courrier de depart',
        description: 'Met a jour uniquement le champ statut d\'un courrier de depart.',
        tags: ['CourrierDepart'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier de depart',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'statut', type: 'string', example: 'Transmis')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statut mis a jour avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Statut mis a jour avec succes.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Champ statut manquant ou invalide.'),
            new OA\Response(response: 404, description: 'Courrier de depart non trouve.'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PatchCourrierDepart');

        $courrierDepart = $this->courrierDepartRepository->find($id);
        if (!$courrierDepart) {
            return $this->json(['code' => 404, 'message' => 'Courrier de depart non trouve.'], 404);
        }

        $data = $request->request->all();
        if (empty($data)) {
            $decoded = json_decode($request->getContent(), true);
            $data = is_array($decoded) ? $decoded : [];
        }

        $statut = $data['statut'] ?? null;
        if ($statut === null || trim((string) $statut) === '') {
            return $this->json(['code' => 400, 'message' => 'Champ statut manquant ou invalide.'], 400);
        }

        try {
            $courrierDepart = $this->crudService->patchEntity($courrierDepart, [
                'statut' => $statut,
            ]);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }

        return $this->json([
            'code' => 200,
            'message' => 'Statut mis a jour avec succes.',
            'data' => [
                'id' => $courrierDepart->getId(),
                'statut' => $courrierDepart->getStatut(),
            ],
        ], 200);
    }
}
