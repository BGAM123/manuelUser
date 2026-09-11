<?php

namespace App\Command;

use App\Repository\Core\NotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notification:cleanup',
    description: 'Supprime les anciennes notifications lues après un certain nombre de jours',
)]
class NotificationCleanupCommand extends Command
{
    public function __construct(
        private NotificationRepository $notificationRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('days', InputArgument::OPTIONAL, 'Nombre de jours après lesquels supprimer les notifications lues', 30)
            ->setHelp('Cette commande supprime les notifications lues qui ont plus de X jours (par défaut 30 jours)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getArgument('days');

        $io->title('Nettoyage des notifications');
        $io->info(sprintf('Suppression des notifications lues de plus de %d jours...', $days));

        try {
            $count = $this->notificationRepository->deleteOldReadNotifications($days);

            if ($count > 0) {
                $io->success(sprintf('%d notification(s) supprimée(s) avec succès.', $count));
            } else {
                $io->info('Aucune notification à supprimer.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors de la suppression des notifications : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
