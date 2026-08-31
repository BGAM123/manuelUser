<?php

// src/Entity/Category.php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
// use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catégorie de biens (ex. Véhicules, Bâtiments, Matériel informatique...).
 *
 * Une catégorie est le PARENT dans la relation parent-enfant avec AssetType :
 * une Category regroupe plusieurs AssetType (ex. "Véhicules" -> "Camion", "Véhicule léger").
 */
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
// #[UniqueEntity(fields: ['nom'], message: "Ce nom de catégorie est déjà utilisé.")]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['category:list', 'category:detail', 'asset_type:list', 'asset_type:detail', 'champ:detail', 'etat_bien:list', 'etat_bien:detail', 'champ:list'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom de la catégorie est obligatoire.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le nom de la catégorie ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['category:list', 'category:detail', 'asset_type:list', 'asset_type:detail', 'champ:detail', 'etat_bien:list', 'etat_bien:detail', 'champ:list', 'asset:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['category:list', 'category:detail'])]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['category:list', 'category:detail'])]
    private ?int $seuil = null;

     #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "consomtible es true ou false.")]
    #[Groups(['category:list', 'category:detail'])]
    private ?bool $consommable = false;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de création est obligatoire.")]
    #[Groups(['category:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de mise à jour est obligatoire.")]
    #[Groups(['category:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['category:list', 'category:detail'])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'is_default', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_default')]
    #[Groups(['category:list', 'category:detail', 'asset_type:list', 'asset_type:detail'])]
    private bool $isDefault = false;

    /**
     * Types de biens rattachés à cette catégorie (relation parent-enfant).
     *
     * Volontairement exposée UNIQUEMENT dans category:detail (pas category:list) :
     * charger les enfants de chaque catégorie dans une liste paginée serait coûteux
     * et inutile pour un tableau de liste. Pour la vue détail, en revanche, avoir
     * directement les types de biens rattachés évite un aller-retour supplémentaire
     * côté frontend (cf. GET /categories/{id}).
     *
     * @var Collection<int, AssetType>
     */
    #[ORM\OneToMany(targetEntity: AssetType::class, mappedBy: 'category')]
    #[Groups(['category:detail'])]
    private Collection $assetTypes;

    /**
     * Champs personnalisés associés à cette catégorie (ManyToMany propriétaire).
     * Exposée uniquement en détail pour éviter le coût sur les listes paginées.
     *
     * @var Collection<int, Champ>
     */
    #[ORM\ManyToMany(targetEntity: Champ::class, inversedBy: 'categories')]
    #[ORM\JoinTable(name: 'category_champ')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'champ_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['category:detail'])]
    private Collection $champs;

    public function __construct()
    {
        $this->assetTypes = new ArrayCollection();
        $this->champs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getConsommable(): ?bool
    {
        return $this->consommable;
    }

    public function setConsommable(?bool $consommable): static
    {
        $this->consommable = $consommable;

        return $this;
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

    public function getSeuil(): ?int
    {
        return $this->seuil;
    }

    public function setSeuil(?int $seuil): static
    {
        $this->seuil = $seuil;

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

    /**
     * @return Collection<int, AssetType>
     */
    public function getAssetTypes(): Collection
    {
        return $this->assetTypes;
    }

    public function addAssetType(AssetType $assetType): static
    {
        if (!$this->assetTypes->contains($assetType)) {
            $this->assetTypes->add($assetType);
            $assetType->setCategory($this);
        }

        return $this;
    }

    public function removeAssetType(AssetType $assetType): static
    {
        if ($this->assetTypes->removeElement($assetType)) {
            if ($assetType->getCategory() === $this) {
                $assetType->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Champ>
     */
    public function getChamps(): Collection
    {
        return $this->champs;
    }

    public function addChamp(Champ $champ): static
    {
        if (!$this->champs->contains($champ)) {
            $this->champs->add($champ);
        }

        return $this;
    }

    public function removeChamp(Champ $champ): static
    {
        $this->champs->removeElement($champ);

        return $this;
    }

    /**
     * Remplace la collection de champs associés par la liste fournie.
     *
     * @param Champ[] $champs
     */
    public function syncChamps(array $champs): static
    {
        foreach ($this->champs->toArray() as $existing) {
            if (!in_array($existing, $champs, true)) {
                $this->removeChamp($existing);
            }
        }

        foreach ($champs as $champ) {
            $this->addChamp($champ);
        }

        return $this;
    }
}