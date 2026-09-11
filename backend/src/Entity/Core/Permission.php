<?php

namespace App\Entity\Core;

use App\Repository\Core\PermissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'core_permission')]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        name: 'id',
        type: Types::INTEGER,
        nullable: false
    )]
    #[Groups(['GetCollection:Permission', 'Get:Permission'])]
    private ?int $id = null;

    #[ORM\Column(
        name: 'nom',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    #[Groups(['GetCollection:Permission', 'Get:Permission'])]
    private ?string $nom = null;

    #[ORM\Column(
        name: 'description',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    #[Groups(['GetCollection:Permission', 'Get:Permission'])]
    private ?string $description = null;

    /** @var Collection<int, RolePermission> */
    #[ORM\OneToMany(
        targetEntity: RolePermission::class,
        mappedBy: 'idPermission',
        orphanRemoval: true
    )]
    private Collection $rolePermissions;

    public function __construct()
    {
        $this->rolePermissions = new ArrayCollection();
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

    /** @return Collection<int, RolePermission> */
    public function getRolePermissions(): Collection
    {
        return $this->rolePermissions;
    }

    public function addRolePermission(RolePermission $rolePermission): static
    {
        if (!$this->rolePermissions->contains($rolePermission)) {
            $this->rolePermissions->add($rolePermission);
            $rolePermission->setIdPermission($this);
        }
        return $this;
    }

    public function removeRolePermission(RolePermission $rolePermission): static
    {
        if ($this->rolePermissions->removeElement($rolePermission)) {
            if ($rolePermission->getIdPermission() === $this) {
                $rolePermission->setIdPermission(null);
            }
        }
        return $this;
    }
}
