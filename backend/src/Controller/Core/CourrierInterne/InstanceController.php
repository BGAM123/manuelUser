<?php

namespace App\Controller\Core\CourrierInterne;

use App\Repository\Cour\CourrierInterneRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierInterne")]
class InstanceController extends AbstractController
{
    public function __construct(
        private CourrierInterneRepository $courrierInterneRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/core/courrier-interne/set-instance', name: 'app_core_courrier_interne_set_instance', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/courrier-interne/set-instance',
        summary: 'Mettre un ou plusieurs courriers internes en instance',
        tags: ['CourrierInterne'],
        description: 'Met un ou plusieurs courriers internes en instance en definissant isinstance a true.',
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['ids'],
                properties: [
                    new OA\Property(
                        property: 'ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3],
                        description: 'Liste des IDs des courriers internes a mettre en instance'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courriers internes mis en instance avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: '3 courrier(s) interne(s) mis en instance avec succes'),
                        new OA\Property(property: 'total', type: 'integer', example: 3),
                        new OA\Property(property: 'success', type: 'integer', example: 3),
                        new OA\Property(property: 'errors', type: 'integer', example: 0),
                        new OA\Property(
                            property: 'details',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                                    new OA\Property(property: 'isinstance', type: 'boolean', example: true),
                                    new OA\Property(property: 'statut', type: 'string', example: 'Instancie')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requete invalide (IDs manquants ou format incorrect).')
        ]
    )]
    public function setInstance(Request $request): Response
    {
        // Pas de verification de permissions - tout utilisateur connecte peut mettre en instance

        $data = json_decode($request->getContent(), true);

        if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "ids" est requis et doit etre un tableau non vide.'
            ], 400);
        }

        $ids = $data['ids'];
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                $results[] = [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'ID invalide (doit etre un nombre entier).'
                ];
                $errorCount++;
                continue;
            }

            $courrierInterne = $this->courrierInterneRepository->find((int) $id);

            if (!$courrierInterne) {
                $results[] = [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Courrier interne non trouve.'
                ];
                $errorCount++;
                continue;
            }

            if ($courrierInterne->isinstance()) {
                $results[] = [
                    'id' => $id,
                    'status' => 'warning',
                    'message' => 'Le courrier interne est deja en instance.',
                    'isinstance' => true
                ];
                $errorCount++;
                continue;
            }

            try {
                $courrierInterne->setInstance(true);
                $courrierInterne->refreshStatut();

                $results[] = [
                    'id' => $courrierInterne->getId(),
                    'status' => 'success',
                    'isinstance' => true,
                    'statut' => $courrierInterne->getStatut(),
                    'updatedAt' => $courrierInterne->getUpdatedAt()?->format('c')
                ];
                $successCount++;
            } catch (\Exception $e) {
                $results[] = [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Erreur lors de la mise en instance : ' . $e->getMessage()
                ];
                $errorCount++;
            }
        }

        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage()
            ], 500);
        }

        return $this->json([
            'message' => sprintf(
                '%d courrier(s) interne(s) mis en instance avec succes%s',
                $successCount,
                $errorCount > 0 ? sprintf(', %d erreur(s)', $errorCount) : ''
            ),
            'total' => count($ids),
            'success' => $successCount,
            'errors' => $errorCount,
            'details' => $results
        ], 200);
    }

    #[Route('/core/courrier-interne/unset-instance', name: 'app_core_courrier_interne_unset_instance', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/courrier-interne/unset-instance',
        summary: 'Desinstancier un ou plusieurs courriers internes',
        tags: ['CourrierInterne'],
        description: 'Annule l\'instance d\'un ou plusieurs courriers internes en definissant isinstance a false.',
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['ids'],
                properties: [
                    new OA\Property(
                        property: 'ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3],
                        description: 'Liste des IDs des courriers internes a desinstancier'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Instances des courriers internes annulees avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: '3 instance(s) annulee(s) avec succes'),
                        new OA\Property(property: 'total', type: 'integer', example: 3),
                        new OA\Property(property: 'success', type: 'integer', example: 3),
                        new OA\Property(property: 'errors', type: 'integer', example: 0),
                        new OA\Property(
                            property: 'details',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                                    new OA\Property(property: 'isinstance', type: 'boolean', example: false),
                                    new OA\Property(property: 'statut', type: 'string', example: 'Transmis')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requete invalide (IDs manquants ou format incorrect).')
        ]
    )]
    public function unsetInstance(Request $request): Response
    {
        // Pas de verification de permissions - tout utilisateur connecte peut desinstancier

        $data = json_decode($request->getContent(), true);

        if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "ids" est requis et doit etre un tableau non vide.'
            ], 400);
        }

        $ids = $data['ids'];
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                $results[] = [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'ID invalide (doit etre un nombre entier).'
                ];
                $errorCount++;
                continue;
            }

            $courrierInterne = $this->courrierInterneRepository->find((int) $id);

            if (!$courrierInterne) {
                $results[] = [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Courrier interne non trouve.'
                ];
                $errorCount++;
                continue;
            }

            if (!$courrierInterne->isinstance()) {
                $results[] = [
                    'id' => $id,
                    'status' => 'warning',
                    'message' => 'Le courrier interne n\'est pas en instance.',
                    'isinstance' => false
                ];
                $errorCount++;
                continue;
            }

            try {
                $courrierInterne->setInstance(false);
                $courrierInterne->refreshStatut();

                $results[] = [
                    'id' => $courrierInterne->getId(),
                    'status' => 'success',
                    'isinstance' => false,
                    'statut' => $courrierInterne->getStatut(),
                    'updatedAt' => $courrierInterne->getUpdatedAt()?->format('c')
                ];
                $successCount++;
            } catch (\Exception $e) {
                $results[] = [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Erreur lors de la desinstanciation : ' . $e->getMessage()
                ];
                $errorCount++;
            }
        }

        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage()
            ], 500);
        }

        return $this->json([
            'message' => sprintf(
                '%d instance(s) annulee(s) avec succes%s',
                $successCount,
                $errorCount > 0 ? sprintf(', %d erreur(s)', $errorCount) : ''
            ),
            'total' => count($ids),
            'success' => $successCount,
            'errors' => $errorCount,
            'details' => $results
        ], 200);
    }
}
