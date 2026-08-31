<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crée un utilisateur avec un mot de passe par défaut',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addArgument('firstName', InputArgument::REQUIRED, 'Prénom')
            ->addArgument('lastName', InputArgument::REQUIRED, 'Nom')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe', 'password123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $password = $input->getArgument('password');

        $user = new User();
        $user->setEmail($input->getArgument('email'))
            ->setFirstName($input->getArgument('firstName'))
            ->setLastName($input->getArgument('lastName'))
            ->setCreatedAt(new \DateTimeImmutable())
            ->setRoles(['ROLE_USER']);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Utilisateur "%s" créé (mot de passe par défaut : %s)', $user->getEmail(), $password));

        return Command::SUCCESS;
    }
}
