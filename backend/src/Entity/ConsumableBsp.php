<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use App\Repository\ConsumableBspRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ConsumableBspRepository::class)]
#[ORM\Table(name: 'consumable_bsp')]
#[ORM\HasLifecycleCallbacks]
class ConsumableBsp implements BlameableInterface
{
    use BlameableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['consumable_bsp:detail'])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: ConsumableTransfer::class, inversedBy: 'consumableBsp')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    #[Groups(['consumable_bsp:detail'])]
    private ?ConsumableTransfer $consumableTransfer = null;

    #[ORM\OneToOne(targetEntity: Bsp::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    #[Groups(['consumable_bsp:detail'])]
    private ?Bsp $bsp = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['consumable_bsp:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['consumable_bsp:detail'])]
    private bool $isDelete = false;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsumableTransfer(): ?ConsumableTransfer
    {
        return $this->consumableTransfer;
    }

    public function setConsumableTransfer(ConsumableTransfer $consumableTransfer): static
    {
        $this->consumableTransfer = $consumableTransfer;
        return $this;
    }

    public function getBsp(): ?Bsp
    {
        return $this->bsp;
    }

    public function setBsp(Bsp $bsp): static
    {
        $this->bsp = $bsp;
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

    public function isDelete(): bool
    {
        return $this->isDelete;
    }

    public function setDelete(bool $isDelete): static
    {
        $this->isDelete = $isDelete;
        return $this;
    }
}
