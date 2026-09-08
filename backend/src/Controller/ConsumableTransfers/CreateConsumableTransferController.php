<?php

namespace App\Controller\ConsumableTransfers;

use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableTransferService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;

#[Route('/consumable-transfers')]
#[OA\Tag(name: 'Consomptibles-Transferts')]
final class CreateConsumableTransferController extends AbstractController
{
    #[Route('', name: 'app_consumable_transfer_create', methods: ['POST'])]
    #[OA\Post(
        path: '/consumable-transfers',
        summary: 'Créer un transfert de consomptible',
        description: 'Création multipart/form-data. Deux types possibles : TRANSFERT_DIRECT ou BSP.'
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['TRANSFERT_DIRECT', 'BSP'], example: 'TRANSFERT_DIRECT', description: 'Obligatoire : TRANSFERT_DIRECT ou BSP'),
                    new OA\Property(property: 'consumable_id', type: 'integer', example: 1, description: 'Obligatoire'),
                    new OA\Property(property: 'service_source_id', type: 'integer', nullable: true, example: 12, description: 'Optionnel, réservé aux administrateurs : service qui possède le stock à transférer. Pour un utilisateur lambda, le service connecté est toujours utilisé.'),
                    new OA\Property(property: 'service_destination_id', type: 'integer', example: 16, description: 'Service destinataire (obligatoire)'),
                    new OA\Property(property: 'quantite', type: 'number', example: 500, description: 'Obligatoire'),
                    new OA\Property(property: 'dateTransfert', type: 'string', format: 'date', nullable: true, example: '2026-08-16'),
                    new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'Transfert pour usage interne'),
                    // Champs BSP uniquement (si type = BSP)
                    new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 16, description: 'Service BSP (si type = BSP)'),
                    new OA\Property(property: 'beneficiaire_id', type: 'integer', nullable: true, example: 8, description: 'Bénéficiaire BSP (si type = BSP)'),
                    new OA\Property(property: 'quantiteDemandee', type: 'integer', nullable: true, example: 500, description: 'Quantité demandée BSP (si type = BSP)'),
                    new OA\Property(property: 'quantiteAccordee', type: 'integer', nullable: true, example: 500, description: 'Quantité accordée BSP (si type = BSP)'),
                    new OA\Property(property: 'quantiteServie', type: 'integer', nullable: true, example: 500, description: 'Quantité servie BSP (si type = BSP)'),
                    new OA\Property(property: 'dateEtablissement', type: 'string', format: 'date', nullable: true, example: '2026-08-16', description: 'Date établissement BSP (si type = BSP)'),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Documents : pour TRANSFERT_DIRECT → consumable_transfer_piece_jointe, pour BSP → bsp_piece_jointe'
                    ),
                    new OA\Property(
                        property: 'piecesJointesNoms[]',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: 'Noms des documents',
                        example: ['Document 1', 'Document 2']
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Transfert créé avec succès.', 'data' => ['id' => 1]]))]
    #[OA\Response(response: 400, description: 'Validation, ou stock insuffisant/épuisé pour un transfert BSP', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => "Impossible d'effectuer le transfert : le stock est épuisé.", 'data' => ['quantite' => "Impossible d'effectuer le transfert : le stock est épuisé."]]))]
    public function __invoke(
        Request $request,
        ConsumableTransferService $consumableTransferService,
        ApiResponseFactory $apiResponse,
        #[CurrentUser] User $currentUser
    ): JsonResponse {
        $payload = $request->request->all();
        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        try {
            if (!array_key_exists('type', $payload) || $payload['type'] === null) {
                throw new ValidationFailedException(['type' => 'Le type est obligatoire.']);
            }

            $type = $payload['type'];
            unset($payload['type']);

            if ($type === 'BSP') {
                $transfer = $consumableTransferService->createBspTransfer($payload, $documents, $documentLabels, $currentUser);
            } elseif ($type === 'TRANSFERT_DIRECT') {
                $transfer = $consumableTransferService->createDirectTransfer($payload, $documents, $documentLabels, $currentUser);
            } else {
                throw new ValidationFailedException(['type' => 'Le type doit être TRANSFERT_DIRECT ou BSP.']);
            }
        } catch (ValidationFailedException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST, $e->getErrors());
        }

        return $apiResponse->success(
            ['id' => $transfer->getId()],
            Response::HTTP_OK,
            'Transfert créé avec succès.'
        );
    }
}
