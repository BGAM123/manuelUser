<?php

namespace App\Controller\Core\Transmission;

use App\Repository\Cour\TransmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Transmission")]
class SetInstanceController extends AbstractController
{
    public function __construct(
        private TransmissionRepository $transmissionRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/core/transmission/set-instance', name: 'app_core_transmission_set_instance', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/transmission/set-instance',
        summary: 'Mettre une ou plusieurs transmissions en instance',
        tags: ['Transmission'],
        description: 'Met une ou plusieurs transmissions en instance en définissant isinstance à true et le statut à "En instance". Une transmission en instance nécessite une action ou une attention particulière.',
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
                        description: 'Liste des IDs des transmissions à mettre en instance'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transmissions mises en instance avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: '3 transmission(s) mise(s) en instance avec succès'),
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
                                    new OA\Property(property: 'statut', type: 'string', example: 'En instance')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide (IDs manquants ou format incorrect).')
        ]
    )]
    public function setInstance(Request $request): Response
    {
        // Pas de vérification de permissions - tout utilisateur connecté peut mettre en instance
        
        // Récupérer les données JSON de la requête
        $data = json_decode($request->getContent(), true);
        
        // Valider que les IDs sont fournis
        if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "ids" est requis et doit être un tableau non vide.'
            ], 400);
        }

        $ids = $data['ids'];
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($ids as $id) {
            // Valider que l'ID est un entier
            if (!is_numeric($id)) {
                $results[] = [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'ID invalide (doit être un nombre entier).'
                ];
                $errorCount++;
                continue;
            }

            $transmission = $this->transmissionRepository->find((int)$id);

            if (!$transmission) {
                $results[] = [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Transmission non trouvée.'
                ];
                $errorCount++;
                continue;
            }

            // VÃ©rifier si la transmission est dÃ©jÃ  en instance
            if ($transmission->isinstance()) {
                $results[] = [
                    'id' => $id,
                    'status' => 'warning',
                    'message' => 'La transmission est déjà en instance.',
                    'isinstance' => true,
                    'statut' => $transmission->getStatut()
                ];
                $errorCount++;
                continue;
            }

            try {
                // Mettre la transmission en instance
                $transmission->setInstance(true);
                
                // Changer le statut Ã  "En instance"
                $transmission->setStatut('En instance');
                
                $results[] = [
                    'id' => $transmission->getId(),
                    'status' => 'success',
                    'isinstance' => true,
                    'statut' => 'En instance',
                    'updatedAt' => $transmission->getUpdatedAt()?->format('c')
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

        // Sauvegarder toutes les modifications en une seule transaction
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
                '%d transmission(s) mise(s) en instance avec succès%s',
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
