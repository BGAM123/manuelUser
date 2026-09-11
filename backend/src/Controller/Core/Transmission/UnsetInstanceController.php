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
class UnsetInstanceController extends AbstractController
{
    public function __construct(
        private TransmissionRepository $transmissionRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/core/transmission/unset-instance', name: 'app_core_transmission_unset_instance', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/transmission/unset-instance',
        summary: 'Annuler l\'instance d\'une ou plusieurs transmissions',
        tags: ['Transmission'],
        description: 'Annule l\'instance d\'une ou plusieurs transmissions en définissant isinstance à false et en ajustant le statut selon : 1) "Classé" si le courrier est gelé, 2) "Reçu" si l\'accusé de réception est validé, 3) "Transmis" sinon.',
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
                        description: 'Liste des IDs des transmissions dont l\'instance doit être annulée'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Instances des transmissions annulées avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: '3 instance(s) annulées avec succès'),
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
            new OA\Response(response: 400, description: 'RequÃªte invalide (IDs manquants ou format incorrect).')
        ]
    )]
    public function unsetInstance(Request $request): Response
    {
        // Pas de vÃ©rification de permissions - tout utilisateur connectÃ© peut annuler l'instance
        
        // RÃ©cupÃ©rer les donnÃ©es JSON de la requÃªte
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

            // VÃ©rifier si la transmission n'est pas en instance
            if (!$transmission->isinstance()) {
                $results[] = [
                    'id' => $id,
                    'status' => 'warning',
                    'message' => 'La transmission n\'est pas en instance.',
                    'isinstance' => false,
                    'statut' => $transmission->getStatut()
                ];
                $errorCount++;
                continue;
            }

            try {
                // Retirer le marqueur d'instance
                $transmission->setInstance(false);
                
                // DÃ©terminer le nouveau statut selon la logique mÃ©tier
                $courrier = $transmission->getIdCourrier();
                
                if ($courrier && $courrier->isGeled()) {
                    // Si le courrier est gelé/classé
                    $nouveauStatut = 'Classé';
                } elseif ($transmission->isAccuseReception()) {
                    // Si l'accusé de réception est validé
                    $nouveauStatut = 'Reçu';
                } else {
                    // Par défaut, transmission en cours
                    $nouveauStatut = 'Transmis';
                }
                
                $transmission->setStatut($nouveauStatut);
                
                $results[] = [
                    'id' => $transmission->getId(),
                    'status' => 'success',
                    'isinstance' => false,
                    'statut' => $nouveauStatut,
                    'updatedAt' => $transmission->getUpdatedAt()?->format('c')
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Erreur lors de l\'annulation de l\'instance : ' . $e->getMessage()
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
                '%d instance(s) annulées avec succès%s',
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
