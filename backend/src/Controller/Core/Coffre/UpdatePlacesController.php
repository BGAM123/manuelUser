<?php

namespace App\Controller\Core\Coffre;

use App\Repository\Core\CoffreRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Coffre")]
class UpdatePlacesController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private CoffreRepository $coffreRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/core/coffre/{id}/places', name: 'app_core_coffre_update_places', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/coffre/{id}/places',
        summary: 'Incrémenter ou décrémenter le nombre de places occupées',
        tags: ['Coffre'],
        description: "Permet d'ajouter ou retirer des places dans un coffre. Le nombre de places actuelles s'incrémentent ou se décrementent automatiquement.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant du coffre', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'action',
                            type: 'string',
                            enum: ['increment', 'decrement'],
                            example: 'increment',
                            description: 'Action à effectuer : increment (ajouter) ou decrement (retirer)'
                        ),
                        new OA\Property(
                            property: 'quantite',
                            type: 'integer',
                            example: 1,
                            description: 'Nombre de places à  ajouter ou retirer (défaut: 1)'
                        ),
                    ],
                    required: ['action']
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Nombre de places mis à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: '2 place(s) ajoutée(s) avec succès'),
                        new OA\Property(
                            property: 'coffre',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'nom', type: 'string', example: 'Coffre A1'),
                                new OA\Property(property: 'nombrePlaceActuelle', type: 'integer', example: 7),
                                new OA\Property(property: 'tailleMaximale', type: 'integer', example: 20),
                                new OA\Property(property: 'placesDisponibles', type: 'integer', example: 13),
                                new OA\Property(property: 'tauxRemplissage', type: 'number', format: 'float', example: 35.0),
                                new OA\Property(property: 'isPlein', type: 'boolean', example: false),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Coffre non trouvé.'),
            new OA\Response(
                response: 400,
                description: 'Action invalide ou capacité dépassée.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Le coffre est plein. Impossible d\'ajouter plus de places.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function updatePlaces(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'UpdatePlacesCoffre');

        $coffre = $this->coffreRepository->find($id);

        if (!$coffre || $coffre->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Coffre non trouvé.'], 404);
        }

        if (!$coffre->isActive()) {
            return $this->json(['code' => 400, 'message' => 'Le coffre est inactif.'], 400);
        }

        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['code' => 400, 'message' => 'JSON invalide: ' . json_last_error_msg()], 400);
        }

        // VÃ©rifier que l'action est fournie
        if (empty($data['action'])) {
            return $this->json(['code' => 400, 'message' => 'L\'action est obligatoire (increment ou decrement).'], 400);
        }

        $action = strtolower($data['action']);
        if (!in_array($action, ['increment', 'decrement'])) {
            return $this->json(['code' => 400, 'message' => 'Action invalide. Utilisez "increment" ou "decrement".'], 400);
        }

        // QuantitÃ© par dÃ©faut : 1
        $quantite = isset($data['quantite']) ? (int) $data['quantite'] : 1;

        if ($quantite <= 0) {
            return $this->json(['code' => 400, 'message' => 'La quantité doit être supérieure à 0.'], 400);
        }

        try {
            $nombrePlaceActuelle = $coffre->getNombrePlaceActuelle();
            $nouveauNombre = $nombrePlaceActuelle;

            if ($action === 'increment') {
                $nouveauNombre = $nombrePlaceActuelle + $quantite;
                
                // VÃ©rifier que Ã§a ne dÃ©passe pas la capacitÃ©
                if ($nouveauNombre > $coffre->getTailleMaximale()) {
                    return $this->json([
                        'code' => 400,
                        'message' => "Impossible d'ajouter {$quantite} place(s). Le coffre ne peut contenir que {$coffre->getTailleMaximale()} places (actuellement {$nombrePlaceActuelle}).",
                        'placesDisponibles' => $coffre->getPlacesDisponibles()
                    ], 400);
                }

                $message = "{$quantite} place(s) ajoutée(s) avec succès";
            } else { // decrement
                $nouveauNombre = $nombrePlaceActuelle - $quantite;
                
                // Vérifier que ça ne devient pas négatif
                if ($nouveauNombre < 0) {
                    return $this->json([
                        'code' => 400,
                        'message' => "Impossible de retirer {$quantite} place(s). Le coffre contient actuellement {$nombrePlaceActuelle} place(s)."
                    ], 400);
                }

                $message = "{$quantite} place(s) retirée(s) avec succès";
            }

            // Mettre à jour le nombre de places
            $coffre->setNombrePlaceActuelle($nouveauNombre);
            $this->entityManager->flush();

            return $this->json([
                'message' => $message,
                'coffre' => [
                    'id' => $coffre->getId(),
                    'nom' => $coffre->getNom(),
                    'nombrePlaceActuelle' => $coffre->getNombrePlaceActuelle(),
                    'tailleMaximale' => $coffre->getTailleMaximale(),
                    'placesDisponibles' => $coffre->getPlacesDisponibles(),
                    'tauxRemplissage' => $coffre->getTauxRemplissage(),
                    'isPlein' => $coffre->isPlein(),
                    'updatedAt' => $coffre->getUpdatedAt()?->format('c'),
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
