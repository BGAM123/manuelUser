<?php

// src/Entity/Permission.php

namespace App\Entity;

use App\Repository\PermissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une Permission représente une action technique unitaire du système
 * (ex. "gerer_biens", "voir_rapports"). Elle est ensuite regroupée dans
 * un ou plusieurs Rôles pour former les habilitations réelles données aux utilisateurs.
 *
 * Le nom est unique afin d'éviter que deux permissions différentes se recouvrent
 * dans le code (les contrôles de droits se basent sur ce nom).
 */
#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[UniqueEntity(fields: ['nom'], message: "Ce nom de permission est déjà utilisé.")]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['permission:list', 'permission:detail', 'role:detail', 'groupe:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: "Le nom de la permission est obligatoire.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le nom de la permission ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['permission:list', 'permission:detail', 'role:detail', 'groupe:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['permission:list', 'permission:detail', 'role:detail', 'groupe:detail'])]
    private ?string $description = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    #[SerializedName('is_active')]
    #[Groups(['permission:list', 'permission:detail', 'role:detail', 'groupe:detail'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de création est obligatoire.")]
    #[Groups(['permission:detail'])]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'd-m-Y H:i:s'],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTime::RFC3339],
    )]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de mise à jour est obligatoire.")]
    #[Groups(['permission:detail'])]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'd-m-Y H:i:s'],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTime::RFC3339],
    )]
    private ?\DateTimeImmutable $updatedAt = null;

    // Suppression logique : une permission utilisée historiquement par un rôle ne doit
    // jamais être supprimée physiquement sans précaution, sous peine de casser les
    // habilitations déjà accordées. Le champ is_delete permet de la "masquer" sans la détruire.
    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    private bool $isDelete = false;

    /**
     * Côté inverse de la relation ManyToMany Role <-> Permission.
     * Le côté propriétaire (table de jointure role_permission) est porté par Role.
     *
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class, mappedBy: 'permissions')]
    private Collection $roles;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }
}
