<?php

namespace App\Entity;

use App\Repository\InputRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InputRepository::class)]
#[ORM\Table(name: 'input')]
#[ORM\HasLifecycleCallbacks]
class Input
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['input:list', 'input:detail', 'champ:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La valeur du champ est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'La valeur ne doit pas dépasser {{ limit }} caractères.')]
    #[Groups(['input:list', 'input:detail', 'champ:detail'])]
    private ?string $valeur = null;

    // ❌ SUPPRIMER complètement cette propriété
    // #[ORM\Column(nullable: true)]
    // #[Groups(['input:detail', 'champ:detail'])]
    // private ?int $ordre = null;

    #[ORM\ManyToOne(inversedBy: 'inputs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['input:detail'])]
    private ?Champ $champ = null;

    #[ORM\ManyToMany(targetEntity: Asset::class, mappedBy: 'inputs')]
    private Collection $assets;

    #[ORM\Column]
    #[Groups(['input:detail'])]
    private bool $isDelete = false;

    #[ORM\Column]
    #[Groups(['input:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['input:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->assets = new ArrayCollection();
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

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    // ✅ AJOUTER cette méthode
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function setValeur(string $valeur): static
    {
        $this->valeur = $valeur;
        return $this;
    }

    // ❌ SUPPRIMER ces méthodes
    // public function getOrdre(): ?int
    // {
    //     return $this->ordre;
    // }

    // public function setOrdre(?int $ordre): static
    // {
    //     $this->ordre = $ordre;
    //     return $this;
    // }

    public function getChamp(): ?Champ
    {
        return $this->champ;
    }

    public function setChamp(?Champ $champ): static
    {
        $this->champ = $champ;
        return $this;
    }

    /**
     * @return Collection<int, Asset>
     */
    public function getAssets(): Collection
    {
        return $this->assets;
    }

    public function addAsset(Asset $asset): static
    {
        if (!$this->assets->contains($asset)) {
            $this->assets->add($asset);
            $asset->addInput($this);
        }
        return $this;
    }

    public function removeAsset(Asset $asset): static
    {
        if ($this->assets->removeElement($asset)) {
            $asset->removeInput($this);
        }
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