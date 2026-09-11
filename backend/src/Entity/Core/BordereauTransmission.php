<?php

namespace App\Entity\Core;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\Core\BordereauTransmissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BordereauTransmissionRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['bordereau_transmission:read']],
    denormalizationContext: ['groups' => ['bordereau_transmission:write']]
)]
class BordereauTransmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['bordereau_transmission:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['bordereau_transmission:read', 'bordereau_transmission:write'])]
    private array $courrierIds = [];

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['bordereau_transmission:read', 'bordereau_transmission:write'])]
    private ?int $correspondantId = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['bordereau_transmission:read', 'bordereau_transmission:write'])]
    private ?string $numeroReference = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['bordereau_transmission:read', 'bordereau_transmission:write'])]
    private ?int $nombrePieceJointe = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['bordereau_transmission:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['bordereau_transmission:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourrierIds(): array
    {
        return $this->courrierIds;
    }

    public function setCourrierIds(array $courrierIds): static
    {
        $this->courrierIds = $courrierIds;

        return $this;
    }

    public function getCorrespondantId(): ?int
    {
        return $this->correspondantId;
    }

    public function setCorrespondantId(?int $correspondantId): static
    {
        $this->correspondantId = $correspondantId;

        return $this;
    }

    public function getNumeroReference(): ?string
    {
        return $this->numeroReference;
    }

    public function setNumeroReference(string $numeroReference): static
    {
        $this->numeroReference = $numeroReference;

        return $this;
    }

    public function getNombrePieceJointe(): ?int
    {
        return $this->nombrePieceJointe;
    }

    public function setNombrePieceJointe(int $nombrePieceJointe): static
    {
        $this->nombrePieceJointe = $nombrePieceJointe;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
