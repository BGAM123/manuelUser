<?php

// src/EventSubscriber/CorsSubscriber.php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gère les headers CORS pour permettre au frontend (port 8074) d'appeler
 * l'API (port 8075) sans être bloqué par le navigateur.
 */
final class CorsSubscriber implements EventSubscriberInterface
{
    // Origines autorisées — ajouter d'autres ports/domaines si nécessaire
    private const ALLOWED_ORIGINS = [
        'http://185.98.136.192:8074',
        'http://localhost:8080',
        'http://localhost:5173',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            // Priorité haute (256) pour intercepter AVANT les firewalls Symfony
            KernelEvents::REQUEST  => ['onKernelRequest',  256],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    /**
     * Répond immédiatement aux requêtes OPTIONS (preflight) avec 204.
     * Sans cela, le navigateur bloque la vraie requête avant même qu'elle parte.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->getMethod() !== 'OPTIONS') {
            return;
        }

        $origin = $request->headers->get('Origin', '');

        $response = new Response('', Response::HTTP_NO_CONTENT);
        $this->addCorsHeaders($response, $origin);

        $event->setResponse($response);
    }

    /**
     * Ajoute les headers CORS à toutes les réponses sortantes.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request  = $event->getRequest();
        $response = $event->getResponse();
        $origin   = $request->headers->get('Origin', '');

        $this->addCorsHeaders($response, $origin);
    }

    private function addCorsHeaders(Response $response, string $origin): void
    {
        // Autoriser l'origine si elle fait partie de la liste blanche
        if (in_array($origin, self::ALLOWED_ORIGINS, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        } else {
            // En dev ou si origine inconnue, autoriser tout (adapter en prod si besoin)
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With');
        $response->headers->set('Access-Control-Max-Age', '3600');
    }
}
