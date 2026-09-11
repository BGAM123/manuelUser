<?php

namespace App\Entity\Cour;

use App\Repository\Cour\PieceJointeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PieceJointeRepository::class)]
#[ORM\Table(name: 'cour_piece_jointe')]
#[ORM\HasLifecycleCallbacks]
class PieceJointe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, nullable: false)]
    #[Groups(['GetCollection:PieceJointe', 'Get:PieceJointe'])]
    private ?int $id = null;

    #[ORM\Column(name: 'nom', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:PieceJointe', 'Get:PieceJointe'])]
    private ?string $nom = null;

    #[ORM\Column(name: 'intitule', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:PieceJointe', 'Get:PieceJointe'])]
    private ?string $intitule = null;

    #[ORM\Column(name: 'chemin', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:PieceJointe', 'Get:PieceJointe'])]
    private ?string $chemin = null;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:PieceJointe', 'Get:PieceJointe'])]
    private ?string $type = null;

    #[ORM\Column(name: 'id_parent', type: Types::INTEGER, nullable: true)]
    #[Groups(['GetCollection:PieceJointe', 'Get:PieceJointe'])]
    private ?int $idParent = null;

    #[ORM\Column(name: 'type_parent', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:PieceJointe', 'Get:PieceJointe'])]
    private ?string $typeParent = null;

    #[ORM\ManyToOne(targetEntity: Transmission::class, inversedBy: 'piecesJointes')]
    #[ORM\JoinColumn(name: 'id_transmission', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Groups(['GetCollection:PieceJointe', 'Get:PieceJointe'])]
    private ?Transmission $transmission = null;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:PieceJointe', 'Get:PieceJointe'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:PieceJointe', 'Get:PieceJointe'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'search', type: Types::STRING, length: 1000, nullable: true)]
    private ?string $search = null;

    // ──────────────── GETTERS / SETTERS ────────────────

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
        $this->nom = trim($nom ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getIntitule(): ?string
    {
        return $this->intitule;
    }

    public function setIntitule(?string $intitule): static
    {
        $this->intitule = trim($intitule ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getChemin(): ?string
    {
        return $this->chemin;
    }

    public function setChemin(?string $chemin): static
    {
        $this->chemin = trim($chemin ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = trim($type ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getIdParent(): ?int
    {
        return $this->idParent;
    }

    public function setIdParent(?int $idParent): static
    {
        $this->idParent = $idParent;
        return $this;
    }

    public function getTypeParent(): ?string
    {
        return $this->typeParent;
    }

    public function setTypeParent(?string $typeParent): static
    {
        $this->typeParent = trim($typeParent ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function isDelete(): bool
    {
        return $this->isDelete;
    }

    public function setDelete(bool $isDelete): static
    {
        $this->isDelete = $isDelete;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateSearchField(): void
    {
        $this->search = implode(' <br/> ', array_filter([
            $this->nom,
            $this->intitule,
            $this->type,
            $this->typeParent,
            $this->chemin,
        ]));
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function setSearch(?string $search): static
    {
        $this->search = $search;
        return $this;
    }

    public function getTransmission(): ?Transmission
    {
        return $this->transmission;
    }

    public function setTransmission(?Transmission $transmission): static
    {
        $this->transmission = $transmission;
        return $this;
    }
}
