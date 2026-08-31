<?php

// src/Entity/Groupe.php

namespace App\Entity;

use App\Repository\GroupeRepository;
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
 * Un Groupe définit un socle commun de permissions hérité par tous ses membres
 * (ex. SECRETAIRE, AGENT, CHEF_SERVICE). Structurellement identique à Role, mais
 * distinct dans son usage métier : Role = fonction/métier unique (max 2 par User),
 * Groupe = appartenance organisationnelle (illimitée par User), les deux se cumulent
 * dans User::getEffectivePermissions().
 *
 * Table nommée "groupe" (et non "group") car GROUP est un mot réservé SQL.
 */
#[ORM\Entity(repositoryClass: GroupeRepository::class)]
#[ORM\Table(name: 'groupe')]
#[UniqueEntity(fields: ['nom'], message: "Ce nom de groupe est déjà utilisé.")]
class Groupe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['groupe:list', 'groupe:detail', 'user:read', 'user:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: "Le nom du groupe est obligatoire.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le nom du groupe ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['groupe:list', 'groupe:detail', 'user:read', 'user:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['groupe:list', 'groupe:detail', 'user:read', 'user:detail'])]
    private ?string $description = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    #[SerializedName('is_active')]
    #[Groups(['groupe:list', 'groupe:detail', 'user:read', 'user:detail'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de création est obligatoire.")]
    #[Groups(['groupe:detail'])]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'd-m-Y H:i:s'],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTime::RFC3339],
    )]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de mise à jour est obligatoire.")]
    #[Groups(['groupe:detail'])]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'd-m-Y H:i:s'],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTime::RFC3339],
    )]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    private bool $isDelete = false;

    /**
     * Côté propriétaire de la relation ManyToMany Groupe <-> Permission (table groupe_permission).
     * Même pattern que Role::permissions.
     *
     * @var Collection<int, Permission>
     */
    #[ORM\ManyToMany(targetEntity: Permission::class)]
    #[ORM\JoinTable(name: 'groupe_permission')]
    #[Groups(['groupe:detail'])]
    private Collection $permissions;

    /**
     * Côté inverse de la relation ManyToMany User <-> Groupe.
     * Le côté propriétaire (table user_groupe) est porté par User, comme pour assignedRoles.
     *
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'assignedGroupes')]
    private Collection $users;

    public function __construct()
    {
        $this->permissions = new ArrayCollection();
        $this->users = new ArrayCollection();
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
     * @return Collection<int, Permission>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->permissions->contains($permission);
    }

    public function addPermission(Permission $permission): static
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
        }

        return $this;
    }

    public function removePermission(Permission $permission): static
    {
        $this->permissions->removeElement($permission);

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }
}