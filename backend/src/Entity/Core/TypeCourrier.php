<?php

namespace App\Entity\Core;

use App\Entity\Cour\Courrier;
use App\Repository\Core\TypeCourrierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TypeCourrierRepository::class)]
#[ORM\Table(name: 'core_type_courrier')]
#[ORM\UniqueConstraint(name: 'UNIQ_TYPE_COURRIER_NOM', columns: ['nom'])]
#[ORM\HasLifecycleCallbacks]
class TypeCourrier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, nullable: false)]
    #[Groups(['GetCollection:TypeCourrier', 'Get:TypeCourrier'])]
    private ?int $id = null;

    #[ORM\Column(name: 'nom', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:TypeCourrier', 'Get:TypeCourrier'])]
    private ?string $nom = null;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:TypeCourrier', 'Get:TypeCourrier'])]
    private ?string $type = null;

    #[ORM\Column(name: 'classe_courrier', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:TypeCourrier', 'Get:TypeCourrier'])]
    private ?string $classeCourrier = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'typeEnfants')]
    #[ORM\JoinColumn(name: 'id_type_parent', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    #[Groups(['Get:TypeCourrier'])]
    private ?self $idTypeParent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'idTypeParent')]
    private Collection $typeEnfants;

    /**
     * @var Collection<int, CategorieCorrespondant>
     */
    #[ORM\ManyToMany(targetEntity: CategorieCorrespondant::class, inversedBy: 'typeCourriers')]
    #[ORM\JoinTable(name: 'core_type_courrier_categorie')]
    #[Groups(['GetCollection:TypeCourrier', 'Get:TypeCourrier'])]
    private Collection $categories;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:TypeCourrier', 'Get:TypeCourrier'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:TypeCourrier', 'Get:TypeCourrier'])]
    private ?\DateTimeImmutable $updatedAt = null;

    
    #[ORM\Column(
        name: 'search',
        type: Types::TEXT,
        length: 1000,
        nullable: true
    )]
    private ?string $search = null;

    /**
     * @var Collection<int, Courrier>
     */
    #[ORM\OneToMany(targetEntity: Courrier::class, mappedBy: 'typeCourrier')]
    private Collection $courriers;

    public function __construct()
    {
        $this->courriers = new ArrayCollection();
        $this->typeEnfants = new ArrayCollection();
        $this->categories = new ArrayCollection();
    }

    // ──────────────── GETTERS / SETTERS ────────────────

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
        $this->nom = trim($nom ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = trim($type ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getClasseCourrier(): ?string
    {
        return $this->classeCourrier;
    }

    public function setClasseCourrier(?string $classeCourrier): static
    {
        $this->classeCourrier = trim($classeCourrier ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getIdTypeParent(): ?self
    {
        return $this->idTypeParent;
    }

    public function setIdTypeParent(?self $idTypeParent): static
    {
        $this->idTypeParent = $idTypeParent;
        $this->updateSearchField();
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getTypeEnfants(): Collection
    {
        return $this->typeEnfants;
    }

    public function addTypeEnfant(self $child): static
    {
        if (!$this->typeEnfants->contains($child)) {
            $this->typeEnfants->add($child);
            $child->setIdTypeParent($this);
        }
        return $this;
    }

    public function removeTypeEnfant(self $child): static
    {
        if ($this->typeEnfants->removeElement($child) && $child->getIdTypeParent() === $this) {
            $child->setIdTypeParent(null);
        }
        return $this;
    }

    /**
     * @return Collection<int, CategorieCorrespondant>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategorie(CategorieCorrespondant $categorie): static
    {
        if (!$this->categories->contains($categorie)) {
            $this->categories->add($categorie);
        }
        return $this;
    }

    public function removeCategorie(CategorieCorrespondant $categorie): static
    {
        $this->categories->removeElement($categorie);
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

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateSearchField(): void
    {
        $categoriesNoms = $this->categories->map(fn($cat) => $cat->getNom())->toArray();
        
        $this->search = implode(' <br/> ', array_filter([
            $this->nom,
            $this->type,
            $this->classeCourrier,
            implode(', ', $categoriesNoms),
            $this->idTypeParent?->getNom(),
        ]));
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function setSearch(?string $search): static
    {
        $this->search = $search;
        return $this;
    }

    /**
     * @return Collection<int, Courrier>
     */
    public function getCourriers(): Collection
    {
        return $this->courriers;
    }

    public function addCourrier(Courrier $courrier): static
    {
        if (!$this->courriers->contains($courrier)) {
            $this->courriers->add($courrier);
            $courrier->setTypeCourrier($this);
        }

        return $this;
    }

    public function removeCourrier(Courrier $courrier): static
    {
        if ($this->courriers->removeElement($courrier)) {
            // set the owning side to null (unless already changed)
            if ($courrier->getTypeCourrier() === $this) {
                $courrier->setTypeCourrier(null);
            }
        }

        return $this;
    }
}
