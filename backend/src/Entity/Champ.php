<?php

// src/Entity/Champ.php

namespace App\Entity;

use App\Repository\ChampRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champ personnalisé pouvant être associé à une ou plusieurs catégories de biens.
 */
#[ORM\Entity(repositoryClass: ChampRepository::class)]
#[ORM\Table(name: 'champ')]
#[UniqueEntity(fields: ['nom'], message: 'Ce nom de champ est déjà utilisé.')]
class Champ
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['champ:list', 'champ:detail', 'category:detail'])]
    private ?int $id = null;

    /**
     * ✅ Relation ManyToMany avec Asset
     * @var Collection<int, Asset>
     */
    #[ORM\ManyToMany(targetEntity: Asset::class, mappedBy: 'champs')]
    #[Groups(['champ:detail'])]
    private Collection $assets;

    #[ORM\Column(length: 191, unique: true)]
    #[Assert\NotBlank(message: 'Le nom du champ est obligatoire.')]
    #[Assert\Length(
        max: 191,
        maxMessage: 'Le nom du champ ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['champ:list', 'champ:detail', 'category:detail'])]
    private ?string $nom = null;

    // ❌ SUPPRIMEZ CES LIGNES (typeChamp)
    // #[ORM\Column(name: 'option', length: 100)]
    // private ?string $typeChamp = null;

    // ❌ SUPPRIMEZ CES LIGNES (valeur)
    // #[ORM\Column(length: 255, nullable: true)]
    // private ?string $valeur = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de création est obligatoire.')]
    #[Groups(['champ:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de mise à jour est obligatoire.')]
    #[Groups(['champ:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['champ:list', 'champ:detail'])]
    private bool $isDelete = false;


    /**
     * ✅ Nouveau champ : Type du champ
     * Exemples: text, number, select, checkbox, radio, date, etc.
     */
    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: 'Le type du champ est obligatoire.')]
    #[Assert\Length(
        max: 50,
        maxMessage: 'Le type du champ ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['champ:list', 'champ:detail', 'category:detail'])]
    private ?string $type = null;

    /**
     * ✅ Nouveau champ : Sous-type du champ
     * Exemples: pour type=select -> single, multiple; pour type=text -> email, phone, url, etc.
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(
        max: 50,
        maxMessage: 'Le sous-type du champ ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['champ:list', 'champ:detail', 'category:detail'])]
    private ?string $subtype = null;

    #[ORM\Column(name: 'champ_option', length: 50, nullable: true)]
    #[Assert\Length(
        max: 50,
        maxMessage: 'L\'option du champ ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['champ:list', 'champ:detail', 'category:detail'])]
    #[SerializedName('option')]
    private ?string $option = null;

    
    /**
     * ✅ Nouveau champ : Numéro d'ordre
     * Définit l'ordre d'affichage des champs
     */
    #[ORM\Column(name: 'ordre', type: 'integer', nullable: true)]
    #[Groups(['champ:list', 'champ:detail', 'category:detail'])]
    private ?int $ordre = null;

    /**
     * ✅ Relation OneToMany avec Input (UNE SEULE FOIS)
     * @var Collection<int, Input>
     */
    #[ORM\OneToMany(mappedBy: 'champ', targetEntity: Input::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['valeur' => 'ASC'])]
    #[Groups(['champ:detail'])]
    private Collection $inputs;

    // /**
    //  * @var Collection<int, Category>
    //  */
    // #[ORM\ManyToMany(targetEntity: Category::class, mappedBy: 'champs')]
    // private Collection $categories;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'champs')]
    #[ORM\JoinTable(name: 'category_champ')]
    #[ORM\JoinColumn(name: 'champ_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['champ:detail', 'champ:list'])]
    #[MaxDepth(1)]  // Évite les boucles infinies
    private Collection $categories;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->assets = new ArrayCollection();
        $this->inputs = new ArrayCollection(); // ✅ Une seule fois
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

      /**
     * Get the order number
     */
    public function getOrdre(): int
    {
        return $this->ordre;
    }

    /**
     * Set the order number
     */
    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        return $this;
    }
    

    // ❌ SUPPRIMEZ CES MÉTHODES (typeChamp)
    // public function getTypeChamp(): ?string
    // {
    //     return $this->typeChamp;
    // }
    // public function setTypeChamp(?string $typeChamp): static
    // {
    //     $this->typeChamp = $typeChamp;
    //     return $this;
    // }

    // ❌ SUPPRIMEZ CES MÉTHODES (valeur)
    // public function getValeur(): ?string
    // {
    //     return $this->valeur;
    // }
    // public function setValeur(?string $valeur): static
    // {
    //     $this->valeur = $valeur;
    //     return $this;
    // }

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


    /**
     * Get the type of the field
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Set the type of the field
     */
    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get the subtype of the field
     */
    public function getSubtype(): ?string
    {
        return $this->subtype;
    }
    
    /**
     * Get the option of the field
     */
    public function getOption(): ?string
    {
        return $this->option;
    }

    /**
     * Set the option of the field
     */
    public function setOption(?string $option): static
    {
        $this->option = $option;
        return $this;
    }

    /**
     * Set the subtype of the field
     */
    public function setSubtype(?string $subtype): static
    {
        $this->subtype = $subtype;
        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->addChamp($this);
        }
        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if ($this->categories->removeElement($category)) {
            $category->removeChamp($this);
        }
        return $this;
    }

    public function clearCategories(): static
    {
        foreach ($this->categories as $category) {
            $category->removeChamp($this);
        }
        $this->categories->clear();
        return $this;
    }

    /**
     * ✅ Getters et setters pour la relation avec Input
     * @return Collection<int, Input>
     */
    public function getInputs(): Collection
    {
        return $this->inputs;
    }

    public function addInput(Input $input): static
    {
        if (!$this->inputs->contains($input)) {
            $this->inputs->add($input);
            $input->setChamp($this);
        }
        return $this;
    }

    public function removeInput(Input $input): static
    {
        if ($this->inputs->removeElement($input)) {
            if ($input->getChamp() === $this) {
                $input->setChamp(null);
            }
        }
        return $this;
    }

    /**
     * ✅ Getters et setters pour la relation avec Asset
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
            $asset->addChamp($this);
        }
        return $this;
    }

    public function removeAsset(Asset $asset): static
    {
        if ($this->assets->removeElement($asset)) {
            $asset->removeChamp($this);
        }
        return $this;
    }
}