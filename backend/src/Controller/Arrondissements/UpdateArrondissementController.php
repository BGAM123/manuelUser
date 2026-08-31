<?php

namespace App\Controller\Arrondissements;

use App\Entity\Arrondissement;
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
final class UpdateArrondissementController extends AbstractController
{
    #[Route('/{id}', name: 'app_arrondissement_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(path: '/arrondissements/{id}', summary: 'Mettre à jour un arrondissement')]
    #[OA\Patch(path: '/arrondissements/{id}', summary: 'Mettre à jour partiellement un arrondissement')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Arrondissement mis à jour'),
                new OA\Property(property: 'code', type: 'string', example: 'Code mis à jour'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Success - Arrondissement mis à jour avec succès', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Arrondissement mis à jour avec succès.', 'data' => ['id' => 1, 'nom' => 'Yaoundé I', 'code' => 'YDE1', 'departement_id' => 1, 'is_delete' => false]]))]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide')]
    #[OA\Response(response: 404, description: 'Not Found - Arrondissement non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - Arrondissement supprimé ou nom déjà utilisé dans le département')]
    public function __invoke(
        Arrondissement $arrondissement,
        Request $request,
        ArrondissementRepository $arrondissementRepository,
        DepartementRepository $departementRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($arrondissement->isDelete()) {
            return $apiResponse->error('Cet arrondissement est supprimé.', Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $targetDepartement = $arrondissement->getDepartement();
        if (array_key_exists('departement_id', $payload) && null !== $payload['departement_id']) {
            $departement = $departementRepository->getActiveDepartementById((int) $payload['departement_id']);
            if (!$departement) {
                return $apiResponse->error('Le département demandé est introuvable.', Response::HTTP_NOT_FOUND);
            }
            $arrondissement->setDepartement($departement);
            $targetDepartement = $departement;
        }

        if (!empty($payload['nom']) && $targetDepartement && $arrondissementRepository->existsByNom((string) $payload['nom'], $targetDepartement, $arrondissement->getId())) {
            return $apiResponse->error('Ce nom d\'arrondissement est déjà utilisé dans ce département.', Response::HTTP_CONFLICT);
        }

        $arrondissementRepository->applyPayloadToArrondissement($arrondissement, $payload);

        $errors = $validator->validate($arrondissement);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $arrondissementRepository->save($arrondissement);

        $data = json_decode($serializer->serialize($arrondissement, 'json', ['groups' => ['arrondissement:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Arrondissement mis à jour avec succès.');
    }
}
