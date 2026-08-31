<?php

namespace App\Entity;

use App\Repository\SecurityDocumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SecurityDocumentRepository::class)]
#[ORM\Table(name: 'security_document')]
#[ORM\HasLifecycleCallbacks]
class SecurityDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['security:detail', 'asset:detail', 'piece_jointe:detail'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'securityDocuments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['piece_jointe:detail'])]
    private ?Security $security = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['security:detail', 'asset:detail'])]
    private ?PieceJointe $pieceJointe = null;

    #[ORM\Column]
    private bool $isDelete = false;

    #[ORM\Column]
    #[Groups(['security:detail', 'asset:detail', 'piece_jointe:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['security:detail', 'asset:detail', 'piece_jointe:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSecurity(): ?Security
    {
        return $this->security;
    }

    public function setSecurity(?Security $security): static
    {
        $this->security = $security;
        return $this;
    }

    public function getPieceJointe(): ?PieceJointe
    {
        return $this->pieceJointe;
    }

    public function setPieceJointe(?PieceJointe $pieceJointe): static
    {
        $this->pieceJointe = $pieceJointe;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
