<?php

namespace App\Tests\Entity;

use App\Entity\Core\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

class UserTest extends TestCase
{
    public function testUserInitialization()
    {
        // Crée un utilisateur avec des données fictives
        $user = new User();
        $user->setUsername('john_doe')
            ->setEmail('john.doe@example.com')
            ->setPassword('password123');

        // Vérifie que les propriétés sont correctement définies
        $this->assertEquals('john_doe', $user->getUsername());
        $this->assertEquals('john.doe@example.com', $user->getEmail());
        $this->assertEquals('password123', $user->getPassword());
    }

    public function testEmailValidation()
    {
        // Crée un validateur
        $validator = Validation::createValidator();

        // Crée un utilisateur avec un email invalide
        $user = new User();
        $user->setEmail('invalid-email');
        
        // Valide l'email
        $violations = $validator->validate($user);

        // Vérifie qu'il y a une violation (email invalide)
        $this->assertGreaterThan(0, count($violations));
    }

    public function testPasswordLength()
    {
        // Crée un utilisateur avec un mot de passe trop court
        $user = new User();
        $user->setPassword('short');
        
        // Valide le mot de passe
        $violations = Validation::createValidator()->validate($user);

        // Vérifie qu'il y a une violation sur le mot de passe
        $this->assertGreaterThan(0, count($violations));
    }

    public function testUserToString()
    {
        // Crée un utilisateur
        $user = new User();
        $user->setUsername('john_doe');
        
        // Vérifie que la méthode __toString renvoie le bon nom d'utilisateur
        $this->assertEquals('john_doe', (string) $user);
    }
}
