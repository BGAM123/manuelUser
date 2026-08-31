<?php

namespace App\Entity;

use App\Repository\ArrondissementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArrondissementRepository::class)]
#[UniqueEntity(fields: ['nom', 'departement'], message: 'Ce nom d\'arrondissement est déjà utilisé dans ce département.')]
class Arrondissement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['arrondissement:list', 'arrondissement:detail', 'departement:detail', 'service:list', 'service:detail'])]
    private ?int $id = null;

    /**
     * V1: unicité SQL composite (nom, departement_id) conservée.
     * Conséquence connue: un arrondissement soft-delete peut bloquer
     * une recréation du même nom dans le même département.
     */
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom de l\'arrondissement est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom de l\'arrondissement ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['arrondissement:list', 'arrondissement:detail', 'departement:detail', 'service:list', 'service:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Le code de l\'arrondissement ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['arrondissement:list', 'arrondissement:detail'])]
    private ?string $code = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de création est obligatoire.')]
    #[Groups(['arrondissement:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de mise à jour est obligatoire.')]
    #[Groups(['arrondissement:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['arrondissement:list', 'arrondissement:detail'])]
    private bool $isDelete = false;

    #[ORM\ManyToOne(targetEntity: Departement::class, inversedBy: 'arrondissements')]
    #[ORM\JoinColumn(name: 'departement_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Le département est obligatoire.')]
    #[Groups(['arrondissement:list', 'arrondissement:detail'])]
    private ?Departement $departement = null;

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

    public function getDepartement(): ?Departement
    {
        return $this->departement;
    }

    public function setDepartement(?Departement $departement): static
    {
        $this->departement = $departement;

        return $this;
    }

    public function getDepartementId(): ?int
    {
        return null === $this->departement ? null : $this->departement->getId();
    }

    #[SerializedName('departement_id')]
    #[Groups(['arrondissement:list', 'arrondissement:detail'])]
    public function getDepartementIdSerialized(): ?int
    {
        return $this->getDepartementId();
    }
}
