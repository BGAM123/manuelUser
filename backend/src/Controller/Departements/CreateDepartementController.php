<?php

namespace App\Controller\Departements;

use App\Repository\DepartementRepository;
use App\Repository\RegionRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/departements')]
#[OA\Tag(name: 'Departements')]
final class CreateDepartementController extends AbstractController
{
    #[Route('', name: 'app_departement_create', methods: ['POST'])]
    #[OA\Post(path: '/departements', summary: 'Créer un département (unitaire ou lot de 1 à 10)')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['nom', 'region_id'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Département exemple'),
                new OA\Property(property: 'code', type: 'string', example: 'Code exemple'),
                new OA\Property(property: 'region_id', type: 'integer', example: 1),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Département(s) créé(s) avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Département(s) créé(s) avec succès.',
                'data' => [
                    [
                        'id' => 1,
                        'nom' => 'Mfoundi',
                        'region_id' => 1,
                        'region' => ['id' => 1, 'nom' => 'Centre'],
                        'arrondissements' => [],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide ou parent invalide')]
    #[OA\Response(response: 409, description: 'Conflict - Nom déjà utilisé dans la région')]
    public function __invoke(
        Request $request,
        DepartementRepository $departementRepository,
        RegionRepository $regionRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $items = array_is_list($payload) ? $payload : [$payload];
        if (count($items) < 1 || count($items) > 10) {
            return $apiResponse->error('Le lot doit contenir entre 1 et 10 éléments.', Response::HTTP_BAD_REQUEST);
        }

        $created = [];

        foreach ($items as $item) {
            if (!is_array($item) || empty($item['region_id'])) {
                return $apiResponse->error('Le champ region_id est obligatoire pour chaque département.', Response::HTTP_BAD_REQUEST);
            }

            $region = $regionRepository->getActiveRegionById((int) $item['region_id']);
            if (!$region) {
                return $apiResponse->error('La région demandée est introuvable.', Response::HTTP_NOT_FOUND);
            }

            if (!empty($item['nom']) && $departementRepository->existsByNom((string) $item['nom'], $region)) {
                return $apiResponse->error('Ce nom de département est déjà utilisé dans cette région.', Response::HTTP_CONFLICT);
            }

            $departement = $departementRepository->buildDepartementFromPayload($item);
            $departement->setRegion($region);

            $errors = $validator->validate($departement);
            if (count($errors) > 0) {
                $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
                return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
            }

            $departementRepository->save($departement, false);
            $created[] = $departement;
        }

        $departementRepository->flush();

        $data = json_decode($serializer->serialize($created, 'json', ['groups' => ['departement:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_CREATED, 'Département(s) créé(s) avec succès.');
    }
}
