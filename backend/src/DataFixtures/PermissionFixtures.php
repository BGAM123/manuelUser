<?php

// src/DataFixtures/PermissionFixtures.php

namespace App\DataFixtures;

use App\Entity\Permission;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de données de démonstration pour les permissions techniques du module Administration.
 * Les noms utilisés reprennent les exemples cités dans le cahier des charges
 * (archived, assign_permission_to_role, gerer_biens, voir_rapports...).
 *
 * Chaque permission est enregistrée sous une référence ("permission_<nom>") afin que
 * RoleFixtures puisse les récupérer pour construire les rôles sans requête supplémentaire.
 */
class PermissionFixtures extends Fixture
{
    /**
     * @var array<int, array{nom: string, description: string}>
     */
    public const PERMISSIONS = [
        ['nom' => 'gerer_utilisateurs', 'description' => "Créer, modifier et désactiver les comptes utilisateurs"],
        ['nom' => 'gerer_roles', 'description' => "Créer, modifier et désactiver les rôles"],
        ['nom' => 'assign_permission_to_role', 'description' => "Affecter ou retirer une permission à un rôle"],
        ['nom' => 'gerer_biens', 'description' => "Gérer les biens du patrimoine (création, mise à jour, sortie)"],
        ['nom' => 'gerer_projets', 'description' => "Créer et suivre les projets patrimoniaux"],
        ['nom' => 'gerer_maintenance', 'description' => "Planifier et suivre la maintenance des biens"],
        ['nom' => 'gerer_inventaire', 'description' => "Réaliser et clôturer les opérations d'inventaire"],
        ['nom' => 'voir_rapports', 'description' => "Consulter les rapports et statistiques du patrimoine"],
        ['nom' => 'valoriser_biens', 'description' => "Valoriser et amortir les biens du patrimoine"],
        ['nom' => 'archived', 'description' => "Consulter les éléments archivés (supprimés logiquement)"],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::PERMISSIONS as $data) {
            $permission = new Permission();
            $permission->setNom($data['nom']);
            $permission->setDescription($data['description']);
            $permission->setIsActive(true);
            $permission->setCreatedAt(new \DateTimeImmutable());
            $permission->setUpdatedAt(new \DateTimeImmutable());

            $manager->persist($permission);

            // Référence réutilisée par RoleFixtures pour composer les rôles.
            $this->addReference('permission_' . $data['nom'], $permission);
        }

        $manager->flush();
    }
}
