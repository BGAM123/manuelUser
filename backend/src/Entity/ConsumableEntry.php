<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use App\Repository\ConsumableEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConsumableEntryRepository::class)]
#[ORM\Table(name: 'consumable_entry')]
#[ORM\HasLifecycleCallbacks]
class ConsumableEntry implements BlameableInterface
{
    use BlameableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['consumable_entry:list', 'consumable_entry:detail'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Consumable::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['consumable_entry:list', 'consumable_entry:detail'])]
    private ?Consumable $consumable = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['consumable_entry:list', 'consumable_entry:detail'])]
    private ?Service $service = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Assert\NotNull(message: "La quantité est obligatoire.")]
    #[Assert\Positive(message: "La quantité doit être supérieure à 0.")]
    #[Groups(['consumable_entry:list', 'consumable_entry:detail'])]
    private ?string $quantite = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['consumable_entry:list', 'consumable_entry:detail'])]
    private ?\DateTimeInterface $dateEntree = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['consumable_entry:detail'])]
    private ?string $observations = null;

    /**
     * @var Collection<int, PieceJointe>
     */
    #[ORM\ManyToMany(targetEntity: PieceJointe::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'consumable_entry_piece_jointe')]
    #[ORM\JoinColumn(name: 'consumable_entry_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'piece_jointe_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['consumable_entry:detail'])]
    private Collection $pieceJointes;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['consumable_entry:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['consumable_entry:detail'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['consumable_entry:list', 'consumable_entry:detail'])]
    private bool $isDelete = false;

    public function __construct()
    {
        $this->pieceJointes = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        if (null === $this->dateEntree) {
            $this->dateEntree = new \DateTime();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsumable(): ?Consumable
    {
        return $this->consumable;
    }

    public function setConsumable(Consumable $consumable): static
    {
        $this->consumable = $consumable;
        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(Service $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function getQuantite(): ?string
    {
        return $this->quantite;
    }

    public function setQuantite(string $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getDateEntree(): ?\DateTimeInterface
    {
        return $this->dateEntree;
    }

    public function setDateEntree(?\DateTimeInterface $dateEntree): static
    {
        $this->dateEntree = $dateEntree;
        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations;
        return $this;
    }

    /**
     * @return Collection<int, PieceJointe>
     */
    public function getPieceJointes(): Collection
    {
        return $this->pieceJointes;
    }

    public function addPieceJointe(PieceJointe $pieceJointe): static
    {
        if (!$this->pieceJointes->contains($pieceJointe)) {
            $this->pieceJointes->add($pieceJointe);
        }
        return $this;
    }

    public function removePieceJointe(PieceJointe $pieceJointe): static
    {
        $this->pieceJointes->removeElement($pieceJointe);
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

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
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
