<?php

namespace App\Entity;

use App\Repository\SecurityModeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SecurityModeRepository::class)]
#[ORM\Table(name: 'security_mode')]
#[ORM\HasLifecycleCallbacks]
class SecurityMode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['security_mode:list', 'security_mode:detail', 'security:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du mode de sécurisation est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.')]
    #[Groups(['security_mode:list', 'security_mode:detail', 'security:detail'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['security_mode:list', 'security_mode:detail', 'security:detail'])]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isDelete = false;

    #[ORM\Column]
    #[Groups(['security_mode:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['security_mode:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'securityMode', targetEntity: Security::class)]
    private Collection $securities;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->securities = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Security>
     */
    public function getSecurities(): Collection
    {
        return $this->securities;
    }

    public function addSecurity(Security $security): static
    {
        if (!$this->securities->contains($security)) {
            $this->securities->add($security);
            $security->setSecurityMode($this);
        }

        return $this;
    }

    public function removeSecurity(Security $security): static
    {
        if ($this->securities->removeElement($security)) {
            if ($security->getSecurityMode() === $this) {
                $security->setSecurityMode(null);
            }
        }

        return $this;
    }
}
