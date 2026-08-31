<?php

// src/Entity/Role.php

namespace App\Entity;

use App\Repository\RoleRepository;
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
 * Un Rôle regroupe un ensemble de Permissions et représente un "métier"
 * (ex. Administrateur, Gestionnaire de Biens...). C'est ce que l'on attribue
 * ensuite à un Utilisateur (au maximum 2 rôles par utilisateur, voir User::addAssignedRole()).
 *
 * Role est le côté propriétaire de la relation ManyToMany avec Permission : c'est donc lui
 * qui porte la table de jointure `role_permission`. C'est un choix arbitraire mais cohérent
 * car un rôle "possède" ses permissions au sens métier (on affecte des permissions à un rôle,
 * pas l'inverse).
 */
#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[UniqueEntity(fields: ['nom'], message: "Ce nom de rôle est déjà utilisé.")]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['role:list', 'role:detail', 'user:read', 'user:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: "Le nom du rôle est obligatoire.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le nom du rôle ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['role:list', 'role:detail', 'user:read', 'user:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['role:list', 'role:detail', 'user:read', 'user:detail'])]
    private ?string $description = null;

    // Reflète le select "Actif / Inactif" du formulaire d'administration des rôles.
    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    #[SerializedName('is_active')]
    #[Groups(['role:list', 'role:detail', 'user:read', 'user:detail'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de création est obligatoire.")]
    #[Groups(['role:detail'])]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'd-m-Y H:i:s'],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTime::RFC3339],
    )]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de mise à jour est obligatoire.")]
    #[Groups(['role:detail'])]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'd-m-Y H:i:s'],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTime::RFC3339],
    )]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    private bool $isDelete = false;

    /**
     * Côté propriétaire de la relation ManyToMany Role <-> Permission (table role_permission).
     *
     * @var Collection<int, Permission>
     */
    #[ORM\ManyToMany(targetEntity: Permission::class, inversedBy: 'roles')]
    #[ORM\JoinTable(name: 'role_permission')]
    #[Groups(['role:detail'])]
    private Collection $permissions;

    /**
     * Côté inverse de la relation ManyToMany User <-> Role.
     * Le côté propriétaire (table de jointure user_role) est porté par User,
     * car c'est l'utilisateur qui "choisit" ses rôles (et est limité à 2).
     *
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'assignedRoles')]
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
