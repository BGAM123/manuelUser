<?php

// src/DataFixtures/ProjectFixtures.php

namespace App\DataFixtures;

use App\Entity\Project;
use App\Entity\ProjectStatus;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de données de démonstration pour 5 projets patrimoniaux, inspirés des missions
 * décrites dans le cahier des charges MINEPIA (inventaire, valorisation, déploiement...).
 *
 * Dépend de AppFixtures : on réutilise les utilisateurs déjà créés (les premiers de la
 * liste) pour peupler la relation ManyToMany Project <-> User, plutôt que d'en créer
 * de nouveaux.
 */
class ProjectFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @var array<int, array{nom: string, description: string, responsable: string, debut: string, fin: ?string, statut: ProjectStatus}>
     */
    private const PROJECTS = [
        [
            'nom' => 'Recensement physique du patrimoine 2026',
            'description' => "Inventaire général de base des biens meubles et immeubles du MINEPIA dans les 10 régions",
            'responsable' => 'SDBMM',
            'debut' => '2026-01-15',
            'fin' => '2026-06-30',
            'statut' => ProjectStatus::TERMINE,
        ],
        [
            'nom' => 'Déploiement de la plateforme de gestion du patrimoine',
            'description' => "Mise en œuvre de l'application web de gestion patrimoniale et formation des utilisateurs",
            'responsable' => 'DAG',
            'debut' => '2026-06-01',
            'fin' => '2026-09-01',
            'statut' => ProjectStatus::EN_COURS,
        ],
        [
            'nom' => 'Valorisation comptable des biens immobiliers',
            'description' => "Valorisation des terrains et bâtiments conformément à l'arrêté du MINFI sur les amortissements",
            'responsable' => 'SDBMM',
            'debut' => '2026-03-01',
            'fin' => '2026-12-31',
            'statut' => ProjectStatus::EN_COURS,
        ],
        [
            'nom' => 'Sécurisation foncière des terrains du MINEPIA',
            'description' => "Régularisation des titres et sécurisation des terrains identifiés lors de l'inventaire",
            'responsable' => 'DAG',
            'debut' => '2026-09-01',
            'fin' => null,
            'statut' => ProjectStatus::PLANIFIE,
        ],
        [
            'nom' => 'Interfaçage avec GEPSOFT',
            'description' => "Mise en place du cadre d'échange des données patrimoniales avec l'application GEPSOFT du MINFI",
            'responsable' => 'DAG',
            'debut' => '2026-10-01',
            'fin' => null,
            'statut' => ProjectStatus::PLANIFIE,
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        /** @var array<int, User> $users */
        $users = $manager->getRepository(User::class)->findBy([], ['id' => 'ASC'], 5);

        foreach (self::PROJECTS as $index => $data) {
            $project = new Project();
            $project->setNom($data['nom']);
            $project->setDescription($data['description']);
            //$project->setResponsable($data['responsable']);
            $project->setDateDebut(new \DateTimeImmutable($data['debut']));
            $project->setDateFinPrevue(null !== $data['fin'] ? new \DateTimeImmutable($data['fin']) : null);
            // $project->setStatut($data['statut']);
            $project->setStatut($data['statut'] instanceof \BackedEnum ? $data['statut']->value : $data['statut']);
            $project->setCreatedAt(new \DateTimeImmutable());
            $project->setUpdatedAt(new \DateTimeImmutable());

            // Affecte 1 à 2 utilisateurs de démonstration par projet, en tournant sur
            // la liste des utilisateurs disponibles pour varier les affectations.
            if (!empty($users)) {
                $project->addUser($users[$index % count($users)]);
                if (count($users) > 1) {
                    $project->addUser($users[($index + 1) % count($users)]);
                }
            }

            $manager->persist($project);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}
