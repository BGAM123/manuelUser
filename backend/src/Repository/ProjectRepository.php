<?php

// src/Repository/ProjectRepository.php

namespace App\Repository;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @return array<int, Project>
     */
    /**
     * @return array<int, Project>
     */
    public function findPaginatedProjects(int $page, int $limit, ?string $q = null, ?string $statut = null, ?string $exercice = null, ?string $isDelete = 'false'): array
    {
        $qb = $this->createQueryBuilder('p');
        SoftDeleteQueryFilter::apply($qb, 'p', $isDelete);

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('p.nom LIKE :q OR p.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if (null !== $statut) {
            $qb->andWhere('p.statut = :statut')
                ->setParameter('statut', $statut);
        }

        // ✅ Filtre par exercice
        if ($exercice !== null && $exercice !== '') {
            $qb->andWhere('p.exercice = :exercice')
                ->setParameter('exercice', $exercice);
        }

        /** @var array<int, Project> $result */
        $result = $qb
            ->orderBy('p.dateDebut', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }
    // ✅ Méthode restore correcte
    public function restore(Project $project): void
    {
        $project->setIsDelete(false);  // ✅ Utilise setIsDelete()
        $project->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function countProjects(?string $q = null, ?string $statut = null, ?string $exercice = null, ?string $isDelete = 'false'): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');
        SoftDeleteQueryFilter::apply($qb, 'p', $isDelete);

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('p.nom LIKE :q OR p.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if (null !== $statut) {
            $qb->andWhere('p.statut = :statut')
                ->setParameter('statut', $statut);
        }

        // ✅ Filtre par exercice
        if ($exercice !== null && $exercice !== '') {
            $qb->andWhere('p.exercice = :exercice')
                ->setParameter('exercice', $exercice);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getProjectById(int $id): ?Project
    {
        return $this->find($id);
    }

    /**
     * Construit un Projet à partir d'un payload brut. Les champs de type date sont
     * attendus au format ISO 8601 (ex. "2026-07-01"). Ne persiste pas.
     */
    public function buildProjectFromPayload(array $data): Project
    {
        $project = new Project();

        if (isset($data['nom'])) {
            $project->setNom((string) $data['nom']);
        }
        if (array_key_exists('description', $data)) {
            $project->setDescription($data['description'] === null ? null : (string) $data['description']);
        }
        if (isset($data['responsable'])) {
            $project->setResponsable((string) $data['responsable']);
        }
        if (!empty($data['date_debut'])) {
            $project->setDateDebut(new \DateTimeImmutable((string) $data['date_debut']));
        }
        if (array_key_exists('date_fin_prevue', $data)) {
            $project->setDateFinPrevue(
                $data['date_fin_prevue'] === null || $data['date_fin_prevue'] === ''
                    ? null
                    : new \DateTimeImmutable((string) $data['date_fin_prevue'])
            );
        }
        if (!empty($data['statut'])) {
            $project->setStatut((string) $data['statut']);
        }
        // Exercice fixé à la création (fourni par l'appelant ou année courante par défaut),
        // jamais modifié ensuite : voir applyPayloadToProject() qui ne le touche pas.
        $project->setExercice(
            array_key_exists('exercice', $data) && $data['exercice'] !== null && $data['exercice'] !== ''
                ? (int) $data['exercice']
                : (int) (new \DateTimeImmutable())->format('Y')
        );

        $now = new \DateTimeImmutable();
        $project->setCreatedAt($now);
        $project->setUpdatedAt($now);

        return $project;
    }

    /**
     * Applique les champs modifiables d'un projet.
     */
    public function applyPayloadToProject(Project $project, array $data): Project
    {
        if (isset($data['nom'])) {
            $project->setNom((string) $data['nom']);
        }
        if (array_key_exists('description', $data)) {
            $project->setDescription($data['description'] === null ? null : (string) $data['description']);
        }
        if (isset($data['responsable'])) {
            $project->setResponsable((string) $data['responsable']);
        }
        if (!empty($data['date_debut'])) {
            $project->setDateDebut(new \DateTimeImmutable((string) $data['date_debut']));
        }
        if (array_key_exists('date_fin_prevue', $data)) {
            $project->setDateFinPrevue(
                $data['date_fin_prevue'] === null || $data['date_fin_prevue'] === ''
                    ? null
                    : new \DateTimeImmutable((string) $data['date_fin_prevue'])
            );
        }

        if (!empty($data['statut'])) {
            $project->setStatut((string) $data['statut']);
        }

        if (array_key_exists('exercice', $data) && $data['exercice'] !== null && $data['exercice'] !== '') {
            $project->setExercice((int) $data['exercice']);
        }

        $project->setUpdatedAt(new \DateTimeImmutable());

        return $project;
    }

    public function save(Project $project): void
    {
        $em = $this->getEntityManager();
        $em->persist($project);
        $em->flush();
    }

    /**
     * Suppression logique : le projet reste consultable en historique mais n'apparaît
     * plus dans les listes actives.
     */
    public function softDelete(Project $project): void
    {
        $project->setIsDelete(true);
        $this->save($project);
    }

    /**
     * Suppression physique et irréversible.
     */
    public function remove(Project $project): void
    {
        $em = $this->getEntityManager();
        $em->remove($project);
        $em->flush();
    }

    /**
     * Récupère les années d'exercice disponibles pour les filtres
     */
    public function getAvailableExercices(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('DISTINCT p.exercice')
            ->where('p.isDelete = false')
            ->andWhere('p.exercice IS NOT NULL')
            ->orderBy('p.exercice', 'DESC');

        $result = $qb->getQuery()->getScalarResult();
        return array_column($result, 'exercice');
    }
}
