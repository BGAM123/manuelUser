<?php

namespace App\Entity;

use App\Repository\DepartementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DepartementRepository::class)]
#[UniqueEntity(fields: ['nom', 'region'], message: 'Ce nom de département est déjà utilisé dans cette région.')]
class Departement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['departement:list', 'departement:detail', 'region:detail', 'arrondissement:list', 'arrondissement:detail', 'service:list', 'service:detail'])]
    private ?int $id = null;

    /**
     * V1: unicité SQL composite (nom, region_id) conservée.
     * Conséquence connue: un département soft-delete peut bloquer une recréation
     * du même nom dans la même région.
     */
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom du département est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom du département ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['departement:list', 'departement:detail', 'region:detail', 'arrondissement:list', 'arrondissement:detail', 'service:list', 'service:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Le code du département ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['departement:list', 'departement:detail'])]
    private ?string $code = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de création est obligatoire.')]
    #[Groups(['departement:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de mise à jour est obligatoire.')]
    #[Groups(['departement:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['departement:list', 'departement:detail'])]
    private bool $isDelete = false;

    #[ORM\ManyToOne(targetEntity: Region::class, inversedBy: 'departements')]
    #[ORM\JoinColumn(name: 'region_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'La région est obligatoire.')]
    #[Groups(['departement:list', 'departement:detail', 'arrondissement:list', 'arrondissement:detail'])]
    private ?Region $region = null;

    /**
     * @var Collection<int, Arrondissement>
     */
    #[ORM\OneToMany(targetEntity: Arrondissement::class, mappedBy: 'departement')]
    #[Groups(['departement:detail'])]
    private Collection $arrondissements;

    public function __construct()
    {
        $this->arrondissements = new ArrayCollection();
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

    public function getRegion(): ?Region
    {
        return $this->region;
    }

    public function setRegion(?Region $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getRegionId(): ?int
    {
        return null === $this->region ? null : $this->region->getId();
    }

    #[SerializedName('region_id')]
    #[Groups(['departement:list', 'departement:detail'])]
    public function getRegionIdSerialized(): ?int
    {
        return $this->getRegionId();
    }

    /**
     * @return Collection<int, Arrondissement>
     */
    public function getArrondissements(): Collection
    {
        return $this->arrondissements;
    }

    public function addArrondissement(Arrondissement $arrondissement): static
    {
        if (!$this->arrondissements->contains($arrondissement)) {
            $this->arrondissements->add($arrondissement);
            $arrondissement->setDepartement($this);
        }

        return $this;
    }

    public function removeArrondissement(Arrondissement $arrondissement): static
    {
        if ($this->arrondissements->removeElement($arrondissement)) {
            if ($arrondissement->getDepartement() === $this) {
                $arrondissement->setDepartement(null);
            }
        }

        return $this;
    }
}
