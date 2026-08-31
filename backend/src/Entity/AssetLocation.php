<?php

namespace App\Entity;

use App\Repository\AssetLocationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AssetLocationRepository::class)]
#[ORM\Table(name: 'asset_location')]
#[ORM\HasLifecycleCallbacks]
class AssetLocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['asset:detail', 'location:detail'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'assetLocations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['location:detail'])]
    private ?Asset $asset = null;

    #[ORM\ManyToOne(inversedBy: 'assetLocations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['asset:detail'])]
    private ?Location $location = null;

    // ✅ AJOUTER cette propriété
    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[Groups(['asset:detail', 'location:detail'])]
    private bool $isDelete = false;

    #[ORM\Column]
    #[Groups(['asset:detail', 'location:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['asset:detail', 'location:detail'])]
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

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): static
    {
        $this->asset = $asset;
        return $this;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): static
    {
        $this->location = $location;
        return $this;
    }

    // ✅ AJOUTER les getters et setters pour isDelete
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