<?php

namespace App\Controller\Departements;

use App\Entity\Departement;
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
final class UpdateDepartementController extends AbstractController
{
    #[Route('/{id}', name: 'app_departement_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(path: '/departements/{id}', summary: 'Mettre à jour un département')]
    #[OA\Patch(path: '/departements/{id}', summary: 'Mettre à jour partiellement un département')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Département mis à jour'),
                new OA\Property(property: 'code', type: 'string', example: 'Code mis à jour'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Success - Département mis à jour avec succès', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Département mis à jour avec succès.', 'data' => ['id' => 1, 'nom' => 'Mfoundi', 'code' => 'MF', 'region_id' => 1, 'is_delete' => false]]))]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide')]
    #[OA\Response(response: 404, description: 'Not Found - Département non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - Département supprimé ou nom déjà utilisé dans la région')]
    public function __invoke(
        Departement $departement,
        Request $request,
        DepartementRepository $departementRepository,
        RegionRepository $regionRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($departement->isDelete()) {
            return $apiResponse->error('Ce département est supprimé.', Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $targetRegion = $departement->getRegion();
        if (array_key_exists('region_id', $payload) && null !== $payload['region_id']) {
            $region = $regionRepository->getActiveRegionById((int) $payload['region_id']);
            if (!$region) {
                return $apiResponse->error('La région demandée est introuvable.', Response::HTTP_NOT_FOUND);
            }
            $departement->setRegion($region);
            $targetRegion = $region;
        }

        if (!empty($payload['nom']) && $targetRegion && $departementRepository->existsByNom((string) $payload['nom'], $targetRegion, $departement->getId())) {
            return $apiResponse->error('Ce nom de département est déjà utilisé dans cette région.', Response::HTTP_CONFLICT);
        }

        $departementRepository->applyPayloadToDepartement($departement, $payload);

        $errors = $validator->validate($departement);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $departementRepository->save($departement);

        $data = json_decode($serializer->serialize($departement, 'json', ['groups' => ['departement:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Département mis à jour avec succès.');
    }
}
