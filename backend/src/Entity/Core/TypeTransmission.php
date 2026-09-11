<?php

namespace App\Entity\Core;

use App\Repository\Core\TypeTransmissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TypeTransmissionRepository::class)]
#[ORM\Table(name: 'core_type_transmission')]
#[ORM\UniqueConstraint(name: 'UNIQ_TYPE_TRANSMISSION_NOM', columns: ['nom'])]
#[ORM\HasLifecycleCallbacks]
class TypeTransmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Groups(['GetCollection:TypeTransmission', 'Get:TypeTransmission'])]
    private ?int $id = null;

    #[ORM\Column(name: 'nom', type: Types::STRING, length: 255, nullable: false)]
    #[Groups(['GetCollection:TypeTransmission', 'Get:TypeTransmission'])]
    private string $nom = '';

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:TypeTransmission', 'Get:TypeTransmission'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:TypeTransmission', 'Get:TypeTransmission'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = trim($nom);
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
    public function updateTimestamps(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

}
