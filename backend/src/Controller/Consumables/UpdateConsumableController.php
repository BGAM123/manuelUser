<?php

namespace App\Controller\Consumables;

use App\Entity\Consumable;
use App\Exception\ValidationFailedException;
use App\Repository\ConsumableRepository;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumables')]
#[OA\Tag(name: 'Consomptibles')]
final class UpdateConsumableController extends AbstractController
{
    #[Route('/{id}', name: 'app_consumable_update', methods: ['POST'])]
    #[OA\Post(
        path: '/consumables/{id}',
        summary: 'Mettre à jour un consomptible',
        description: "Modification partielle (multipart/form-data). La quantité de départ ne peut pas être modifiée.\n\n"
            . "**Uploads :** `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index).\n\n"
            . "**Exemple noms :** piecesJointes[0]=fiche.pdf + piecesJointesNoms[0]=Fiche technique ; piecesJointes[1]=photo.jpg + piecesJointesNoms[1]=Photo produit.\n\n"
            . "**Sans piecesJointesNoms :** chaque document prend automatiquement UploadedFile::getClientOriginalName() (ex. fiche.pdf).\n\n"
            . "**Swagger CSV :** si une seule chaîne `Fiche technique,Photo produit` est envoyée, elle est découpée automatiquement en deux noms."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', nullable: true),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'quantite', type: 'number', nullable: true, description: 'Quantité modifiable'),
                        new OA\Property(property: 'prixInitial', type: 'number', nullable: true, description: 'Prix unitaire initial'),
                        new OA\Property(property: 'prixTotal', type: 'number', nullable: true, description: 'Prix total'),
                        new OA\Property(property: 'category_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'asset_type_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'asset_sub_type_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'service_id', type: 'integer', nullable: true, description: 'Si fourni, crée automatiquement une entrée de consomptible pour ce service'),
                        new OA\Property(property: 'piecesJointes[]', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), description: 'Documents à ajouter'),
                        new OA\Property(property: 'piecesJointesNoms[]', type: 'array', items: new OA\Items(type: 'string'), description: 'Noms associés'),
                    ]
                )
            ),
            new OA\JsonContent(example: ['nom' => 'Papier A4 Premium', 'description' => 'Papier format A4 90g/m²']),
        ]
    )]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Consomptible mis à jour avec succès.', 'data' => ['id' => 1]]))]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le consomptible demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        int $id,
        Request $request,
        ConsumableRepository $consumableRepository,
        ConsumableService $consumableService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $consumable = $consumableRepository->find($id);
        if (!$consumable) {
            return $apiResponse->error('Le consomptible demandé est introuvable.', Response::HTTP_NOT_FOUND);
        }

        if ($consumable->isDelete()) {
            return $apiResponse->error('Ce consomptible est supprimé.', Response::HTTP_CONFLICT);
        }

        $contentType = (string) $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $payload = json_decode($request->getContent(), true);
            if (!is_array($payload)) {
                return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
            }
            $documents = [];
            $documentLabels = [];
        } else {
            $payload = $request->request->all();
            $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
            $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');
        }

        try {
            $consumable = $consumableService->update($consumable, $payload, $documents, $documentLabels);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        }

        return $apiResponse->success(
            ['id' => $consumable->getId()],
            Response::HTTP_OK,
            'Consomptible mis à jour avec succès.'
        );
    }
}
