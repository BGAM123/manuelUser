<?php

namespace App\Entity\Core;

use App\Repository\Core\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'core_role')]
#[ORM\HasLifecycleCallbacks]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        name: 'id',
        type: Types::INTEGER,
        nullable: false
    )]
    #[Groups(['GetCollection:Role', 'Get:Role'])]
    private ?int $id = null;

    #[ORM\Column(
        name: 'nom',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    #[Groups(['GetCollection:Role', 'Get:Role'])]
    private ?string $nom = null;

    #[ORM\Column(
        name: 'description',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    #[Groups(['GetCollection:Role', 'Get:Role'])]
    private ?string $description = null;

    #[ORM\Column(
        name: 'is_delete',
        type: Types::BOOLEAN,
        nullable: false,
        options: ['default' => false]
    )]
    #[Groups(['GetCollection:Role', 'Get:Role'])]
    private bool $isDelete = false;

    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        nullable: false,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    #[Groups(['GetCollection:Role', 'Get:Role'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        nullable: false,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    #[Groups(['GetCollection:Role', 'Get:Role'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(
        name: 'search',
        type: Types::STRING,
        length: 1000,
        nullable: true
    )]
    private ?string $search = null;

    /** @var Collection<int, RolePermission> */
    #[ORM\OneToMany(
        targetEntity: RolePermission::class,
        mappedBy: 'idRole',
        orphanRemoval: true
    )]
    private Collection $rolePermissions;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'idRole')]
    private Collection $users;

    public function __construct()
    {
        $this->rolePermissions = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    // ──────────────── Getters / Setters ────────────────

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
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = trim($description ?? '');
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

        $this->search = implode(' <br/> ', array_filter([
            $this->nom ?? '',
            $this->description ?? ''
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

    /** @return Collection<int, RolePermission> */
    public function getRolePermissions(): Collection
    {
        return $this->rolePermissions;
    }

    public function addRolePermission(RolePermission $rolePermission): static
    {
        if (!$this->rolePermissions->contains($rolePermission)) {
            $this->rolePermissions->add($rolePermission);
            $rolePermission->setIdRole($this);
        }
        return $this;
    }

    public function removeRolePermission(RolePermission $rolePermission): static
    {
        if ($this->rolePermissions->removeElement($rolePermission)) {
            if ($rolePermission->getIdRole() === $this) {
                $rolePermission->setIdRole(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, User> */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setIdRole($this);
        }
        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            if ($user->getIdRole() === $this) {
                $user->setIdRole(null);
            }
        }
        return $this;
    }
}
