<?php

namespace App\Entity;

use App\Repository\AssetSecurityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AssetSecurityRepository::class)]
#[ORM\Table(name: 'asset_security')]
#[ORM\HasLifecycleCallbacks]
class AssetSecurity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['security:detail', 'asset:detail'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'assetSecurities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['security:detail', 'asset:detail'])]
    private ?Security $security = null;

    #[ORM\ManyToOne(inversedBy: 'assetSecurities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['security:detail'])]
    private ?Asset $asset = null;

    #[ORM\Column]
    private bool $isDelete = false;

    #[ORM\Column]
    #[Groups(['security:detail', 'asset:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['security:detail', 'asset:detail'])]
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

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): static
    {
        $this->asset = $asset;
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
