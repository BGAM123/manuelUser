<?php

namespace App\Entity\Core;

use App\Repository\Core\RolePermissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: RolePermissionRepository::class)]
#[ORM\Table(name: 'core_role_permission')]
#[ORM\HasLifecycleCallbacks]
class RolePermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        name: 'id',
        type: Types::INTEGER,
        nullable: false
    )]
    #[Groups(['GetCollection:RolePermission', 'Get:RolePermission'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rolePermissions')]
    #[ORM\JoinColumn(
        name: 'id_role',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'CASCADE'
    )]
    #[Groups(['GetCollection:RolePermission', 'Get:RolePermission'])]
    private ?Role $idRole = null;

    #[ORM\ManyToOne(inversedBy: 'rolePermissions')]
    #[ORM\JoinColumn(
        name: 'id_permission',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'CASCADE'
    )]
    #[Groups(['GetCollection:RolePermission', 'Get:RolePermission'])]
    private ?Permission $idPermission = null;

    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        nullable: false,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    #[Groups(['GetCollection:RolePermission', 'Get:RolePermission'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        nullable: false,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    #[Groups(['GetCollection:RolePermission', 'Get:RolePermission'])]
    private ?\DateTimeImmutable $updatedAt = null;

    // ──────────────── Getters / Setters ────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdRole(): ?Role
    {
        return $this->idRole;
    }

    public function setIdRole(?Role $idRole): static
    {
        $this->idRole = $idRole;
        return $this;
    }

    public function getIdPermission(): ?Permission
    {
        return $this->idPermission;
    }

    public function setIdPermission(?Permission $idPermission): static
    {
        $this->idPermission = $idPermission;
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
    public function updateTimestamps(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        $this->updatedAt = new \DateTimeImmutable();
    }
}
