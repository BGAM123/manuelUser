<?php

// src/DataFixtures/AppFixtures.php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i = 1; $i <= 30; $i++) {
            $user = new User();
            $user->setFirstName($faker->firstName)
                ->setLastName($faker->lastName)
                ->setEmail($faker->unique()->safeEmail)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setRoles(['ROLE_USER']);

            $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));

            $manager->persist($user);
        }

        $manager->flush();
    }
}

