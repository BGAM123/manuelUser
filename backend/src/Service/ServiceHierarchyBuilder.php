<?php

namespace App\Service;

use App\Entity\Service;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * ServiceHierarchyBuilder - Construit une structure hiérarchisée des services.
 *
 * Prend une liste plate de services et les organise en arbre hiérarchique
 * avec la propriété 'children' contenant les services enfants.
 */
final class ServiceHierarchyBuilder
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }
    /**
     * Construit une hiérarchie de services à partir d'une liste plate.
     *
     * Après filtrage, les services dont le parent n'est pas présent dans la liste
     * sont considérés comme racines. Cela permet de reconstruire correctement
     * l'arbre même si le parent a été filtré.
     *
     * @param Service[] $services Liste plate des services
     * @param bool|null $isActive Filtre optionnel: si null, aucun filtre; si true/false, n'inclut que les services avec ce statut
     * @return array<int, array<string, mixed>> Structure hiérarchisée avec enfants imbriqués
     */
    public function buildHierarchy(array $services, ?bool $isActive = null): array
    {
        // Construire un index par ID pour accès rapide
        $servicesById = [];
        foreach ($services as $service) {
            // Filtrer par is_active si nécessaire
            if (null !== $isActive && $service->isActive() !== $isActive) {
                continue;
            }
            $servicesById[$service->getId()] = $service;
        }

        // Grouper par parent
        $servicesByParent = [];
        foreach ($servicesById as $service) {
            $parentId = $service->getParent()?->getId();
            if (!isset($servicesByParent[$parentId])) {
                $servicesByParent[$parentId] = [];
            }
            $servicesByParent[$parentId][] = $service;
        }

        // Construire la structure hiérarchisée récursivement
        $result = [];

        // Les services racines sont:
        // 1. Ceux avec parent=null ET présents dans servicesById
        // 2. Ceux dont le parent n'existe pas dans servicesById (cas du filtrage)
        $rootServices = [];
        foreach ($servicesById as $service) {
            $parentId = $service->getParent()?->getId();

            // Si pas de parent, ou si le parent n'est pas dans nos données filtrées
            if (null === $parentId || !isset($servicesById[$parentId])) {
                $rootServices[] = $service;
            }
        }

        // Trier les services racines par ordre
        usort($rootServices, function ($a, $b) {
            return ($a->getOrdre() ?? 0) <=> ($b->getOrdre() ?? 0);
        });

        foreach ($rootServices as $rootService) {
            $result[] = $this->buildServiceNode($rootService, $servicesByParent);
        }

        return $result;
    }

    /**
     * Construit un nœud de service avec ses enfants imbriqués.
     *
     * @param Service $service Service racine/courante
     * @param array<int|null, Service[]> $servicesByParent Services groupés par parent
     * @return array<string, mixed> Nœud service avec 'children'
     */
    private function buildServiceNode(Service $service, array $servicesByParent): array
    {
        $children = [];
        if (isset($servicesByParent[$service->getId()])) {
            // Construire les nœuds enfants
            foreach ($servicesByParent[$service->getId()] as $childService) {
                $children[] = $this->buildServiceNode($childService, $servicesByParent);
            }

            // Trier les enfants par ordre (numeroOrdre)
            usort($children, function ($a, $b) {
                return ($a['ordre'] ?? 0) <=> ($b['ordre'] ?? 0);
            });
        }

        $parent = $service->getParent();

        // Extraire les IDs des typeOrganigrammes
        $typeOrganigrammeIds = [];
        foreach ($service->getTypeOrganigrammes() as $type) {
            if (!$type->isIsDelete()) {
                $typeOrganigrammeIds[] = $type->getId();
            }
        }

        // Rechercher l'utilisateur rattaché à ce service
        $utilisateur = $this->findUserByService($service);

        return [
            'id' => $service->getId(),
            'nom' => $service->getNom(),
            'sigle' => $service->getSigle(),
            'code' => $service->getCode(),
            'type_service' => $service->getTypeService(),
            'ordre' => $service->getOrdre(),
            'is_active' => $service->isActive(),
            'parent_id' => $parent ? [
                'id' => $parent->getId(),
                'nom' => $parent->getNom(),
            ] : null,
            'typeOrganigrammes' => $typeOrganigrammeIds,
            'utilisateur' => $utilisateur ? [
                'id' => $utilisateur->getId(),
                'firstName' => $utilisateur->getFirstName(),
                'lastName' => $utilisateur->getLastName(),
                'matricule' => $utilisateur->getMatricule(),
            ] : null,
            'children' => $children,
        ];
    }

    /**
     * Trouve un utilisateur rattaché à un service
     */
    private function findUserByService(Service $service): ?User
    {
        $users = $this->userRepository->findBy(['service' => $service, 'isDelete' => false]);
        return $users[0] ?? null;
    }
}
