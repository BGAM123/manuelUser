<?php

namespace App\Controller\Core\Coffre;

use App\Repository\Core\CoffreRepository;
use App\Repository\Core\SalleRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Coffre")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private CoffreRepository $coffreRepository,
        private SalleRepository $salleRepository,
    ) {}

    #[Route('/core/coffre/{id}', name: 'app_core_coffre_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/coffre/{id}',
        summary: 'Mettre à  jour un coffre',
        tags: ['Coffre'],
        description: "Met à jour les informations d'un coffre existant.",
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
                        new OA\Property(property: 'nom', type: 'string', example: 'Coffre A2', description: 'Nouveau nom du coffre'),
                        new OA\Property(property: 'tailleMaximale', type: 'integer', example: 25, description: 'Nouvelle capacité maximale'),
                        new OA\Property(property: 'idSalle', type: 'integer', example: 2, description: 'Nouvel ID de salle'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: false, description: 'Nouveau statut actif'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coffre mis à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Coffre mis à jour avec succès'),
                        new OA\Property(
                            property: 'coffre',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'nom', type: 'string', example: 'Coffre A2'),
                                new OA\Property(property: 'nombrePlaceActuelle', type: 'integer', example: 10),
                                new OA\Property(property: 'tailleMaximale', type: 'integer', example: 25),
                                new OA\Property(property: 'idSalle', type: 'integer', example: 2),
                                new OA\Property(property: 'placesDisponibles', type: 'integer', example: 15),
                                new OA\Property(property: 'tauxRemplissage', type: 'number', format: 'float', example: 40.0),
                                new OA\Property(property: 'isPlein', type: 'boolean', example: false),
                                new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-12-02T11:30:00+00:00'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Coffre non trouvé.'),
            new OA\Response(
                response: 400,
                description: 'Données invalides ou nom déjà utilisé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Un autre coffre utilise déjà ce nom.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PatchCoffre');

        $coffre = $this->coffreRepository->find($id);

        if (!$coffre || $coffre->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Coffre non trouvé.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['code' => 400, 'message' => 'JSON invalide: ' . json_last_error_msg()], 400);
        }

        $this->functionService->validate($data);
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'isDelete', 'nombrePlaceActuelle']);

        try {
            // VÃ©rifier l'unicitÃ© du nom dans la mÃªme salle
            // Si le nom OU la salle change, il faut vÃ©rifier l'unicitÃ©
            $nomChanged = isset($data['nom']) && $data['nom'] !== $coffre->getNom();
            $salleChanged = isset($data['idSalle']) && $data['idSalle'] !== $coffre->getIdSalle();
            
            if ($nomChanged || $salleChanged) {
                $newNom = $data['nom'] ?? $coffre->getNom();
                $newIdSalle = $data['idSalle'] ?? $coffre->getIdSalle();
                
                $existingCoffre = $this->coffreRepository->findOneBy([
                    'nom' => $newNom,
                    'idSalle' => $newIdSalle
                ]);
                
                if ($existingCoffre && $existingCoffre->getId() !== $coffre->getId()) {
                    return $this->json(['code' => 400, 'message' => 'Un autre coffre avec ce nom existe déjà dans cette salle.'], 400);
                }
            }

            // VÃ©rifier que la salle existe si idSalle est modifiÃ©
            if (isset($data['idSalle'])) {
                $salle = $this->salleRepository->find($data['idSalle']);
                if (!$salle || $salle->isDelete()) {
                    return $this->json(['code' => 404, 'message' => 'La salle spécifiée n\'existe pas.'], 404);
                }
            }

            // VÃ©rifier que la nouvelle tailleMaximale ne soit pas infÃ©rieure au nombrePlaceActuelle
            if (isset($data['tailleMaximale'])) {
                if ($data['tailleMaximale'] < $coffre->getNombrePlaceActuelle()) {
                    return $this->json([
                        'code' => 400, 
                        'message' => 'La taille maximale ne peut pas Ãªtre inférieure au nombre de places actuellement occupées (' . $coffre->getNombrePlaceActuelle() . ').'
                    ], 400);
                }

                // VÃ©rifier que la valeur est positive
                if ($data['tailleMaximale'] < 0) {
                    return $this->json([
                        'code' => 400, 
                        'message' => 'La taille maximale doit être positive.'
                    ], 400);
                }
            }

            // Mettre Ã  jour
            $updatedCoffre = $this->crudService->patchEntity($coffre, $data);

            return $this->json([
                'message' => 'Coffre mis à jour avec succès',
                'coffre' => [
                    'id' => $updatedCoffre->getId(),
                    'nom' => $updatedCoffre->getNom(),
                    'nombrePlaceActuelle' => $updatedCoffre->getNombrePlaceActuelle(),
                    'tailleMaximale' => $updatedCoffre->getTailleMaximale(),
                    'idSalle' => $updatedCoffre->getIdSalle(),
                    'placesDisponibles' => $updatedCoffre->getPlacesDisponibles(),
                    'tauxRemplissage' => $updatedCoffre->getTauxRemplissage(),
                    'isPlein' => $updatedCoffre->isPlein(),
                    'isActive' => $updatedCoffre->isActive(),
                    'isDelete' => $updatedCoffre->isDelete(),
                    'updatedAt' => $updatedCoffre->getUpdatedAt()?->format('c'),
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
