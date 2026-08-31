<?php

namespace App\Controller\Arrondissements;

use App\Repository\ArrondissementRepository;
use App\Repository\DepartementRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/arrondissements')]
#[OA\Tag(name: 'Arrondissements')]
final class CreateArrondissementController extends AbstractController
{
    #[Route('', name: 'app_arrondissement_create', methods: ['POST'])]
    #[OA\Post(
        path: '/arrondissements',
        summary: 'Créer un arrondissement (unitaire ou lot de 1 à 10)',
        description: 'Crée un ou plusieurs arrondissements (1 à 10) rattachés à un département actif. Corps : objet unique ou tableau d\'objets avec nom, departement_id et code optionnel.'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['nom', 'departement_id'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Arrondissement exemple'),
                new OA\Property(property: 'code', type: 'string', example: 'Code exemple'),
                new OA\Property(property: 'departement_id', type: 'integer', example: 1),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Arrondissement(s) créé(s) avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Arrondissement(s) créé(s) avec succès.',
                'data' => [
                    [
                        'id' => 1,
                        'nom' => 'Yaoundé 1er',
                        'code' => 'YDE1',
                        'is_delete' => false,
                        'departement_id' => 1,
                        'departement' => [
                            'id' => 1,
                            'nom' => 'Mfoundi',
                            'region' => ['id' => 1, 'nom' => 'Centre'],
                        ],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    #[OA\Response(
        response: 409,
        description: 'Conflict',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 409, 'message' => 'Resource already exists.', 'data' => null])
    )]
    public function __invoke(
        Request $request,
        ArrondissementRepository $arrondissementRepository,
        DepartementRepository $departementRepository,
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
            if (!is_array($item) || empty($item['departement_id'])) {
                return $apiResponse->error('Le champ departement_id est obligatoire pour chaque arrondissement.', Response::HTTP_BAD_REQUEST);
            }

            $departement = $departementRepository->getActiveDepartementById((int) $item['departement_id']);
            if (!$departement) {
                return $apiResponse->error('Le département demandé est introuvable.', Response::HTTP_NOT_FOUND);
            }

            if (!empty($item['nom']) && $arrondissementRepository->existsByNom((string) $item['nom'], $departement)) {
                return $apiResponse->error('Ce nom d\'arrondissement est déjà utilisé dans ce département.', Response::HTTP_CONFLICT);
            }

            $arrondissement = $arrondissementRepository->buildArrondissementFromPayload($item);
            $arrondissement->setDepartement($departement);

            $errors = $validator->validate($arrondissement);
            if (count($errors) > 0) {
                $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
                return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
            }

            $arrondissementRepository->save($arrondissement, false);
            $created[] = $arrondissement;
        }

        $arrondissementRepository->flush();

        $data = json_decode($serializer->serialize($created, 'json', ['groups' => ['arrondissement:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_CREATED, 'Arrondissement(s) créé(s) avec succès.');
    }
}
