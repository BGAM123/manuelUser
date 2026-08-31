<?php
// src/Entity/ExitType.php

namespace App\Entity;

use App\Repository\ExitTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExitTypeRepository::class)]
#[ORM\Table(name: 'exit_type')]
#[ORM\HasLifecycleCallbacks]
class ExitType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['exit_type:list', 'exit_type:detail', 'asset_exit:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['exit_type:list', 'exit_type:detail', 'asset_exit:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['exit_type:detail', 'asset_exit:detail'])]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['exit_type:list', 'exit_type:detail', 'asset_exit:detail'])]
    private ?string $code = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['exit_type:list', 'exit_type:detail'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['exit_type:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['exit_type:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['exit_type:list', 'exit_type:detail'])]
    private bool $isDelete = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['exit_type:list', 'exit_type:detail', 'asset_exit:detail'])]
    private bool $beneficiaire = false;

    /**
     * @var Collection<int, AssetExit>
     */
    #[ORM\OneToMany(mappedBy: 'exitType', targetEntity: AssetExit::class)]
    private Collection $assetExits;

    public function __construct()
    {
        $this->assetExits = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    public function isDelete(): bool
    {
        return $this->isDelete;
    }

    public function setIsDelete(bool $isDelete): static
    {
        $this->isDelete = $isDelete;
        return $this;
    }

    public function isBeneficiaire(): bool
    {
        return $this->beneficiaire;
    }

    public function setBeneficiaire(bool $beneficiaire): static
    {
        $this->beneficiaire = $beneficiaire;
        return $this;
    }

    /**
     * @return Collection<int, AssetExit>
     */
    public function getAssetExits(): Collection
    {
        return $this->assetExits;
    }

    public function addAssetExit(AssetExit $assetExit): static
    {
        if (!$this->assetExits->contains($assetExit)) {
            $this->assetExits->add($assetExit);
            $assetExit->setExitType($this);
        }
        return $this;
    }

    public function removeAssetExit(AssetExit $assetExit): static
    {
        if ($this->assetExits->removeElement($assetExit)) {
            if ($assetExit->getExitType() === $this) {
                $assetExit->setExitType(null);
            }
        }
        return $this;
    }
}
