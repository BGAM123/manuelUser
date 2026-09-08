<?php

namespace App\Command;

use App\Entity\Asset;
use App\Entity\AssetAssignment;
use App\Repository\ProjectRepository;
use App\Repository\AssetRepository;
use App\Repository\AssetAssignmentRepository;
use App\Service\AssetAssignmentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande de restitution automatique des biens de projets expirés.
 * 
 * Cette commande est exécutée périodiquement (par défaut tous les jours à minuit)
 * via le scheduler Symfony pour restituer automatiquement les biens dont le projet
 * est expiré.
 * 
 * Fonctionnement :
 * 1. Récupère tous les projets non supprimés avec dateFinPrevue < date du jour
 * 2. Pour chaque projet, parcourt tous les biens associés
 * 3. Pour chaque bien, vérifie les conditions suivantes :
 *    - Le bien n'est pas supprimé (isDelete = false)
 *    - Le bien a le statut ACTIF
 *    - Le bien a un serviceRestitution renseigné
 *    - Aucune restitution n'est déjà en cours pour ce bien
 * 4. Si toutes les conditions sont remplies, crée une restitution automatique
 *    vers le serviceRestitution du bien
 * 
 * Utilisation manuelle :
 * php bin/console app:restituer-biens-projets-expires
 * 
 * Configuration du scheduler :
 * Voir config/scheduler.yaml
 */
#[AsCommand(name: 'app:restituer-biens-projets-expires')]
class RestituerBiensProjetsExpiresCommand extends Command
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private AssetRepository $assetRepository,
        private AssetAssignmentRepository $assignmentRepository,
        private AssetAssignmentService $assetAssignmentService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Restitution automatique des biens de projets expirés');

        // Étape 1 : Récupérer tous les projets expirés (dateFinPrevue < aujourd'hui)
        $expiredProjects = $this->projectRepository->findExpiredProjects();
        
        // Si aucun projet expiré, terminer avec succès
        if (empty($expiredProjects)) {
            $io->success('Aucun projet expiré trouvé.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('%d projet(s) expiré(s) trouvé(s).', count($expiredProjects)));

        // Compteurs pour le résumé global
        $totalAssetsProcessed = 0;
        $totalAssetsRestituted = 0;
        $totalAssetsSkipped = 0;

        // Étape 2 : Parcourir chaque projet expiré
        foreach ($expiredProjects as $project) {
            $io->section(sprintf('Projet : %s (ID: %d)', $project->getNom(), $project->getId()));

            // Récupérer tous les biens associés à ce projet via AssetRepository
            // La relation est ManyToMany définie du côté Asset (table asset_project)
            $assets = $this->assetRepository->createQueryBuilder('a')
                ->innerJoin('a.projects', 'p')
                ->where('p.id = :projectId')
                ->setParameter('projectId', $project->getId())
                ->getQuery()
                ->getResult();
            $assetsCount = 0;
            $restitutedCount = 0;
            $skippedCount = 0;

            // Étape 3 : Parcourir chaque bien du projet
            foreach ($assets as $asset) {
                $assetsCount++;

                // Vérification 1 : Le bien ne doit pas être supprimé
                if ($asset->isDelete()) {
                    $io->text(sprintf('  - Bien #%d : ignoré (supprimé)', $asset->getId()));
                    $skippedCount++;
                    continue;
                }

                // Vérification 2 : Le bien doit avoir le statut ACTIF
                if ($asset->getStatut() !== 'ACTIF') {
                    $io->text(sprintf('  - Bien #%d : ignoré (statut: %s)', $asset->getId(), $asset->getStatut()));
                    $skippedCount++;
                    continue;
                }

                // Vérification 3 : Le bien doit avoir un serviceRestitution renseigné
                $serviceRestitution = $asset->getServiceRestitution();
                if (!$serviceRestitution) {
                    $io->text(sprintf('  - Bien #%d : ignoré (pas de service de restitution)', $asset->getId()));
                    $skippedCount++;
                    continue;
                }

                // Vérification 4 : Aucune restitution ne doit déjà être en cours
                // Une restitution en cours est définie par :
                // - typeAffectation = 'RESTITUTION'
                // - detenteur = true (affectation active)
                // - dateFin IS NULL (pas terminée)
                // - isDelete = false (non supprimée)
                $existingRestitution = $this->assignmentRepository->findOneBy([
                    'asset' => $asset,
                    'typeAffectation' => 'RESTITUTION',
                    'detenteur' => true,
                    'dateFin' => null,
                    'isDelete' => false,
                ]);

                if ($existingRestitution) {
                    $io->text(sprintf('  - Bien #%d : ignoré (restitution déjà en cours)', $asset->getId()));
                    $skippedCount++;
                    continue;
                }

                // Étape 4 : Créer la restitution automatique
                try {
                    // Appeler le service de restitution avec l'ID du service de restitution
                    // currentUser = null car c'est une commande automatique
                    $this->assetAssignmentService->restituerAsset($asset, [
                        'service_id' => $serviceRestitution->getId(),
                    ], null);
                    $io->text(sprintf('  - Bien #%d : restitué au service %s', $asset->getId(), $serviceRestitution->getNom()));
                    $restitutedCount++;
                } catch (\Exception $e) {
                    // En cas d'erreur, logger et continuer avec le bien suivant
                    $io->error(sprintf('  - Bien #%d : erreur lors de la restitution - %s', $asset->getId(), $e->getMessage()));
                    $skippedCount++;
                }
            }

            // Afficher le résumé pour ce projet
            $io->text(sprintf('  Résumé projet : %d biens traités, %d restitués, %d ignorés', $assetsCount, $restitutedCount, $skippedCount));
            
            // Mettre à jour les compteurs globaux
            $totalAssetsProcessed += $assetsCount;
            $totalAssetsRestituted += $restitutedCount;
            $totalAssetsSkipped += $skippedCount;
        }

        // Afficher le résumé global
        $io->newLine();
        $io->success(sprintf('Terminé : %d biens traités, %d restitués, %d ignorés', $totalAssetsProcessed, $totalAssetsRestituted, $totalAssetsSkipped));

        return Command::SUCCESS;
    }
}
