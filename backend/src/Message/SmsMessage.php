<?php

namespace App\Message;

class SmsMessage
{
    private string $phone;
    private string $message;
    private bool $normalize;
    private array $context;

    public function __construct(
        string $phone,
        string $message,
        bool $normalize = true,
        array $context = []
    ) {
        $this->phone = $phone;
        $this->message = $message;
        $this->normalize = $normalize;
        $this->context = $context;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function shouldNormalize(): bool
    {
        return $this->normalize;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}