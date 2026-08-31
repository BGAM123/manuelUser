<?php

namespace App\EventSubscriber;

use App\Exception\ResourceInUseException;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException as SymfonyValidationFailedException;

final class ExceptionSubscriber implements EventSubscriberInterface
{
    /** @var array<string, array{0: string, 1: bool}> path prefix => [label, feminine] */
    private const RESOURCE_MESSAGES = [
        '/assets' => ['bien', false],
        '/users' => ['utilisateur', false],
        '/projects' => ['projet', false],
        '/services' => ['service', false],
        '/regions' => ['région', true],
        '/departements' => ['département', false],
        '/arrondissements' => ['arrondissement', false],
        '/categories' => ['catégorie', true],
        '/asset-types' => ['type de bien', false],
        '/etat-biens' => ['état de bien', false],
        '/roles' => ['rôle', false],
        '/permissions' => ['permission', true],
        '/type-organigrammes' => ["type d'organigramme", false],
        '/champs' => ['champ', false],
        '/upload' => ['fichier', false],
        '/asset-sub-types' => ['sous-type de bien', false],
        '/groupes' => ['groupe', false],
        '/consumables' => ['consomptible', false],
        '/consumable-entries' => ['entrée de consomptible', true],
        '/consumable-transfers' => ['transfert de consomptible', false],
        '/securities' => ['sécurisation', true],
        '/bsps' => ['BSP', false],
        '/inputs' => ['valeur de champ', true],
    ];

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.exception' => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Validation métier
        if ($exception instanceof ValidationFailedException) {
            $event->setResponse($this->json(
                false,
                Response::HTTP_BAD_REQUEST,
                $exception->getMessage() ?: 'La validation a échoué.',
                $exception->getErrors()
            ));

            return;
        }

        // InputBag::get() sur un tableau (Symfony 6.3+)
        if ($exception instanceof BadRequestException) {
            $event->setResponse($this->json(
                false,
                Response::HTTP_BAD_REQUEST,
                'La validation a échoué.',
                ['request' => $exception->getMessage()]
            ));

            return;
        }

        if ($exception instanceof SymfonyValidationFailedException) {
            $violations = $exception->getViolations();
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            $event->setResponse($this->json(
                false,
                Response::HTTP_BAD_REQUEST,
                'La validation a échoué.',
                $errors
            ));

            return;
        }

        // Ressource introuvable (custom)
        if ($exception instanceof ResourceNotFoundException) {
            $event->setResponse($this->json(false, Response::HTTP_NOT_FOUND, $exception->getMessage(), null));

            return;
        }

        // Suppression définitive impossible : ressource liée par une contrainte FK
        if ($exception instanceof ResourceInUseException) {
            $event->setResponse($this->json(false, Response::HTTP_BAD_REQUEST, $exception->getMessage(), null));

            return;
        }

        // NotFoundHttpException (MapEntity / EntityValueResolver)
        if ($exception instanceof NotFoundHttpException || $exception instanceof EntityNotFoundException) {
            $message = $this->resolveNotFoundMessage($request->getPathInfo(), $exception->getMessage());
            $event->setResponse($this->json(false, Response::HTTP_NOT_FOUND, $message, null));

            return;
        }

        if ($exception instanceof UnauthorizedHttpException) {
            $event->setResponse($this->json(
                false,
                Response::HTTP_UNAUTHORIZED,
                $exception->getMessage() ?: 'Authentification requise.',
                null
            ));

            return;
        }

        if ($exception instanceof AccessDeniedHttpException || $exception instanceof AccessDeniedException) {
            $event->setResponse($this->json(
                false,
                Response::HTTP_FORBIDDEN,
                $exception->getMessage() ?: 'Accès refusé.',
                null
            ));

            return;
        }

        // InvalidArgumentException JSON (validation legacy)
        if ($exception instanceof \InvalidArgumentException) {
            $decoded = json_decode($exception->getMessage(), true);
            if (is_array($decoded)) {
                $event->setResponse($this->json(false, Response::HTTP_BAD_REQUEST, 'La validation a échoué.', $decoded));

                return;
            }
            $msg = $exception->getMessage();
            $status = str_contains(mb_strtolower($msg), 'introuvable')
                ? Response::HTTP_NOT_FOUND
                : (str_contains(mb_strtolower($msg), 'déjà') ? Response::HTTP_CONFLICT : Response::HTTP_BAD_REQUEST);
            $event->setResponse($this->json(false, $status, $msg, null));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage() ?: Response::$statusTexts[$statusCode] ?? 'Erreur HTTP.';
            if (Response::HTTP_NOT_FOUND === $statusCode) {
                $message = $this->resolveNotFoundMessage($request->getPathInfo(), $message);
            }
            $event->setResponse($this->json(false, $statusCode, $message, null));

            return;
        }

        // Erreurs serveur réelles
        $isProd = 'prod' === $this->kernel->getEnvironment();
        $message = $isProd
            ? 'Une erreur interne du serveur est survenue.'
            : ($exception->getMessage() ?: 'Une erreur interne du serveur est survenue.');

        $event->setResponse($this->json(false, Response::HTTP_INTERNAL_SERVER_ERROR, $message, null));
    }

    private function resolveNotFoundMessage(string $path, string $fallback): string
    {
        // Message Doctrine/Symfony générique → message métier FR
        $isGeneric = str_contains($fallback, 'object not found')
            || str_contains($fallback, 'EntityValueResolver')
            || str_contains($fallback, 'Not Found')
            || '' === trim($fallback);

        foreach (self::RESOURCE_MESSAGES as $prefix => [$label, $feminine]) {
            if (str_starts_with($path, $prefix)) {
                return $feminine
                    ? sprintf('La %s demandée est introuvable.', $label)
                    : sprintf('Le %s demandé est introuvable.', $label);
            }
        }

        if ($isGeneric) {
            return 'La ressource demandée est introuvable.';
        }

        return $fallback;
    }

    private function json(bool $success, int $status, string $message, mixed $data): JsonResponse
    {
        return new JsonResponse([
            'success' => $success,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
