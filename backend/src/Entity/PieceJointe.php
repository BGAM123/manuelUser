<?php

// src/Entity/PieceJointe.php

namespace App\Entity;

use App\Repository\PieceJointeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fichier (photo ou document) pouvant être lié à un ou plusieurs biens.
 */
#[ORM\Entity(repositoryClass: PieceJointeRepository::class)]
#[ORM\Table(name: 'piece_jointe')]
class PieceJointe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['piece_jointe:list', 'piece_jointe:detail', 'asset:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['piece_jointe:list', 'piece_jointe:detail', 'asset:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank(message: 'Le chemin est obligatoire.')]
    #[Assert\Length(max: 500)]
    #[Groups(['piece_jointe:list', 'piece_jointe:detail', 'asset:detail'])]
    private ?string $chemin = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getChemin(): ?string
    {
        return $this->chemin;
    }

    public function setChemin(string $chemin): static
    {
        $this->chemin = $chemin;

        return $this;
    }

    /**
     * Distinction photos / documents via le sous-répertoire du chemin.
     */
    public function isPhoto(): bool
    {
        return str_contains((string) $this->chemin, '/photos/');
    }
}
