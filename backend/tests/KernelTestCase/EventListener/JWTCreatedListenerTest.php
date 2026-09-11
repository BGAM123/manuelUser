<?php

namespace App\Tests\EventListener;

use App\Entity\Core\User;
use App\Exception\UserDeleteException;
use App\EventListener\JWTCreatedListener;
use App\Exception\UserNotVerifiedException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

/**
 * Classe de test pour le listener JWTCreatedListener.
 * Teste les cas où des exceptions sont levées et un cas réussi lors de la création d'un JWT.
 */
class JWTCreatedListenerTest extends KernelTestCase
{
    /**
     * @var JWTCreatedListener $listener Instance du listener testé.
     */
    private JWTCreatedListener $listener;

    /**
     * Configuration initiale des tests.
     * Initialise le noyau Symfony et récupère l'instance du listener depuis le conteneur.
     */
    protected function setUp(): void
    {
        // Démarre le noyau Symfony
        self::bootKernel();

        // Récupère le service JWTCreatedListener depuis le conteneur
        $this->listener = self::getContainer()->get(JWTCreatedListener::class);
    }

    /**
     * Teste que l'exception UserDeleteException est levée
     * lorsqu'un utilisateur marqué comme supprimé tente de créer un JWT.
     */
    public function testOnJWTCreatedThrowsUserDeleteException(): void
    {
        // Création d'un utilisateur fictif marqué comme supprimé
        $user = new User();
        $user->setDelete(true); // Simule un utilisateur supprimé

        // Création de l'événement JWT avec l'utilisateur
        $event = $this->createJWTCreatedEvent($user);

        // Vérifie que l'exception UserDeleteException est levée
        $this->expectException(UserDeleteException::class);

        // Appel du listener pour traiter l'événement
        $this->listener->onJWTCreated($event);
    }

    /**
     * Teste que l'exception UserNotVerifiedException est levée
     * lorsqu'un utilisateur non vérifié tente de créer un JWT.
     */
    public function testOnJWTCreatedThrowsUserNotVerifiedException(): void
    {
        // Création d'un utilisateur fictif non vérifié
        $user = new User();
        $user->setDelete(false); // L'utilisateur n'est pas supprimé
        $user->setVerified(false); // Simule un utilisateur non vérifié

        // Création de l'événement JWT avec l'utilisateur
        $event = $this->createJWTCreatedEvent($user);

        // Vérifie que l'exception UserNotVerifiedException est levée
        $this->expectException(UserNotVerifiedException::class);

        // Appel du listener pour traiter l'événement
        $this->listener->onJWTCreated($event);
    }

    /**
     * Teste que le JWT est créé avec succès
     * pour un utilisateur valide (non supprimé et vérifié).
     */
    public function testOnJWTCreatedSuccess(): void
    {
        // Création d'un utilisateur fictif valide
        $user = new User();
        $user->setDelete(false); // L'utilisateur n'est pas supprimé
        $user->setVerified(true); // L'utilisateur est vérifié

        // Création de l'événement JWT avec l'utilisateur
        $event = $this->createJWTCreatedEvent($user);

        // Appel du listener pour traiter l'événement
        // Aucun exception ne devrait être levée
        $this->listener->onJWTCreated($event);

        // Vérifie qu'aucune exception n'est levée
        $this->addToAssertionCount(1); // Incrémente le compteur d'assertions
    }

    /**
     * Méthode utilitaire pour créer un événement JWTCreatedEvent.
     * 
     * @param User $user L'utilisateur associé à l'événement.
     * @return JWTCreatedEvent L'événement JWT simulé.
     */
    private function createJWTCreatedEvent(User $user): JWTCreatedEvent
    {
        // Retourne un événement JWTCreatedEvent avec les données nécessaires
        return new JWTCreatedEvent([], $user);
    }
}
