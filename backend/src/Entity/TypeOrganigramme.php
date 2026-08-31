<?php

namespace App\Entity;

use App\Repository\TypeOrganigrammeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TypeOrganigrammeRepository::class)]
#[ORM\Table(name: 'type_organigramme')]
#[UniqueEntity(fields: ['nom'], message: 'Ce nom de type d\'organigramme est déjà utilisé.')]
class TypeOrganigramme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['type_organigramme:read', 'type_organigramme:list', 'service:list', 'service:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 191, unique: true)]
    #[Assert\NotBlank(message: 'Le nom du type d\'organigramme est obligatoire.')]
    #[Assert\Length(min: 2, max: 191)]
    #[Groups(['type_organigramme:read', 'type_organigramme:list', 'service:list', 'service:detail'])]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['type_organigramme:read'])]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    private bool $isDelete = false;

    /**
     * Relation ManyToMany avec Service
     * @var Collection<int, Service>
     */
    #[ORM\ManyToMany(targetEntity: Service::class, inversedBy: 'typeOrganigrammes', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'type_organigramme_service')]
    private Collection $services;

    public function __construct()
    {
        $this->services = new ArrayCollection();
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
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
        $this->updated_at = new \DateTimeImmutable();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        $this->updated_at = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    public function isIsDelete(): bool
    {
        return $this->isDelete;
    }

    public function setIsDelete(bool $isDelete): static
    {
        $this->isDelete = $isDelete;
        $this->updated_at = new \DateTimeImmutable();
        return $this;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->addTypeOrganigramme($this);
        }
        return $this;
    }

    public function removeService(Service $service): static
    {
        if ($this->services->removeElement($service)) {
            $service->removeTypeOrganigramme($this);
        }
        return $this;
    }
}
