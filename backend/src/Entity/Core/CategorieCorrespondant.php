<?php

namespace App\Entity\Core;

use App\Repository\Core\CategorieCorrespondantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CategorieCorrespondantRepository::class)]
#[ORM\Table(name: 'core_categorie_correspondant')]
#[ORM\HasLifecycleCallbacks]
class CategorieCorrespondant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        name: 'id',
        type: Types::INTEGER,
        nullable: false
    )]
    #[Groups(['GetCollection:CategorieCorrespondant', 'Get:CategorieCorrespondant', 'Get:TypeCourrier', 'GetCollection:TypeCourrier'])]
    private ?int $id = null;

    #[ORM\Column(
        name: 'nom',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    #[Groups(['GetCollection:CategorieCorrespondant', 'Get:CategorieCorrespondant', 'Get:TypeCourrier', 'GetCollection:TypeCourrier'])]
    private ?string $nom = null;

    #[ORM\Column(
        name: 'is_delete',
        type: Types::BOOLEAN,
        nullable: false,
        options: ['default' => false]
    )]
    #[Groups(['GetCollection:CategorieCorrespondant', 'Get:CategorieCorrespondant'])]
    private bool $isDelete = false;

    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        nullable: false,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    #[Groups(['GetCollection:CategorieCorrespondant', 'Get:CategorieCorrespondant'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        nullable: false,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    #[Groups(['GetCollection:CategorieCorrespondant', 'Get:CategorieCorrespondant'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(
        name: 'search',
        type: Types::TEXT,
        length: 1000,
        nullable: true
    )]
    private ?string $search = null;

    /** @var Collection<int, Correspondant> */
    #[ORM\ManyToMany(targetEntity: Correspondant::class, mappedBy: 'categories')]
    private Collection $correspondants;

    /** @var Collection<int, TypeCourrier> */
    #[ORM\ManyToMany(targetEntity: TypeCourrier::class, mappedBy: 'categories')]
    private Collection $typeCourriers;

    public function __construct()
    {
        $this->correspondants = new ArrayCollection();
        $this->typeCourriers = new ArrayCollection();
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
    public function updateTimestampsAndSearch(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        $this->updatedAt = new \DateTimeImmutable();
        $this->search = $this->nom ?? '';
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

    /** @return Collection<int, Correspondant> */
    public function getCorrespondants(): Collection
    {
        return $this->correspondants;
    }

    public function addCorrespondant(Correspondant $correspondant): static
    {
        if (!$this->correspondants->contains($correspondant)) {
            $this->correspondants->add($correspondant);
            $correspondant->addCategorie($this);
        }
        return $this;
    }

    public function removeCorrespondant(Correspondant $correspondant): static
    {
        if ($this->correspondants->removeElement($correspondant)) {
            $correspondant->removeCategorie($this);
        }
        return $this;
    }

    /** @return Collection<int, TypeCourrier> */
    public function getTypeCourriers(): Collection
    {
        return $this->typeCourriers;
    }

    public function addTypeCourrier(TypeCourrier $typeCourrier): static
    {
        if (!$this->typeCourriers->contains($typeCourrier)) {
            $this->typeCourriers->add($typeCourrier);
            $typeCourrier->addCategorie($this);
        }
        return $this;
    }

    public function removeTypeCourrier(TypeCourrier $typeCourrier): static
    {
        if ($this->typeCourriers->removeElement($typeCourrier)) {
            $typeCourrier->removeCategorie($this);
        }
        return $this;
    }
}
