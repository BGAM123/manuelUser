<?php

namespace App\Entity\Core;

use App\Entity\Cour\Reponse;
use App\Repository\Core\TypeReponseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TypeReponseRepository::class)]
#[ORM\Table(name: 'core_type_reponse')]
#[ORM\HasLifecycleCallbacks]
class TypeReponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Groups(['GetCollection:TypeReponse', 'Get:TypeReponse', 'GetCollection:Reponse', 'Get:Reponse'])]
    private ?int $id = null;

    #[ORM\Column(name: 'nom', type: Types::STRING, length: 255, nullable: false)]
    #[Groups(['GetCollection:TypeReponse', 'Get:TypeReponse', 'Post:TypeReponse', 'GetCollection:Reponse', 'Get:Reponse'])]
    private ?string $nom = null;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    #[Groups(['Get:TypeReponse', 'Post:TypeReponse'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'sousTypes')]
    #[ORM\JoinColumn(name: 'id_type_parent', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    #[Groups(['Get:TypeReponse'])]
    private ?self $typeParent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'typeParent')]
    private Collection $sousTypes;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['GetCollection:TypeReponse', 'Get:TypeReponse', 'Post:TypeReponse'])]
    private bool $isActive = true;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['Get:TypeReponse'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['Get:TypeReponse'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'typeReponse', targetEntity: Reponse::class)]
    private Collection $reponses;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
        $this->sousTypes = new ArrayCollection();
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

    public function setNom(string $nom): static
    {
        $this->nom = trim($nom);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = empty($description) ? null : trim($description);
        return $this;
    }

    public function getTypeParent(): ?self
    {
        return $this->typeParent;
    }

    public function setTypeParent(?self $typeParent): static
    {
        $this->typeParent = $typeParent;
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getSousTypes(): Collection
    {
        return $this->sousTypes;
    }

    public function addSousType(self $sousType): static
    {
        if (!$this->sousTypes->contains($sousType)) {
            $this->sousTypes->add($sousType);
            $sousType->setTypeParent($this);
        }
        return $this;
    }

    public function removeSousType(self $sousType): static
    {
        if ($this->sousTypes->removeElement($sousType) && $sousType->getTypeParent() === $this) {
            $sousType->setTypeParent(null);
        }
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    /**
     * @return Collection<int, Reponse>
     */
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function addReponse(Reponse $reponse): static
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses->add($reponse);
            $reponse->setTypeReponse($this);
        }

        return $this;
    }

    public function removeReponse(Reponse $reponse): static
    {
        if ($this->reponses->removeElement($reponse)) {
            // set the owning side to null (unless already changed)
            if ($reponse->getTypeReponse() === $this) {
                $reponse->setTypeReponse(null);
            }
        }

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

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
