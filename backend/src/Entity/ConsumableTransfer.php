<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use App\Repository\ConsumableTransferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConsumableTransferRepository::class)]
#[ORM\Table(name: 'consumable_transfer')]
#[ORM\HasLifecycleCallbacks]
class ConsumableTransfer implements BlameableInterface
{
    use BlameableTrait;

    public const TYPE_INITIAL = 'INITIAL';
    public const TYPE_TRANSFERT_DIRECT = 'TRANSFERT_DIRECT';
    public const TYPE_BSP = 'BSP';
    

    public const STATUT_INITIAL = 'INITIAL';
    public const STATUT_SORTI = 'SORTI';
    public const STATUT_TRANSFERE = 'TRANSFERE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Consumable::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
    private ?Consumable $consumable = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
    private ?Service $serviceDestination = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
    private ?Service $serviceSource = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Le type est obligatoire.")]
    #[Assert\Choice(choices: [self::TYPE_TRANSFERT_DIRECT, self::TYPE_BSP], message: "Le type doit être TRANSFERT_DIRECT ou BSP.")]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
    private ?string $type = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::STATUT_SORTI, self::STATUT_TRANSFERE], message: "Le statut doit être SORTI ou TRANSFERE.")]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
    private ?string $statut = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Assert\NotNull(message: "La quantité est obligatoire.")]
    #[Assert\Positive(message: "La quantité doit être supérieure à 0.")]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
    private ?string $quantite = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
    private ?\DateTimeInterface $dateTransfert = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['consumable_transfer:detail'])]
    private ?string $observations = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])] // ← AJOUTÉ dans les groupes
    private ?string $stockActuel = null;

    /**
     * @var Collection<int, PieceJointe>
     */
    #[ORM\ManyToMany(targetEntity: PieceJointe::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'consumable_transfer_piece_jointe')]
    #[ORM\JoinColumn(name: 'consumable_transfer_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'piece_jointe_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['consumable_transfer:detail'])]
    private Collection $pieceJointes;

    #[ORM\OneToOne(targetEntity: ConsumableBsp::class, mappedBy: 'consumableTransfer')]
    private ?ConsumableBsp $consumableBsp = null;

    /**
     * Cache dénormalisé pour un listing rapide sans jointure vers AcknowledgementOfReceipt
     * (source de vérité réelle, gérée par AcknowledgementService). Tenu à jour à chaque
     * accusé de réception, y compris via le lot (BatchAcknowledgeConsumableTransferController).
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
    private bool $isAcknowledged = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
    private ?\DateTimeInterface $acknowledgedAt = null;

    /**
     * Quantité effectivement consommée sur ce transfert. Doit rester <= quantite (la
     * quantité transférée == reçue par le service de destination).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true, options: ['default' => 0])]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
    private ?string $quantityConsumed = '0';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['consumable_transfer:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['consumable_transfer:detail'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['consumable_transfer:list', 'consumable_transfer:detail'])]
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
        if (null === $this->dateTransfert) {
            $this->dateTransfert = new \DateTime();
        }
        // Fixer le statut selon le type
        if ($this->type === self::TYPE_BSP) {
            $this->statut = self::STATUT_SORTI;
        } elseif ($this->type === self::TYPE_TRANSFERT_DIRECT) {
            $this->statut = self::STATUT_TRANSFERE;
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

    public function getServiceDestination(): ?Service
    {
        return $this->serviceDestination;
    }


    public function getStockActuel(): ?string
    {
        return $this->stockActuel;
    }

    public function setStockActuel(?string $stockActuel): static
    {
        $this->stockActuel = $stockActuel;
        return $this;
    }

    public function setServiceDestination(Service $serviceDestination): static
    {
        $this->serviceDestination = $serviceDestination;
        return $this;
    }

    public function getServiceSource(): ?Service
    {
        return $this->serviceSource;
    }

    public function setServiceSource(?Service $serviceSource): static
    {
        $this->serviceSource = $serviceSource;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
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

    public function getDateTransfert(): ?\DateTimeInterface
    {
        return $this->dateTransfert;
    }

    public function setDateTransfert(?\DateTimeInterface $dateTransfert): static
    {
        $this->dateTransfert = $dateTransfert;
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

    public function getConsumableBsp(): ?ConsumableBsp
    {
        return $this->consumableBsp;
    }

    public function setConsumableBsp(?ConsumableBsp $consumableBsp): static
    {
        $this->consumableBsp = $consumableBsp;
        return $this;
    }

    public function isAcknowledged(): bool
    {
        return $this->isAcknowledged;
    }

    public function setIsAcknowledged(bool $isAcknowledged): static
    {
        $this->isAcknowledged = $isAcknowledged;
        return $this;
    }

    public function getAcknowledgedAt(): ?\DateTimeInterface
    {
        return $this->acknowledgedAt;
    }

    public function setAcknowledgedAt(?\DateTimeInterface $acknowledgedAt): static
    {
        $this->acknowledgedAt = $acknowledgedAt;
        return $this;
    }

    public function getQuantityConsumed(): ?string
    {
        return $this->quantityConsumed;
    }

    public function setQuantityConsumed(?string $quantityConsumed): static
    {
        $this->quantityConsumed = $quantityConsumed;
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
