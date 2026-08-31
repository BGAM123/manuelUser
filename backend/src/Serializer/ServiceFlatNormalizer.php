<?php

namespace App\Serializer;

use App\Entity\Service;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer spécialisé pour la liste des services.
 * Retourne une liste plate sans hiérarchie, avec parent simplifié.
 */
class ServiceFlatNormalizer implements NormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if (!$object instanceof Service) {
            throw new \InvalidArgumentException('Expected Service instance');
        }

        $parent = $object->getParent();

        // Normaliser les types d'organigrammes
        $typeOrganigrammes = [];
        foreach ($object->getTypeOrganigrammes() as $type) {
            if (!$type->isIsDelete()) {
                $typeOrganigrammes[] = [
                    'id' => $type->getId(),
                    'nom' => $type->getNom(),
                ];
            }
        }

        return [
            'id' => $object->getId(),
            'nom' => $object->getNom(),
            'sigle' => $object->getSigle(),
            'code' => $object->getCode(),
            'type_service' => $object->getTypeService(),
            'ordre' => $object->getOrdre(),
            'is_active' => $object->isActive(),
            'parent_id' => $parent ? [
                'id' => $parent->getId(),
                'nom' => $parent->getNom(),
            ] : null,
            'typeOrganigrammes' => $typeOrganigrammes,
            'region' => $object->getRegion() && !$object->getRegion()->isDelete() ? [
                'id' => $object->getRegion()->getId(),
                'nom' => $object->getRegion()->getNom(),
            ] : null,
            'departement' => $object->getDepartement() && !$object->getDepartement()->isDelete() ? [
                'id' => $object->getDepartement()->getId(),
                'nom' => $object->getDepartement()->getNom(),
            ] : null,
            'arrondissement' => $object->getArrondissement() && !$object->getArrondissement()->isDelete() ? [
                'id' => $object->getArrondissement()->getId(),
                'nom' => $object->getArrondissement()->getNom(),
            ] : null,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Service && ($context['_service_flat'] ?? false);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Service::class => false,
        ];
    }
}
