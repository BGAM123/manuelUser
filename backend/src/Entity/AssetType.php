<?php

// src/Entity/AssetType.php

namespace App\Entity;

use App\Repository\AssetTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Type de bien (ex. Véhicule léger, Camion, Bâtiment administratif, Ordinateur de bureau...).
 *
 * AssetType est l'ENFANT dans la relation parent-enfant avec Category :
 * chaque AssetType est obligatoirement rattaché à une Category.
 */
#[ORM\Entity(repositoryClass: AssetTypeRepository::class)]
#[UniqueEntity(fields: ['nom'], message: "Ce nom de type de bien est déjà utilisé.")]
class AssetType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['asset_type:list', 'asset_type:detail', 'etat_bien:list', 'etat_bien:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: "Le nom du type de bien est obligatoire.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le nom du type de bien ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['asset_type:list', 'asset_type:detail', 'etat_bien:list', 'etat_bien:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['asset_type:list', 'asset_type:detail'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de création est obligatoire.")]
    #[Groups(['asset_type:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de mise à jour est obligatoire.")]
    #[Groups(['asset_type:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['asset_type:list', 'asset_type:detail'])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'is_default', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_default')]
    #[Groups(['asset_type:list', 'asset_type:detail'])]
    private bool $isDefault = false;

    /**
     * Durée de vie estimée du bien en années (ex. 5, 10, 20).
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'La durée de vie doit être un entier positif ou zéro.')]
    #[Groups(['asset_type:list', 'asset_type:detail'])]
    private ?int $dureeVie = null;

    /**
     * Taux d'amortissement (ex. 10.00, 20.00, 25.00).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le taux doit être un nombre positif ou zéro.')]
    #[Groups(['asset_type:list', 'asset_type:detail'])]
    private ?string $taux = null;

    /**
     * Catégorie parente. Obligatoire : un type de bien ne peut pas exister sans catégorie.
     * onDelete: RESTRICT -> même si quelqu'un tente une suppression physique de Category
     * (cf. pattern DeleteProjectController), la base refusera si des AssetType y sont
     * encore rattachés. La suppression logique, elle, est gérée en amont côté application
     * (cf. CategoryRepository::countActiveAssetTypes).
     */
    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'assetTypes')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: "La catégorie est obligatoire.")]
    private ?Category $category = null;

    /**
     * États de bien autorisés pour ce type (ManyToMany inverse).
     *
     * @var Collection<int, EtatBien>
     */
    #[ORM\ManyToMany(targetEntity: EtatBien::class, mappedBy: 'assetTypes')]
    #[Groups(['asset_type:detail'])]
    private Collection $etatBiens;

    public function __construct()
    {
        $this->etatBiens = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
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

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getDureeVie(): ?int
    {
        return $this->dureeVie;
    }

    public function setDureeVie(?int $dureeVie): static
    {
        $this->dureeVie = $dureeVie;

        return $this;
    }

    public function getTaux(): ?string
    {
        return $this->taux;
    }

    public function setTaux(?string $taux): static
    {
        $this->taux = $taux;

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

    public function getCategoryId(): ?int
    {
        return null === $this->category ? null : $this->category->getId();
    }

    #[SerializedName('category_id')]
    #[Groups(['asset_type:list', 'asset_type:detail'])]
    public function getCategoryIdSerialized(): ?int
    {
        return $this->getCategoryId();
    }

    /**
     * @return Collection<int, EtatBien>
     */
    public function getEtatBiens(): Collection
    {
        return $this->etatBiens;
    }

    public function addEtatBien(EtatBien $etatBien): static
    {
        if (!$this->etatBiens->contains($etatBien)) {
            $this->etatBiens->add($etatBien);
            $etatBien->addAssetType($this);
        }

        return $this;
    }

    public function removeEtatBien(EtatBien $etatBien): static
    {
        if ($this->etatBiens->removeElement($etatBien)) {
            $etatBien->removeAssetType($this);
        }

        return $this;
    }
}