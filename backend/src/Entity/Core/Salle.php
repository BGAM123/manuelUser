<?php

namespace App\Entity\Core;

use App\Repository\Core\SalleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SalleRepository::class)]
#[ORM\Table(name: 'core_salle')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['nom'], message: 'Ce nom de salle existe déjà.')]
class Salle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, nullable: false)]
    #[Groups(['GetCollection:Salle', 'Get:Salle'])]
    private ?int $id = null;

    #[ORM\Column(name: 'nom', type: Types::STRING, length: 255, nullable: false, unique: true)]
    #[Groups(['GetCollection:Salle', 'Get:Salle'])]
    private ?string $nom = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, nullable: false, options: ['default' => true])]
    #[Groups(['GetCollection:Salle', 'Get:Salle'])]
    private bool $isActive = true;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Salle', 'Get:Salle'])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: false)]
    #[Groups(['GetCollection:Salle', 'Get:Salle'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: false)]
    #[Groups(['GetCollection:Salle', 'Get:Salle'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
