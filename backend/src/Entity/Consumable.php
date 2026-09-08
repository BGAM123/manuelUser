<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use App\Repository\ConsumableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConsumableRepository::class)]
#[ORM\Table(name: 'consumable')]
#[ORM\HasLifecycleCallbacks]
class Consumable implements BlameableInterface
{
    use BlameableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['consumable:list', 'consumable:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom du consomptible est obligatoire.")]
    #[Assert\Length(max: 255, maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères.")]
    #[Groups(['consumable:list', 'consumable:detail'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['consumable:detail'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['consumable:list', 'consumable:detail'])]
    private ?string $unite_mesure = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['consumable:list', 'consumable:detail'])]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: AssetType::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['consumable:list', 'consumable:detail'])]
    private ?AssetType $assetType = null;

    #[ORM\ManyToOne(targetEntity: AssetSubType::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['consumable:list', 'consumable:detail'])]
    private ?AssetSubType $assetSubType = null;

    /**
     * Service propriétaire/gestionnaire de ce consomptible. Nullable : les consomptibles
     * existants n'ont pas de propriétaire connu tant qu'il n'est pas renseigné manuellement.
     */
    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['consumable:list', 'consumable:detail'])]
    private ?Service $service = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, options: ['default' => 0])]
    #[Assert\NotNull(message: "La quantité est obligatoire.")]
    #[Assert\PositiveOrZero(message: "La quantité doit être positive ou nulle.")]
    #[Groups(['consumable:list', 'consumable:detail'])]
    private ?string $quantite = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['consumable:list', 'consumable:detail'])]
    private ?string $prixInitial = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['consumable:list', 'consumable:detail'])]
    private ?string $prixTotal = null;

    /**
     * Cache du stock actuel, recalculé et persisté par ConsumableStockManager à chaque
     * mouvement (entrée/transfert/retour/consommation) — jusqu'ici cette valeur n'était
     * jamais persistée, seulement recalculée à la volée par ConsumableRepository::getStockActuel().
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['consumable:list', 'consumable:detail'])]
    private ?string $stockActuel = null;

    /**
     * @var Collection<int, PieceJointe>
     */
    #[ORM\ManyToMany(targetEntity: PieceJointe::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'consumable_piece_jointe')]
    #[ORM\JoinColumn(name: 'consumable_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'piece_jointe_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['consumable:detail'])]
    private Collection $pieceJointes;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['consumable:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['consumable:detail'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['consumable:list', 'consumable:detail'])]
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getUnite_mesure(): ?string
    {
        return $this->unite_mesure;
    }

    public function setUnite_mesure(?string $unite_mesure): static
    {
        $this->unite_mesure = $unite_mesure;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getAssetType(): ?AssetType
    {
        return $this->assetType;
    }

    public function setAssetType(?AssetType $assetType): static
    {
        $this->assetType = $assetType;
        return $this;
    }

    public function getAssetSubType(): ?AssetSubType
    {
        return $this->assetSubType;
    }

    public function setAssetSubType(?AssetSubType $assetSubType): static
    {
        $this->assetSubType = $assetSubType;
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

    public function getPrixInitial(): ?string
    {
        return $this->prixInitial;
    }

    public function setPrixInitial(?string $prixInitial): static
    {
        $this->prixInitial = $prixInitial;
        return $this;
    }

    public function getPrixTotal(): ?string
    {
        return $this->prixTotal;
    }

    public function setPrixTotal(?string $prixTotal): static
    {
        $this->prixTotal = $prixTotal;
        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
        return $this;
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
