<?php

namespace App\Controller\Services;

use App\Repository\ServiceRepository;
use App\Repository\TypeOrganigrammeRepository;
use App\Repository\RegionRepository;
use App\Repository\DepartementRepository;
use App\Repository\ArrondissementRepository;
use App\Entity\Service;
use App\Service\ApiResponseFactory;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/services')]
#[OA\Tag(name: 'Services')]
final class UpdateServiceController extends AbstractController
{
    #[Route('/{id}', name: 'app_service_update', methods: ['PUT','PATCH'])]
    #[OA\Put(path: '/services/{id}', summary: 'Remplacer un service')]
    #[OA\Patch(path: '/services/{id}', summary: 'Mettre à jour partiellement un service')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        description: 'Payload pour mettre à jour un service',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Comptabilité'),
                new OA\Property(property: 'sigle', type: 'string', example: 'COMP'),
                new OA\Property(property: 'code', type: 'string', example: 'COMP'),
                new OA\Property(property: 'type_service', type: 'string', example: 'Poste'),
                new OA\Property(property: 'ordre', type: 'integer', example: 2),
                new OA\Property(property: 'is_active', type: 'boolean', example: true),
                new OA\Property(property: 'parent_id', type: 'integer', example: 3),
                new OA\Property(property: 'typeOrganigrammes', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                new OA\Property(property: 'region_id', type: 'integer', example: 1, nullable: true),
                new OA\Property(property: 'departement_id', type: 'integer', example: 3, nullable: true),
                new OA\Property(property: 'arrondissement_id', type: 'integer', example: 5, nullable: true),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Service mis à jour avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Service updated successfully.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Service updated successfully.',
                'data' => [
                    'id' => 2,
                    'nom' => 'Informatique',
                    'sigle' => 'IT',
                    'code' => 'IT',
                    'type_service' => 'Poste',
                    'ordre' => 1,
                    'is_active' => true,
                    'parent_id' => null,
                    'typeOrganigrammes' => [
                        ['id' => 1, 'nom' => 'Organigramme Administratif'],
                        ['id' => 2, 'nom' => 'Organigramme Fonctionnel']
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - payload invalide')]
    #[OA\Response(response: 404, description: 'Not Found - Service non trouvé')]
    public function __invoke(
        Service $service,
        Request $request,
        ServiceRepository $serviceRepository,
        TypeOrganigrammeRepository $typeOrganigrammeRepository,
        RegionRepository $regionRepository,
        DepartementRepository $departementRepository,
        ArrondissementRepository $arrondissementRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        // Valider le numéro d'ordre unique (si modifié)
        if (isset($payload['ordre']) && $payload['ordre'] !== $service->getOrdre()) {
            $parentId = $service->getParent() ? $service->getParent()->getId() : null;
            if ($serviceRepository->isOrdreAlreadyUsed($payload['ordre'], $parentId, $service->getId())) {
                return $apiResponse->error('Ce numéro d\'ordre est déjà utilisé par un autre service.', Response::HTTP_BAD_REQUEST);
            }
        }

        $serviceRepository->applyPayloadToService($service, $payload);

        if (array_key_exists('parent_id', $payload)) {
            if (null === $payload['parent_id']) {
                $service->setParent(null);
            } else {
                $parent = $serviceRepository->getServiceById((int) $payload['parent_id']);
                if (!$parent) {
                    return $apiResponse->error('Le service parent demandé est introuvable.', Response::HTTP_NOT_FOUND);
                }
                $service->setParent($parent);
            }
        }

        // Gérer les typeOrganigrammes (synchroniser)
        if (array_key_exists('typeOrganigrammes', $payload) && is_array($payload['typeOrganigrammes'])) {
            // Supprimer les anciens
            foreach ($service->getTypeOrganigrammes() as $type) {
                $service->removeTypeOrganigramme($type);
            }
            // Ajouter les nouveaux
            foreach ($payload['typeOrganigrammes'] as $typeId) {
                $type = $typeOrganigrammeRepository->find((int) $typeId);
                if (!$type || $type->isIsDelete()) {
                    return $apiResponse->error('Le type d\'organigramme demandé est introuvable.', Response::HTTP_NOT_FOUND);
                }
                $service->addTypeOrganigramme($type);
            }
        }

        if (array_key_exists('region_id', $payload)) {
            if (null === $payload['region_id'] || '' === $payload['region_id']) {
                $service->setRegion(null);
            } else {
                $region = $regionRepository->getActiveRegionById((int) $payload['region_id']);
                if (!$region) {
                    return $apiResponse->error('La région demandée est introuvable.', Response::HTTP_NOT_FOUND);
                }
                $service->setRegion($region);
            }
        }

        if (array_key_exists('departement_id', $payload)) {
            if (null === $payload['departement_id'] || '' === $payload['departement_id']) {
                $service->setDepartement(null);
            } else {
                $departement = $departementRepository->getActiveDepartementById((int) $payload['departement_id']);
                if (!$departement) {
                    return $apiResponse->error('Le département demandé est introuvable.', Response::HTTP_NOT_FOUND);
                }
                $service->setDepartement($departement);
            }
        }

        if (array_key_exists('arrondissement_id', $payload)) {
            if (null === $payload['arrondissement_id'] || '' === $payload['arrondissement_id']) {
                $service->setArrondissement(null);
            } else {
                $arrondissement = $arrondissementRepository->getActiveArrondissementById((int) $payload['arrondissement_id']);
                if (!$arrondissement) {
                    return $apiResponse->error('L\'arrondissement demandé est introuvable.', Response::HTTP_NOT_FOUND);
                }
                $service->setArrondissement($arrondissement);
            }
        }

        $errors = $validator->validate($service);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $serviceRepository->save($service);

        $data = json_decode($serializer->serialize($service, 'json', ['groups' => ['service:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Service updated successfully.');
    }
}

