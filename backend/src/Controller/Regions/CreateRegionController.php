<?php

namespace App\Controller\Regions;

use App\Service\ApiResponseFactory;
use App\Service\LocationRegistrationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/regions')]
#[OA\Tag(name: 'Regions')]
final class CreateRegionController extends AbstractController
{
    #[Route('', name: 'app_region_create', methods: ['POST'])]
    #[OA\Post(path: '/regions', summary: 'Créer une région (unitaire ou lot de 1 à 10)')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(
                    type: 'object',
                    required: ['nom'],
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'Centre'),
                        new OA\Property(property: 'code', type: 'string', nullable: true, example: 'CE'),
                    ]
                ),
                new OA\Schema(
                    type: 'array',
                    minItems: 1,
                    maxItems: 10,
                    items: new OA\Items(
                        type: 'object',
                        required: ['nom'],
                        properties: [
                            new OA\Property(property: 'nom', type: 'string', example: 'Adamaoua'),
                            new OA\Property(property: 'code', type: 'string', nullable: true, example: 'AD'),
                        ]
                    )
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Région(s) traitée(s) avec succès',
        content: new OA\JsonContent(example: [
            'success' => true,
            'status' => 201,
            'message' => 'Région(s) enregistrée(s) avec succès.',
            'data' => [
                'created' => 1,
                'restored' => 0,
                'regions' => [
                    ['id' => 1, 'nom' => 'Centre', 'code' => 'CE', 'is_delete' => false],
                ],
            ],
        ])
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide')]
    #[OA\Response(response: 409, description: 'Conflict - Limite des 10 régions actives dépassée')]
    public function __invoke(
        Request $request,
        LocationRegistrationService $locationRegistrationService,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $items = array_is_list($payload) ? $payload : [$payload];

        try {
            $result = $locationRegistrationService->registerRegions($items);
        } catch (\InvalidArgumentException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\DomainException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_CONFLICT);
        }

        $regions = json_decode($serializer->serialize($result['regions'], 'json', ['groups' => ['region:detail']]), true);

        return $apiResponse->success(
            [
                'created' => $result['created'],
                'restored' => $result['restored'],
                'regions' => $regions,
            ],
            Response::HTTP_CREATED,
            'Région(s) enregistrée(s) avec succès.'
        );
    }
}
