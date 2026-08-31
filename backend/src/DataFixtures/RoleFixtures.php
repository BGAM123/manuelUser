<?php

// src/DataFixtures/RoleFixtures.php

namespace App\DataFixtures;

use App\Entity\Permission;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de données de démonstration pour les 6 rôles métier cités dans le cahier des
 * charges (Administrateur, Gestionnaire de Biens, Responsable Structure, Agent
 * Logistique, Consultation, Auditeur), chacun avec un sous-ensemble de permissions
 * cohérent avec son intitulé.
 *
 * Dépend de PermissionFixtures : les permissions doivent exister avant de pouvoir
 * être affectées aux rôles.
 */
class RoleFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @var array<int, array{nom: string, description: string, permissions: array<int, string>}>
     */
    private const ROLES = [
        [
            'nom' => 'Administrateur',
            'description' => "Accès complet à la plateforme : administration technique et fonctionnelle",
            'permissions' => [
                'gerer_utilisateurs', 'gerer_roles', 'assign_permission_to_role', 'gerer_biens',
                'gerer_projets', 'gerer_maintenance', 'gerer_inventaire', 'voir_rapports',
                'valoriser_biens', 'archived',
            ],
        ],
        [
            'nom' => 'Gestionnaire de Biens',
            'description' => "Gère le cycle de vie des biens du patrimoine au quotidien",
            'permissions' => ['gerer_biens', 'gerer_inventaire', 'valoriser_biens', 'voir_rapports'],
        ],
        [
            'nom' => 'Responsable Structure',
            'description' => "Supervise le patrimoine affecté à sa structure",
            'permissions' => ['gerer_biens', 'gerer_projets', 'voir_rapports'],
        ],
        [
            'nom' => 'Agent Logistique',
            'description' => "Assure le suivi de la maintenance et des mouvements de biens",
            'permissions' => ['gerer_biens', 'gerer_maintenance'],
        ],
        [
            'nom' => 'Consultation',
            'description' => "Accès en lecture seule aux données du patrimoine",
            'permissions' => ['voir_rapports'],
        ],
        [
            'nom' => 'Auditeur',
            'description' => "Contrôle et vérifie la conformité de la gestion patrimoniale",
            'permissions' => ['voir_rapports', 'archived'],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::ROLES as $data) {
            $role = new Role();
            $role->setNom($data['nom']);
            $role->setDescription($data['description']);
            $role->setIsActive(true);
            $role->setCreatedAt(new \DateTimeImmutable());
            $role->setUpdatedAt(new \DateTimeImmutable());

            foreach ($data['permissions'] as $permissionNom) {
                /** @var Permission $permission */
                $permission = $this->getReference('permission_' . $permissionNom, Permission::class);
                $role->addPermission($permission);
            }

            $manager->persist($role);
            $this->addReference('role_' . $data['nom'], $role);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [PermissionFixtures::class];
    }
}
