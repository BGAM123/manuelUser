<?php

namespace App\Controller\Core\Reponse;

use App\Repository\Cour\ReponseRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Reponse")]
class InstanceController extends AbstractController
{
    public function __construct(
        private ReponseRepository $reponseRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/core/reponse/set-instance', name: 'app_core_reponse_set_instance', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/reponse/set-instance',
        summary: 'Mettre une ou plusieurs reponses en instance',
        tags: ['Reponse'],
        description: 'Met une ou plusieurs reponses en instance en definissant isinstance a true.',
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
                        description: 'Liste des IDs des reponses a mettre en instance'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reponses mises en instance avec succes.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: '3 reponse(s) mise(s) en instance avec succes'),
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
                                    new OA\Property(property: 'statut', type: 'string', example: 'Instancié')
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

            $reponse = $this->reponseRepository->find((int) $id);

            if (!$reponse) {
                $results[] = [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Reponse non trouvee.'
                ];
                $errorCount++;
                continue;
            }

            if ($reponse->isinstance()) {
                $results[] = [
                    'id' => $id,
                    'status' => 'warning',
                    'message' => 'La reponse est deja en instance.',
                    'isinstance' => true
                ];
                $errorCount++;
                continue;
            }

            try {
                $reponse->setInstance(true);
                $reponse->refreshStatut();

                $results[] = [
                    'id' => $reponse->getId(),
                    'status' => 'success',
                    'isinstance' => true,
                    'statut' => $reponse->getStatut(),
                    'updatedAt' => $reponse->getUpdatedAt()?->format('c')
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
                '%d reponse(s) mise(s) en instance avec succes%s',
                $successCount,
                $errorCount > 0 ? sprintf(', %d erreur(s)', $errorCount) : ''
            ),
            'total' => count($ids),
            'success' => $successCount,
            'errors' => $errorCount,
            'details' => $results
        ], 200);
    }

    #[Route('/core/reponse/unset-instance', name: 'app_core_reponse_unset_instance', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/reponse/unset-instance',
        summary: 'Desinstancier une ou plusieurs reponses',
        tags: ['Reponse'],
        description: 'Annule l\'instance d\'une ou plusieurs reponses en definissant isinstance a false.',
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
                        description: 'Liste des IDs des reponses a desinstancier'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Instances des reponses annulees avec succes.',
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

            $reponse = $this->reponseRepository->find((int) $id);

            if (!$reponse) {
                $results[] = [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Reponse non trouvee.'
                ];
                $errorCount++;
                continue;
            }

            if (!$reponse->isinstance()) {
                $results[] = [
                    'id' => $id,
                    'status' => 'warning',
                    'message' => 'La reponse n\'est pas en instance.',
                    'isinstance' => false
                ];
                $errorCount++;
                continue;
            }

            try {
                $reponse->setInstance(false);
                $reponse->refreshStatut();

                $results[] = [
                    'id' => $reponse->getId(),
                    'status' => 'success',
                    'isinstance' => false,
                    'statut' => $reponse->getStatut(),
                    'updatedAt' => $reponse->getUpdatedAt()?->format('c')
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
