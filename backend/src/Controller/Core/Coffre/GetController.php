<?php

namespace App\Controller\Core\Coffre;

use App\Repository\Core\CoffreRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Coffre")]
class GetController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private CoffreRepository $coffreRepository,
    ) {}

    #[Route('/core/coffre/{id}', name: 'app_core_coffre_get', methods: ['GET'])]
    #[OA\Get(
        path: '/core/coffre/{id}',
        summary: 'Récupérer un coffre par son ID',
        tags: ['Coffre'],
        description: "Récupère les détails d'un coffre spécifique avec des informations calculées (places disponibles, taux de remplissage, etc.).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant du coffre', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coffre trouvé.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Coffre A1'),
                        new OA\Property(property: 'nombrePlaceActuelle', type: 'integer', example: 5),
                        new OA\Property(property: 'tailleMaximale', type: 'integer', example: 20),
                        new OA\Property(property: 'idSalle', type: 'integer', example: 1),
                        new OA\Property(
                            property: 'autresId',
                            type: 'array',
                            items: new OA\Items(type: 'integer'),
                            example: [101, 102, 103],
                            description: 'Tableau d\'autres IDs associÃ©s'
                        ),
                        new OA\Property(property: 'placesDisponibles', type: 'integer', example: 15, description: 'Nombre de places restantes'),
                        new OA\Property(property: 'tauxRemplissage', type: 'number', format: 'float', example: 25.0, description: 'Pourcentage de remplissage'),
                        new OA\Property(property: 'isPlein', type: 'boolean', example: false, description: 'Indique si le coffre est plein'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-12-02T10:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-12-02T10:30:00+00:00'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Coffre non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function data(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCoffre');

        $coffre = $this->coffreRepository->find($id);

        if (!$coffre || $coffre->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Coffre non trouvé.'], 404);
        }

        $responseData = [
            'id' => $coffre->getId(),
            'nom' => $coffre->getNom(),
            'nombrePlaceActuelle' => $coffre->getNombrePlaceActuelle(),
            'tailleMaximale' => $coffre->getTailleMaximale(),
            'idSalle' => $coffre->getIdSalle(),
            'placesDisponibles' => $coffre->getPlacesDisponibles(),
            'tauxRemplissage' => $coffre->getTauxRemplissage(),
            'isPlein' => $coffre->isPlein(),
            'isActive' => $coffre->isActive(),
            'isDelete' => $coffre->isDelete(),
            'createdAt' => $coffre->getCreatedAt()?->format('c'),
            'updatedAt' => $coffre->getUpdatedAt()?->format('c'),
        ];

        return $this->json($responseData, 200);
    }
}
