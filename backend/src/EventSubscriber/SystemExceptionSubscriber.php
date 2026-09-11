<?php

namespace App\EventSubscriber;

use DateMalformedStringException;
use Doctrine\ORM\Query\QueryException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * EventSubscriber SystemExceptionSubscriber
 * 
 * Gère les exceptions système pour fournir des réponses adaptées.
 */
class SystemExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * Méthode appelée lorsqu'une exception est levée dans l'application.
     *
     * Cette méthode gère les exceptions système et fournit des réponses personnalisées.
     * 
     * @param ExceptionEvent $event L'événement de l'exception.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        // Récupère l'exception qui a été levée.
        $exception = $event->getThrowable();

        // Vérifie si l'exception est système
        $response = $this->handleSystemExceptions($exception);
        if ($response !== null) {
            // Modifie la réponse de l'événement pour renvoyer la réponse personnalisée
            $event->setResponse($response);
        }
    }

    /**
     * Gère les exceptions système.
     *
     * @param \Throwable $exception
     * @return JsonResponse|null
     */
    private function handleSystemExceptions(\Throwable $exception): ?JsonResponse
    {
        return match (true) {
            // Crée une réponse JSON avec un code HTTP 404 (NotFound)
            // Ordre 1
            $exception instanceof NotFoundHttpException => new JsonResponse([
                'code' => 404, // Code HTTP 404 (NotFound)
                'message' => 'No route found.', // Message spécifique pour l'exception
            ], 404),

            // Crée une réponse JSON avec un code HTTP 405 (MethodNotAllowed)
            // Ordre 2
            $exception instanceof MethodNotAllowedHttpException => new JsonResponse([
                'code' => 405, // Code HTTP 405 (MethodNotAllowed)
                'message' => 'No route found.', // Message spécifique pour l'exception
            ], 405),

            // Crée une réponse JSON avec un code HTTP 403 (AccessDenied)
            // Ordre 3
            $exception instanceof AccessDeniedHttpException => new JsonResponse([
                'code' => 403, // Code HTTP 403 (AccessDenied)
                'message' => 'Access denied.', // Message spécifique pour l'exception
            ], 403),

            // Crée une réponse JSON avec un code HTTP 400 (BadRequest)
            $exception instanceof BadRequestHttpException => new JsonResponse([
                'code' => 400, // Code HTTP 400 (BadRequest)
                'message' => 'Bad request.', // Message spécifique pour l'exception
            ], 400),
            
            // Crée une réponse JSON avec un code HTTP 500 (Exception Server)
            $exception instanceof QueryException => new JsonResponse([
                'code' => 500, // Code HTTP 500 (Exception Server)
                'message' => 'Query exception.', // Message spécifique pour l'exception
            ], 500),

            // Crée une réponse JSON avec un code HTTP 500 (Exception Server)
            $exception instanceof DateMalformedStringException => new JsonResponse([
                'code' => 500, // Code HTTP 500 (Exception Server)
                'message' => 'Malformed string date.', // Message spécifique pour l'exception
            ], 500),

            // Crée une réponse JSON avec un code HTTP 500 (Exception Server)
            $exception instanceof SyntaxErrorException => new JsonResponse([
                'code' => 500, // Code HTTP 500 (Exception Server)
                'message' => 'Syntax error.', // Message spécifique pour l'exception
            ], 500),
            
            // ajouter d'autres exceptions système ici

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
