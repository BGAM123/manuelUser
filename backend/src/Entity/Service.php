<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[UniqueEntity(fields: ['nom'], message: 'Un service portant ce nom existe déjà.')]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["user:read", "user:detail", "service:list", "service:detail"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["user:read", "user:detail", "service:list", "service:detail"])]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["user:read", "user:detail", "service:list", "service:detail"])]
    private ?string $sigle = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["user:read", "user:detail", "service:list", "service:detail"])]
    private ?string $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["user:read", "user:detail", "service:list", "service:detail"])]
    private ?string $type_service = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(["user:read", "user:detail", "service:list", "service:detail"])]
    private ?int $ordre = null;

    #[ORM\Column]
    #[Groups(["user:read", "user:detail", "service:list", "service:detail"])]
    private ?bool $is_active = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[Groups(["service:list", "service:detail"])]
    private Collection $children;

    /**
     * Relation ManyToMany avec TypeOrganigramme
     * @var Collection<int, TypeOrganigramme>
     */
    #[ORM\ManyToMany(targetEntity: TypeOrganigramme::class, mappedBy: 'services', cascade: ['persist'])]
    private Collection $typeOrganigrammes;

    #[ORM\ManyToOne(targetEntity: Region::class)]
    #[ORM\JoinColumn(name: 'region_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['service:list', 'service:detail'])]
    private ?Region $region = null;

    #[ORM\ManyToOne(targetEntity: Departement::class)]
    #[ORM\JoinColumn(name: 'departement_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['service:list', 'service:detail'])]
    private ?Departement $departement = null;

    #[ORM\ManyToOne(targetEntity: Arrondissement::class)]
    #[ORM\JoinColumn(name: 'arrondissement_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['service:list', 'service:detail'])]
    private ?Arrondissement $arrondissement = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->typeOrganigrammes = new ArrayCollection();
        $this->assignments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    // Ajoutez ces méthodes dans votre entité Service

// Ajoutez ces méthodes dans votre entité Service

public function getCode(): ?string
{
    return $this->code;
}

public function setCode(?string $code): static
{
    $this->code = $code;
    return $this;
}

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }



    public function getSigle(): ?string
    {
        return $this->sigle;
    }

    public function setSigle(?string $sigle): static
    {
        $this->sigle = $sigle;

        return $this;
    }

    public function getTypeService(): ?string
    {
        return $this->type_service;
    }

    public function setTypeService(?string $type_service): static
    {
        $this->type_service = $type_service;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->is_active;
    }

    public function setIsActive(bool $is_active): static
    {
        $this->is_active = $is_active;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getParentId(): ?int
    {
        return null === $this->parent ? null : $this->parent->getId();
    }

    #[SerializedName('parent_id')]
    #[Groups(["user:read", "user:detail", "service:list", "service:detail"])]
    public function getParentIdSerialized(): ?int
    {
        return $this->getParentId();
    }

    public function getChildren(): Collection
    {
        $children = $this->children->toArray();
        usort($children, function (?self $a, ?self $b) {
            $aOrd = $a?->getOrdre() ?? 0;
            $bOrd = $b?->getOrdre() ?? 0;
            return $aOrd <=> $bOrd;
        });

        return new ArrayCollection($children);
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TypeOrganigramme>
     */
    public function getTypeOrganigrammes(): Collection
    {
        return $this->typeOrganigrammes;
    }

    public function addTypeOrganigramme(TypeOrganigramme $typeOrganigramme): static
    {
        if (!$this->typeOrganigrammes->contains($typeOrganigramme)) {
            $this->typeOrganigrammes->add($typeOrganigramme);
            $typeOrganigramme->addService($this); // ✅ Ajouter cette ligne
        }
        return $this;
    }

    public function removeTypeOrganigramme(TypeOrganigramme $typeOrganigramme): static
    {
        $this->typeOrganigrammes->removeElement($typeOrganigramme);
        $typeOrganigramme->removeService($this); // ✅ Important !
        return $this;
    }

    /**
     * Synchronise les types d'organigrammes avec une liste d'IDs
     * @param array<int, int> $typeOrganigrammeIds
     */
    public function syncTypeOrganigrammes(array $typeOrganigrammeIds): static
    {
        // Supprimer les associations qui ne sont plus présentes
        foreach ($this->typeOrganigrammes as $type) {
            if (!in_array($type->getId(), $typeOrganigrammeIds, true)) {
                $this->removeTypeOrganigramme($type);
            }
        }
        return $this;
    }

    public function getRegion(): ?Region
    {
        return $this->region;
    }

    public function setRegion(?Region $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getDepartement(): ?Departement
    {
        return $this->departement;
    }

    public function setDepartement(?Departement $departement): static
    {
        $this->departement = $departement;

        return $this;
    }

    public function getArrondissement(): ?Arrondissement
    {
        return $this->arrondissement;
    }

    public function setArrondissement(?Arrondissement $arrondissement): static
    {
        $this->arrondissement = $arrondissement;

        return $this;
    }

    /**
     * @var Collection<int, AssetAssignment>
     */
    #[ORM\OneToMany(mappedBy: 'service', targetEntity: AssetAssignment::class)]
    private Collection $assignments;

    /**
     * @return Collection<int, AssetAssignment>
     */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function addAssignment(AssetAssignment $assignment): static
    {
        if (!$this->assignments->contains($assignment)) {
            $this->assignments->add($assignment);
            $assignment->setService($this);
        }

        return $this;
    }

    public function removeAssignment(AssetAssignment $assignment): static
    {
        if ($this->assignments->removeElement($assignment)) {
            if ($assignment->getService() === $this) {
                $assignment->setService(null);
            }
        }

        return $this;
    }
}
