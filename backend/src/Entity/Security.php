<?php

namespace App\Entity;

use App\Repository\SecurityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SecurityRepository::class)]
#[ORM\Table(name: 'security')]
#[ORM\HasLifecycleCallbacks]
class Security
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ✅ Plus de relation ManyToOne vers SecurityMode
    // On stocke directement le nom du mode en texte
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le mode de sécurisation est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le mode ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $securityMode = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateSecurisation = null;

    #[ORM\Column]
    private bool $isDelete = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'security', targetEntity: AssetSecurity::class, cascade: ['persist', 'remove'])]
    private Collection $assetSecurities;

    #[ORM\OneToMany(mappedBy: 'security', targetEntity: SecurityDocument::class, cascade: ['persist', 'remove'])]
    private Collection $securityDocuments;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->assetSecurities = new ArrayCollection();
        $this->securityDocuments = new ArrayCollection();
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

    public function getSecurityMode(): ?string
    {
        return $this->securityMode;
    }

    public function setSecurityMode(string $securityMode): static
    {
        $this->securityMode = $securityMode;
        return $this;
    }

    public function getDateSecurisation(): ?\DateTimeImmutable
    {
        return $this->dateSecurisation;
    }

    public function setDateSecurisation(?\DateTimeImmutable $dateSecurisation): static
    {
        $this->dateSecurisation = $dateSecurisation;
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
     * @return Collection<int, AssetSecurity>
     */
    public function getAssetSecurities(): Collection
    {
        return $this->assetSecurities;
    }

    public function addAssetSecurity(AssetSecurity $assetSecurity): static
    {
        if (!$this->assetSecurities->contains($assetSecurity)) {
            $this->assetSecurities->add($assetSecurity);
            $assetSecurity->setSecurity($this);
        }
        return $this;
    }

    public function removeAssetSecurity(AssetSecurity $assetSecurity): static
    {
        if ($this->assetSecurities->removeElement($assetSecurity)) {
            if ($assetSecurity->getSecurity() === $this) {
                $assetSecurity->setSecurity(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SecurityDocument>
     */
    public function getSecurityDocuments(): Collection
    {
        return $this->securityDocuments;
    }

    public function addSecurityDocument(SecurityDocument $securityDocument): static
    {
        if (!$this->securityDocuments->contains($securityDocument)) {
            $this->securityDocuments->add($securityDocument);
            $securityDocument->setSecurity($this);
        }
        return $this;
    }

    public function removeSecurityDocument(SecurityDocument $securityDocument): static
    {
        if ($this->securityDocuments->removeElement($securityDocument)) {
            if ($securityDocument->getSecurity() === $this) {
                $securityDocument->setSecurity(null);
            }
        }
        return $this;
    }
}