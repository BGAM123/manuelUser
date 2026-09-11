<?php

namespace App\EventSubscriber;

use App\Exception\UserDeleteException;
use App\Exception\EntityFoundException;
use App\Exception\AccessDeniedException;
use App\Exception\InvalidFieldException;
use App\Exception\ChangePasswordException;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidArgumentException;
use App\Exception\UserNotVerifiedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EventSubscriber CustomExceptionSubscriber
 * 
 * Gère les exceptions personnalisées pour fournir des réponses adaptées.
 */
class CustomExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * Méthode appelée lorsqu'une exception est levée dans l'application.
     *
     * Cette méthode gère les exceptions spécifiques et fournit des réponses personnalisées.
     * 
     * @param ExceptionEvent $event L'événement de l'exception.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        // Récupère l'exception qui a été levée.
        $exception = $event->getThrowable();

        // Vérifie si l'exception est personnalisée
        $response = $this->handleCustomExceptions($exception);
        if ($response !== null) {
            // Modifie la réponse de l'événement pour renvoyer la réponse personnalisée
            $event->setResponse($response);
        }
    }

    /**
     * Gère les exceptions personnalisées.
     *
     * @param \Throwable $exception
     * @return JsonResponse|null
     */
    private function handleCustomExceptions(\Throwable $exception): ?JsonResponse
    {
        return match (true) {
            // Crée une réponse JSON avec un code HTTP 403 (Forbidden)
            $exception instanceof UserNotVerifiedException => new JsonResponse([
                'code' => 403, // Code HTTP 403 (Forbidden)
                'message' => 'Account not verified.', // Message spécifique pour l'exception
            ], 403),
            
            // Crée une réponse JSON avec un code HTTP 401 (Unauthorized)
            $exception instanceof UserDeleteException => new JsonResponse([
                'code' => 401, // Code HTTP 401 (Unauthorized)
                'message' => 'Invalid credentials.', // Message spécifique pour l'exception
            ], 401),

            // Crée une réponse JSON avec un code HTTP 403 (AccessDenied)
            $exception instanceof AccessDeniedException => new JsonResponse([
                'code' => 403, // Code HTTP 403 (AccessDenied)
                'message' => 'Access denied.', // Message spécifique pour l'exception
            ], 403),

            // Crée une réponse JSON avec un code HTTP 500 (Exception Server)
            $exception instanceof EntityNotFoundException => new JsonResponse([
                'code' => 500, // Code HTTP 500 (Exception Server)
                'message' => 'Entity not found.', // Message spécifique pour l'exception
            ], 500),

            // Crée une réponse JSON avec un code HTTP 500 (Exception Server)
            $exception instanceof InvalidArgumentException => new JsonResponse([
                'code' => 500, // Code HTTP 500 (Exception Server)
                'message' => 'Invalid argument.', // Message spécifique pour l'exception
            ], 500),

            // Crée une réponse JSON avec un code HTTP 500 (Exception Server)
            $exception instanceof EntityFoundException => new JsonResponse([
                'code' => 500, // Code HTTP 500 (Exception Server)
                'message' => 'Entity already exists.', // Message spécifique pour l'exception
            ], 500),

            // Crée une réponse JSON avec un code HTTP 500 (Exception Server)
            $exception instanceof InvalidFieldException => new JsonResponse([
                'code' => 500, // Code HTTP 500 (Exception Server)
                'message' => 'The field or its setter was not found in the entity.', // Message spécifique pour l'exception
            ], 500),

            // Crée une réponse JSON avec un code HTTP 401 (Unauthorized)
            $exception instanceof ChangePasswordException => new JsonResponse([
                'code' => 401, // Code HTTP 401 (Unauthorized)
                'message' => 'Change password.', // Message spécifique pour l'exception
            ], 401),

            // ajouter d'autres exceptions personnalisées ici

            default => null, // Si l'exception n'est pas gérée ici
        };
    }

    /**
     * Déclare les événements auxquels ce subscriber doit s'abonner.
     *
     * Cette méthode retourne un tableau associant les événements aux méthodes correspondantes.
     *
     * @return array Les événements et leurs gestionnaires respectifs.
     */
    public static function getSubscribedEvents(): array
    {
        // S'abonne à l'événement kernel.exception et le lie à la méthode onKernelException.
        return [
            'kernel.exception' => 'onKernelException',
        ];
    }
}
