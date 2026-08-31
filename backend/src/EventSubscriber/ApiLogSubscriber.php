<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ApiLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.api_action')]
        private readonly LoggerInterface $logger,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -10],
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || 'OPTIONS' === $request->getMethod()) {
            return;
        }

        $request->attributes->set('_api_log_started_at', microtime(true));
        $request->attributes->set('_api_log_request_id', $request->headers->get('X-Request-ID') ?: bin2hex(random_bytes(16)));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->attributes->has('_api_log_started_at')) {
            return;
        }

        $response = $event->getResponse();
        $user = $this->getUser();
        $path = $request->getPathInfo();
        $routeParameters = $request->attributes->get('_route_params', []);
        $resourceId = isset($routeParameters['id']) && ctype_digit((string) $routeParameters['id'])
            ? (int) $routeParameters['id']
            : null;

        try {
            $requestId = (string) $request->attributes->get('_api_log_request_id');
            $response->headers->set('X-Request-ID', $requestId);

            $this->logger->info('API_ACTION', [
                'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'user_id' => $this->getUserId($user),
                'username' => $user?->getUserIdentifier(),
                'method' => $request->getMethod(),
                'route' => $request->attributes->get('_route'),
                'path' => $path,
                'action' => $this->actionFor($request->getMethod()),
                'module' => $this->moduleFor($path),
                'resource_id' => $resourceId,
                'ip' => $request->getClientIp(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - (float) $request->attributes->get('_api_log_started_at')) * 1000),
                'user_agent' => $request->headers->get('User-Agent'),
                'environment' => $_SERVER['APP_ENV'] ?? null,
                'request_id' => $requestId,
            ]);
        } catch (\Throwable) {
            // La journalisation ne doit jamais modifier le résultat de l'API.
        }
    }

    private function getUser(): ?UserInterface
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        return $user instanceof UserInterface ? $user : null;
    }

    private function getUserId(?UserInterface $user): ?int
    {
        if ($user === null || !method_exists($user, 'getId')) {
            return null;
        }

        $id = $user->getId();
        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    private function actionFor(string $method): string
    {
        return match ($method) {
            'POST' => 'CREATE',
            'PUT', 'PATCH' => 'UPDATE',
            'DELETE' => 'DELETE',
            default => 'READ',
        };
    }

    private function moduleFor(string $path): ?string
    {
        $segment = explode('/', trim($path, '/'))[0] ?? '';
        if ($segment === '' || str_starts_with($segment, '_')) {
            return null;
        }

        return match ($segment) {
            'assets' => 'asset',
            'asset-exits' => 'asset_exit',
            'asset-assignments' => 'asset_assignment',
            'asset-maintenances' => 'asset_maintenance',
            'asset-reevaluations' => 'asset_reevaluation',
            'asset-depreciations' => 'asset_depreciation',
            'consumable-transfers' => 'consumable_transfer',
            default => rtrim(str_replace('-', '_', $segment), 's'),
        };
    }
}
