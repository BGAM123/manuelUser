<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

final class LogService
{
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, pagination: array{page: int, limit: int, total: int, pages: int}}
     */
    public function search(int $page, int $limit, array $filters = []): array
    {
        $page = max(1, $page);
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $matches = [];

        $file = $this->getLogFile();
        if (is_file($file) && is_readable($file)) {
            $handle = new \SplFileObject($file, 'rb');
            while (!$handle->eof()) {
                $entry = $this->parseLine((string) $handle->fgets());
                if ($entry !== null && $this->matches($entry, $filters)) {
                    $matches[] = $entry;
                }
            }
        }

        usort($matches, static fn (array $left, array $right): int => strcmp(
            (string) $right['timestamp'],
            (string) $left['timestamp']
        ));

        $total = count($matches);
        $offset = ($page - 1) * $limit;

        return [
            'items' => array_values(array_slice($matches, $offset, $limit)),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
            ],
        ];
    }

    private function getLogFile(): string
    {
        return $this->kernel->getLogDir() . DIRECTORY_SEPARATOR . $this->kernel->getEnvironment() . '.log';
    }

    /**
     * Symfony's default line formatter places the structured context after API_ACTION.
     * Only that context is parsed; technical logs are ignored.
     *
     * @return array<string, mixed>|null
     */
    private function parseLine(string $line): ?array
    {
        $markerPosition = strpos($line, 'API_ACTION');
        if ($markerPosition === false) {
            return null;
        }

        $contextStart = strpos($line, '{', $markerPosition);
        $contextEnd = strrpos($line, '}');
        if ($contextStart === false || $contextEnd === false || $contextEnd < $contextStart) {
            return null;
        }

        $context = json_decode(substr($line, $contextStart, $contextEnd - $contextStart + 1), true);
        if (!is_array($context)) {
            return null;
        }

        if (!isset($context['timestamp'])) {
            $timestampEnd = strpos($line, ']', 1);
            $context['timestamp'] = $timestampEnd === false ? null : substr($line, 1, $timestampEnd - 1);
        }

        return $context;
    }

    /** @param array<string, mixed> $entry @param array<string, mixed> $filters */
    private function matches(array $entry, array $filters): bool
    {
        foreach (['user_id', 'resource_id', 'status_code'] as $key) {
            if (array_key_exists($key, $filters) && (string) ($entry[$key] ?? '') !== (string) $filters[$key]) {
                return false;
            }
        }

        foreach (['username', 'method', 'action', 'module', 'ip'] as $key) {
            if (array_key_exists($key, $filters) && strcasecmp((string) ($entry[$key] ?? ''), (string) $filters[$key]) !== 0) {
                return false;
            }
        }

        if (isset($filters['date_start']) && substr((string) ($entry['timestamp'] ?? ''), 0, 10) < $filters['date_start']) {
            return false;
        }
        if (isset($filters['date_end']) && substr((string) ($entry['timestamp'] ?? ''), 0, 10) > $filters['date_end']) {
            return false;
        }

        return true;
    }
}
