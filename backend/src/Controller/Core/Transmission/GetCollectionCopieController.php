<?php

namespace App\Controller\Core\Transmission;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: "Transmission")]
class GetCollectionCopieController extends AbstractController
{
    public function __construct(
        private GetCollectionController $getCollectionController,
    ) {}

    #[Route('/core/transmission/copie', name: 'app_core_transmission_get_collection_copie', methods: ['GET'])]
    #[OA\Get(
        path: '/core/transmission/copie',
        summary: 'Lister les transmissions (en copie) avec filtres et pagination',
        description: 'Retourne uniquement le bloc `data_transmissions_copie` de l’API GET /core/transmission, avec les mêmes paramètres de filtres et de pagination.',
        tags: ['Transmission'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'order_by', in: 'query', required: false, description: 'Sort direction (ASC or DESC).', schema: new OA\Schema(type: 'string', enum: ['DESC', 'ASC'], default: 'DESC')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Filter from this date (YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Filter to this date (YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date', in: 'query', required: false, description: 'Filter on a specific date (YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'priorite', in: 'query', required: false, description: 'Filter by priority.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'categorie', in: 'query', required: false, description: 'Filter by category ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type_courrier', in: 'query', required: false, description: 'Filter by courrier type ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'courrier', in: 'query', required: false, description: 'Filter by courrier ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'provenance', in: 'query', required: false, description: 'Filter by provenance ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'service_destinataire', in: 'query', required: false, description: 'Filter by destination service ID (same behavior as /core/transmission).', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'emetteur', in: 'query', required: false, description: 'Filter by emitter (user) ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'statut', in: 'query', required: false, description: 'Filter by status.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type_transfert', in: 'query', required: false, description: 'Filter by transfer type.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'accuse_reception', in: 'query', required: false, description: 'Filter by accuse reception (boolean).', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'isinstance', in: 'query', required: false, description: 'Filter by instance flag (boolean).', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'isgeled', in: 'query', required: false, description: 'Filter by courrier gelé (boolean).', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'dernier_poste', in: 'query', required: false, description: 'Filter by last destination service ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, description: 'Include logically deleted transmissions (boolean).', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'isarchive', in: 'query', required: false, description: 'Include archived transmissions (boolean).', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Global search.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Filter by year (YYYY).', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number (>= 1).', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Items per page.', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bloc data_transmissions_copie récupéré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'data_transmissions_copie',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'service_id', type: 'integer', example: 179),
                                    new OA\Property(property: 'page', type: 'integer', example: 1),
                                    new OA\Property(property: 'limit', type: 'integer', example: 10),
                                    new OA\Property(property: 'total', type: 'integer', example: 85),
                                    new OA\Property(property: 'total_transmis_cp', type: 'integer', example: 3),
                                    new OA\Property(property: 'transmissions', type: 'array', items: new OA\Items(type: 'object')),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.'),
        ]
    )]
    public function listCopie(Request $request): Response
    {
        // Déléguer à l'implémentation existante afin de garantir un contenu strictement identique
        // au bloc `data_transmissions_copie` de GET /core/transmission.
        $response = $this->getCollectionController->list($request);

        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $payload = json_decode((string) $response->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['data_transmissions_copie' => []], 200);
        }

        return $this->json([
            'data_transmissions_copie' => $payload['data_transmissions_copie'] ?? [],
        ], 200);
    }
}

