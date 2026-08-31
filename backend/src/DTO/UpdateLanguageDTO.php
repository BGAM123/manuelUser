<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateLanguageDTO
{
    #[Assert\NotBlank(message: 'La langue est requise.')]
    #[Assert\Choice(
        choices: ['fr', 'en', 'es', 'de', 'it'],
        message: 'Langue non supportée. Choisissez parmi: fr, en, es, de, it'
    )]
    private string $langue;

    public function getLangue(): string
    {
        return $this->langue;
    }

    public function setLangue(string $langue): self
    {
        $this->langue = $langue;
        return $this;
    }
}