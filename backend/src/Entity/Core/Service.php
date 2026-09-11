<?php

namespace App\Entity\Core;

use App\Entity\Cour\Courrier;
use App\Entity\Cour\Reponse;
use App\Entity\Cour\Transmission;
use App\Repository\Core\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: 'core_service')]
#[ORM\HasLifecycleCallbacks]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, nullable: false)]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private ?int $id = null;

    #[ORM\Column(name: 'nom', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private ?string $nom = null;

    #[ORM\Column(name: 'sigle', type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private ?string $sigle = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'serviceEnfants')]
    #[ORM\JoinColumn(name: 'id_service_parent', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    #[Groups(['Get:Service'])]
    private ?self $idServiceParent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'idServiceParent')]
    private Collection $serviceEnfants;

    #[ORM\ManyToOne(inversedBy: 'services')]
    #[ORM\JoinColumn(name: 'id_chef_service', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private ?User $chefService = null;

    #[ORM\Column(name: 'email_service', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private ?string $emailService = null;

    #[ORM\Column(name: 'telephone', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private ?string $telephone = null;

    #[ORM\Column(name: 'numero_ordre', type: Types::INTEGER, nullable: true)]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private ?int $numeroOrdre = null;

    #[ORM\Column(name: 'type_service', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private ?string $typeService = null;

    // ✅ NOUVEAU : Pour différencier les directions des services
    #[ORM\Column(name: 'is_direction', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private bool $isDirection = false;

    // ✅ NOUVEAU : Pour filtrer les services affichés lors de la première transmission
    #[ORM\Column(name: 'is_visible_in_transmission', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private bool $isVisibleInTransmission = false;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, nullable: false, options: ['default' => true])]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private bool $isActive = true;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:Service', 'Get:Service'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'search', type: Types::STRING, length: 1000, nullable: true)]
    private ?string $search = null;

    /**
     * @var Collection<int, Courrier>
     */
    #[ORM\OneToMany(targetEntity: Courrier::class, mappedBy: 'idServiceTraitant')]
    private Collection $courriers;

    /**
     * @var Collection<int, Transmission>
     */
    #[ORM\OneToMany(targetEntity: Transmission::class, mappedBy: 'idServiceDestinataire')]
    private Collection $transmissions;

    /**
     * @var Collection<int, Reponse>
     */
    #[ORM\OneToMany(targetEntity: Reponse::class, mappedBy: 'idServiceDestinataire')]
    private Collection $reponses;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'idService')]
    private Collection $users;

    public function __construct()
    {
        $this->serviceEnfants = new ArrayCollection();
        $this->courriers = new ArrayCollection();
        $this->transmissions = new ArrayCollection();
        $this->reponses = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) ($this->nom ?? '');
    }

    // ──────────────── GETTERS / SETTERS ────────────────

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
        $this->nom = $nom !== null ? trim($nom) : null;
        $this->updateSearchField();
        return $this;
    }

    public function getSigle(): ?string
    {
        return $this->sigle;
    }

    public function setSigle(?string $sigle): static
    {
        $this->sigle = $sigle !== null ? trim($sigle) : null;
        $this->updateSearchField();
        return $this;
    }

    public function getIdServiceParent(): ?self
    {
        return $this->idServiceParent;
    }

    public function setIdServiceParent(?self $idServiceParent): static
    {
        $this->idServiceParent = $idServiceParent;
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getServiceEnfants(): Collection
    {
        return $this->serviceEnfants;
    }

    public function addServiceEnfant(self $service): static
    {
        if (!$this->serviceEnfants->contains($service)) {
            $this->serviceEnfants->add($service);
            $service->setIdServiceParent($this);
        }
        return $this;
    }

    public function removeServiceEnfant(self $service): static
    {
        if ($this->serviceEnfants->removeElement($service) && $service->getIdServiceParent() === $this) {
            $service->setIdServiceParent(null);
        }
        return $this;
    }

    public function getChefService(): ?User
    {
        return $this->chefService;
    }

    public function setChefService(?User $chefService): static
    {
        $this->chefService = $chefService;
        return $this;
    }

    public function getEmailService(): ?string
    {
        return $this->emailService;
    }

    public function setEmailService(?string $emailService): static
    {
        $this->emailService = $emailService !== null ? trim($emailService) : null;
        $this->updateSearchField();
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone !== null ? trim($telephone) : null;
        $this->updateSearchField();
        return $this;
    }

    public function getNumeroOrdre(): ?int
    {
        return $this->numeroOrdre;
    }

    public function setNumeroOrdre(?int $numeroOrdre): static
    {
        $this->numeroOrdre = $numeroOrdre;
        return $this;
    }

    public function getTypeService(): ?string
    {
        return $this->typeService;
    }

    public function setTypeService(?string $typeService): static
    {
        $this->typeService = $typeService !== null ? trim($typeService) : null;
        return $this;
    }

    // ✅ NOUVEAU : Getter/Setter pour isDirection
    public function isDirection(): bool
    {
        return $this->isDirection;
    }

    public function setDirection(bool $isDirection): static
    {
        $this->isDirection = $isDirection;
        return $this;
    }

    // ✅ NOUVEAU : Getter/Setter pour isVisibleInTransmission
    public function isVisibleInTransmission(): bool
    {
        return $this->isVisibleInTransmission;
    }

    public function setVisibleInTransmission(bool $isVisibleInTransmission): static
    {
        $this->isVisibleInTransmission = $isVisibleInTransmission;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function setSearch(?string $search): static
    {
        $this->search = $search;
        return $this;
    }

    // ──────────────── LIFECYCLE ────────────────

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateSearchField(): void
    {
        $this->search = implode(' <br/> ', array_filter([
            $this->nom,
            $this->sigle,
            $this->emailService,
            $this->telephone,
        ]));
    }

    // ──────────────── RELATIONS HELPERS ────────────────

    /** @return Collection<int, Courrier> */
    public function getCourriers(): Collection
    {
        return $this->courriers;
    }

    public function addCourrier(Courrier $courrier): static
    {
        if (!$this->courriers->contains($courrier)) {
            $this->courriers->add($courrier);
            $courrier->setIdServiceTraitant($this);
        }
        return $this;
    }

    public function removeCourrier(Courrier $courrier): static
    {
        if ($this->courriers->removeElement($courrier) && $courrier->getIdServiceTraitant() === $this) {
            $courrier->setIdServiceTraitant(null);
        }
        return $this;
    }

    /** @return Collection<int, Transmission> */
    public function getTransmissions(): Collection
    {
        return $this->transmissions;
    }

    public function addTransmission(Transmission $transmission): static
    {
        if (!$this->transmissions->contains($transmission)) {
            $this->transmissions->add($transmission);
            $transmission->setIdServiceDestinataire($this);
        }
        return $this;
    }

    public function removeTransmission(Transmission $transmission): static
    {
        if ($this->transmissions->removeElement($transmission) && $transmission->getIdServiceDestinataire() === $this) {
            $transmission->setIdServiceDestinataire(null);
        }
        return $this;
    }

    /** @return Collection<int, Reponse> */
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function addReponse(Reponse $reponse): static
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses->add($reponse);
            $reponse->setIdServiceDestinataire($this);
        }
        return $this;
    }

    public function removeReponse(Reponse $reponse): static
    {
        if ($this->reponses->removeElement($reponse) && $reponse->getIdServiceDestinataire() === $this) {
            $reponse->setIdServiceDestinataire(null);
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
            $user->setIdService($this);
        }
        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user) && $user->getIdService() === $this) {
            $user->setIdService(null);
        }
        return $this;
    }
}