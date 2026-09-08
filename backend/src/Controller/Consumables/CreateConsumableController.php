<?php

namespace App\Controller\Consumables;

use App\Entity\Consumable;
use App\Entity\User;
use App\Exception\ValidationFailedException;
use App\Security\ConsumableAccessChecker;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/consumables')]
#[OA\Tag(name: 'Consomptibles')]
final class CreateConsumableController extends AbstractController
{
    #[Route('', name: 'app_consumable_create', methods: ['POST'])]
    #[OA\Post(
        path: '/consumables',
        summary: 'Créer un consomptible',
        description: "Création multipart/form-data. La quantité de départ est saisie uniquement à la création et ne peut plus être modifiée par la suite.\n\n"
            . "**Uploads :** `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index).\n\n"
            . "**Exemple noms :** piecesJointes[0]=fiche.pdf + piecesJointesNoms[0]=Fiche technique ; piecesJointes[1]=photo.jpg + piecesJointesNoms[1]=Photo produit.\n\n"
            . "**Sans piecesJointesNoms :** chaque document prend automatiquement UploadedFile::getClientOriginalName() (ex. fiche.pdf).\n\n"
            . "**Swagger CSV :** si une seule chaîne `Fiche technique,Photo produit` est envoyée, elle est découpée automatiquement en deux noms."
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Papier A4'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Papier format A4 80g/m²'),
                    new OA\Property(property: 'quantite', type: 'number', example: 5000, description: 'Stock de départ (saisi uniquement à la création)'),
                    new OA\Property(property: 'unite_mesure', type: 'string', nullable: true, example: 'Paquet', description: 'Unité de mesure du consommable (ex: Paquet, Kg, Litre, etc.)'),
                    new OA\Property(property: 'prixInitial', type: 'number', nullable: true, example: 500, description: 'Prix unitaire initial'),
                    new OA\Property(property: 'prixTotal', type: 'number', nullable: true, example: 2500000, description: 'Prix total (quantité × prix unitaire)'),
                    new OA\Property(property: 'category_id', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'asset_type_id', type: 'integer', nullable: true, example: 5),
                    new OA\Property(property: 'asset_sub_type_id', type: 'integer', nullable: true, example: 10),
                    new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 16, description: 'Si fourni, crée automatiquement une entrée de consomptible pour ce service avec la quantité spécifiée'),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Documents à ajouter : piecesJointes[0]=fiche.pdf, piecesJointes[1]=photo.jpg'
                    ),
                    new OA\Property(
                        property: 'piecesJointesNoms[]',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: "Noms alignés : piecesJointesNoms[0]=Fiche technique. Si omis → nom original.",
                        example: ["Fiche technique", 'Photo produit']
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Consomptible créé avec succès.', 'data' => ['id' => 1, 'unite_mesure' => 'Paquet']]))]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['nom' => 'Le nom est obligatoire.']]))]
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        ConsumableService $consumableService,
        ConsumableAccessChecker $accessChecker,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = $request->request->all();
        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        // Un utilisateur normal ne peut créer un consomptible que pour son propre service.
        if (!empty($payload['service_id']) && !$accessChecker->canAccessServiceId($user, (int) $payload['service_id'])) {
            return $apiResponse->error(
                "Vous ne pouvez créer un consomptible que pour votre propre service.",
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $consumable = $consumableService->create($payload, $documents, $documentLabels);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        }

        return $apiResponse->success(
            ['id' => $consumable->getId()],
            Response::HTTP_OK,
            'Consomptible créé avec succès.'
        );
    }
}
