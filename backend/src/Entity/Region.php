<?php

namespace App\Entity;

use App\Repository\RegionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RegionRepository::class)]
#[UniqueEntity(fields: ['nom'], message: 'Ce nom de région est déjà utilisé.')]
class Region
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['region:list', 'region:detail', 'departement:list', 'departement:detail', 'arrondissement:list', 'arrondissement:detail', 'service:list', 'service:detail'])]
    private ?int $id = null;

    /**
     * V1: l'unicité SQL globale (unique=true) reste alignée sur le pattern Category/AssetType.
     * Conséquence connue: une ligne soft-delete peut bloquer la recréation d'un même nom.
     */
    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Le nom de la région est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom de la région ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['region:list', 'region:detail', 'departement:list', 'departement:detail', 'arrondissement:list', 'arrondissement:detail', 'service:list', 'service:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Le code de la région ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['region:list', 'region:detail'])]
    private ?string $code = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de création est obligatoire.')]
    #[Groups(['region:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de mise à jour est obligatoire.')]
    #[Groups(['region:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['region:list', 'region:detail'])]
    private bool $isDelete = false;

    /**
     * @var Collection<int, Departement>
     */
    #[ORM\OneToMany(targetEntity: Departement::class, mappedBy: 'region')]
    #[Groups(['region:detail'])]
    private Collection $departements;

    public function __construct()
    {
        $this->departements = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

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

    /**
     * @return Collection<int, Departement>
     */
    public function getDepartements(): Collection
    {
        return $this->departements;
    }

    public function addDepartement(Departement $departement): static
    {
        if (!$this->departements->contains($departement)) {
            $this->departements->add($departement);
            $departement->setRegion($this);
        }

        return $this;
    }

    public function removeDepartement(Departement $departement): static
    {
        if ($this->departements->removeElement($departement)) {
            if ($departement->getRegion() === $this) {
                $departement->setRegion(null);
            }
        }

        return $this;
    }
}
