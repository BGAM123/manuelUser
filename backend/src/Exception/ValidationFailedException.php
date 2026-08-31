<?php

namespace App\Exception;

/**
 * Erreur de validation métier — HTTP 400 avec détails dans data.
 */
final class ValidationFailedException extends \InvalidArgumentException
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'La validation a échoué.',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
