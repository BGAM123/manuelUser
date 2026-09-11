<?php

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\Core\ArchiveRepository;

#[ORM\Entity(repositoryClass: ArchiveRepository::class)]
#[ORM\Table(name: 'archive')]
#[ORM\HasLifecycleCallbacks]
class Archive
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $idCourrier = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $idTransmission = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $idCourrierDepart = [];

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $idSalle = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $idCoffre = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDelete = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Getters et Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdCourriers(): ?array
    {
        return $this->idCourrier ?? [];
    }

    public function setIdCourriers(?array $idCourrier): self
    {
        $this->idCourrier = $idCourrier;
        return $this;
    }

    public function addIdCourrier(int $idCourrier): self
    {
        if (!in_array($idCourrier, $this->idCourrier ?? [])) {
            $this->idCourrier[] = $idCourrier;
        }
        return $this;
    }
    public function removeIdCourrier(int $idCourrier): self
    {
        $this->idCourrier = array_values(array_filter(
            $this->idCourrier ?? [],
            fn($id) => $id !== $idCourrier
        ));
        return $this;
    }

    public function getIdTransmissions(): ?array
    {
        return $this->idTransmission ?? [];
    }

    public function setIdTransmissions(?array $idTransmission): self
    {
        $this->idTransmission = $idTransmission;
        return $this;
    }

    public function addIdTransmission(int $idTransmission): self
    {
        if (!in_array($idTransmission, $this->idTransmission ?? [])) {
            $this->idTransmission[] = $idTransmission;
        }
        return $this;
    }

    public function removeIdTransmission(int $idTransmission): self
    {
        $this->idTransmission = array_values(array_filter(
            $this->idTransmission ?? [],
            fn($id) => $id !== $idTransmission
        ));
        return $this;
    }

    public function getIdCourriersDepart(): ?array
    {
        return $this->idCourrierDepart ?? [];
    }

    public function setIdCourriersDepart(?array $idCourrierDepart): self
    {
        $this->idCourrierDepart = $idCourrierDepart;
        return $this;
    }

    public function addIdCourrierDepart(int $idCourrierDepart): self
    {
        if (!in_array($idCourrierDepart, $this->idCourrierDepart ?? [])) {
            $this->idCourrierDepart[] = $idCourrierDepart;
        }
        return $this;
    }

    public function removeIdCourrierDepart(int $idCourrierDepart): self
    {
        $this->idCourrierDepart = array_values(array_filter(
            $this->idCourrierDepart ?? [],
            fn($id) => $id !== $idCourrierDepart
        ));
        return $this;
    }

    public function getIdSalle(): ?int
    {
        return $this->idSalle;
    }

    public function setIdSalle(?int $idSalle): self
    {
        $this->idSalle = $idSalle;
        return $this;
    }

    public function getIdCoffre(): ?int
    {
        return $this->idCoffre;
    }

    public function setIdCoffre(?int $idCoffre): self
    {
        $this->idCoffre = $idCoffre;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isDelete(): bool
    {
        return $this->isDelete;
    }

    public function setIsDelete(bool $isDelete): self
    {
        $this->isDelete = $isDelete;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
