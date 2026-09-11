<?php

namespace App\Tests\EventSubscriber;

use App\Exception\UserDeleteException;
use App\Exception\EntityFoundException;
use App\Exception\AccessDeniedException;
use App\Exception\InvalidFieldException;
use App\Exception\ChangePasswordException;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidArgumentException;
use App\Exception\UserNotVerifiedException;
use Symfony\Component\HttpFoundation\Request;
use App\EventSubscriber\CustomExceptionSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Classe de test pour CustomExceptionSubscriber.
 */
class CustomExceptionSubscriberTest extends KernelTestCase
{
    private CustomExceptionSubscriber $subscriber;

    /**
     * Configuration initiale pour les tests.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriber = new CustomExceptionSubscriber();
    }

    /**
     * Teste que l'exception UserNotVerifiedException retourne la réponse JSON attendue.
     */
    public function testUserNotVerifiedException(): void
    {
        $exception = new UserNotVerifiedException();
        $event = $this->createExceptionEvent($exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();

        // Vérifie qu'une réponse a été définie
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            ['code' => 403, 'message' => 'Account not verified.'],
            json_decode($response->getContent(), true)
        );
    }

    /**
     * Teste que l'exception UserDeleteException retourne la réponse JSON attendue.
     */
    public function testUserDeleteException(): void
    {
        $exception = new UserDeleteException();
        $event = $this->createExceptionEvent($exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();

        // Vérifie qu'une réponse a été définie
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            ['code' => 401, 'message' => 'Invalid credentials.'],
            json_decode($response->getContent(), true)
        );
    }

    /**
     * Teste que l'exception AccessDeniedException retourne la réponse JSON attendue.
     */
    public function testAccessDeniedException(): void
    {
        $exception = new AccessDeniedException();
        $event = $this->createExceptionEvent($exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();

        // Vérifie qu'une réponse a été définie
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            ['code' => 403, 'message' => 'Access denied.'],
            json_decode($response->getContent(), true)
        );
    }

    /**
     * Teste que l'exception EntityNotFoundException retourne la réponse JSON attendue.
     */
    public function testEntityNotFoundException(): void
    {
        $exception = new EntityNotFoundException();
        $event = $this->createExceptionEvent($exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();

        // Vérifie qu'une réponse a été définie
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['code' => 500, 'message' => 'Entity not found.'],
            json_decode($response->getContent(), true)
        );
    }

    /**
     * Teste que l'exception InvalidArgumentException retourne la réponse JSON attendue.
     */
    public function testInvalidArgumentException(): void
    {
        $exception = new InvalidArgumentException();
        $event = $this->createExceptionEvent($exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();

        // Vérifie qu'une réponse a été définie
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['code' => 500, 'message' => 'Invalid argument.'],
            json_decode($response->getContent(), true)
        );
    }

    /**
     * Teste que l'exception EntityFoundException retourne la réponse JSON attendue.
     */
    public function testEntityFoundException(): void
    {
        $exception = new EntityFoundException();
        $event = $this->createExceptionEvent($exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();

        // Vérifie qu'une réponse a été définie
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['code' => 500, 'message' => 'Entity already exists.'],
            json_decode($response->getContent(), true)
        );
    }

    /**
     * Teste que l'exception InvalidFieldException retourne la réponse JSON attendue.
     */
    public function testInvalidFieldException(): void
    {
        $exception = new InvalidFieldException();
        $event = $this->createExceptionEvent($exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();

        // Vérifie qu'une réponse a été définie
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['code' => 500, 'message' => 'The field or its setter was not found in the entity.'],
            json_decode($response->getContent(), true)
        );
    }

    /**
     * Teste que l'exception ChangePasswordException retourne la réponse JSON attendue.
     */
    public function testChangePasswordException(): void
    {
        $exception = new ChangePasswordException();
        $event = $this->createExceptionEvent($exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();

        // Vérifie qu'une réponse a été définie
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            ['code' => 401, 'message' => 'Change password.'],
            json_decode($response->getContent(), true)
        );
    }

    /**
     * Méthode utilitaire pour créer un ExceptionEvent.
     *
     * @param \Throwable $exception L'exception à tester.
     * @return ExceptionEvent L'événement d'exception simulé.
     */
    private function createExceptionEvent(\Throwable $exception): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $requestType = HttpKernelInterface::MAIN_REQUEST;

        return new ExceptionEvent($kernel, $request, $requestType, $exception);
    }
}
