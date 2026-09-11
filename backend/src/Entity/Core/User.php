<?php

namespace App\Entity\Core;

use App\Entity\Cour\Courrier;
use App\Entity\Cour\CourrierDepart;
use App\Entity\Cour\Reponse;
use App\Entity\Cour\Transmission;
use App\Repository\Core\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'core_users')]
#[ORM\UniqueConstraint(name: 'uniq_user_username', fields: ['username'])]
#[ORM\UniqueConstraint(name: 'uniq_user_email', fields: ['email'])]
#[ORM\Index(name: 'idx_user_reset_token', columns: ['reset_token'])]
#[ORM\Index(name: 'idx_user_search', columns: ['search'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['username'], errorPath: 'username', message: 'There is already a user with this username.')]
#[UniqueEntity(fields: ['email'], errorPath: 'email', message: 'There is already a user with this email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, nullable: false)]
    #[Groups(['user'])]
    private ?int $id = null;

    #[ORM\Column(name: 'username', type: Types::STRING, length: 100, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['user'])]
    private ?string $username = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(name: 'roles', type: Types::JSON, nullable: false)]
    private array $roles = ['ROLE_ADMIN'];

    #[ORM\Column(name: 'password', type: Types::STRING, length: 255, nullable: false)]
    private ?string $password = null;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 100, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 100)]
    #[Groups(['user'])]
    private ?string $email = null;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 100, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['user'])]
    private ?string $lastName = null;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['user'])]
    private ?string $firstName = null;

    #[ORM\Column(name: 'civilite', type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['user'])]
    private ?string $civilite = null;

    #[ORM\Column(name: 'phone', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['user'])]
    private ?string $phone = null;

    #[ORM\Column(name: 'is_first_login', type: Types::BOOLEAN, options: ['default' => false], nullable: false)]
    private bool $isFirstLogin = false;

    #[ORM\Column(name: 'is_verified', type: Types::BOOLEAN, options: ['default' => true], nullable: false)]
    #[Groups(['user'])]
    private bool $isVerified = true;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, options: ['default' => false], nullable: false)]
    #[Groups(['user'])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'], nullable: false)]
    #[Groups(['user'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'], nullable: false)]
    #[Groups(['user'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'search', type: Types::STRING, length: 1000, nullable: true)]
    private ?string $search = null;

    #[ORM\Column(name: 'reset_token', type: Types::TEXT, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(name: 'reset_token_expires_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resetTokenExpiresAt = null;

    #[ORM\Column(name: 'code_otp', type: Types::STRING, length: 6, nullable: true)]
    private ?string $codeOtp = null;

    #[ORM\Column(name: 'expiration_otp', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expirationOtp = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true], nullable: false)]
    private bool $isActive = true;

    #[ORM\Column(name: 'is_signataire', type: Types::BOOLEAN, options: ['default' => false], nullable: false)]
    #[Groups(['user'])]
    private bool $isSignataire = false;

    #[ORM\Column(name: 'langue', type: Types::BOOLEAN, options: ['default' => false], nullable: false)]
    #[Groups(['user'])]
    private bool $langue = false;

    #[ORM\Column(name: 'avatar', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['user'])]
    private ?string $avatar = null;

    /**
     * @var array<int>|null
     */
    #[ORM\Column(name: 'services_additionel', type: Types::JSON, nullable: true)]
    #[Groups(['user'])]
    private ?array $servicesAdditionel = null;

    // ── Relations ──────────────────────────────────────

    /**
     * @var Collection<int, Service>
     */
    #[ORM\OneToMany(targetEntity: Service::class, mappedBy: 'chefService')]
    private Collection $services;

    /**
     * @var Collection<int, Courrier>
     */
    #[ORM\OneToMany(targetEntity: Courrier::class, mappedBy: 'idCreateur')]
    private Collection $courriers;

    /**
     * @var Collection<int, Transmission>
     */
    #[ORM\OneToMany(targetEntity: Transmission::class, mappedBy: 'idEmetteur')]
    private Collection $transmissions;

    /**
     * @var Collection<int, Reponse>
     */
    #[ORM\OneToMany(targetEntity: Reponse::class, mappedBy: 'idRedacteur')]
    private Collection $reponses;

    /**
     * @var Collection<int, CourrierDepart>
     */
    #[ORM\OneToMany(targetEntity: CourrierDepart::class, mappedBy: 'idSignataire')]
    private Collection $courrierDeparts;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(name: 'id_service', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?Service $idService = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(name: 'id_role', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?Role $idRole = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(name: 'id_correspondant', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?Correspondant $idCorrespondant = null;

    public function __construct()
    {
        $this->services        = new ArrayCollection();
        $this->courriers       = new ArrayCollection();
        $this->transmissions   = new ArrayCollection();
        $this->reponses        = new ArrayCollection();
        $this->courrierDeparts = new ArrayCollection();
        
        // ✅ Définir ROLE_ADMIN par défaut lors de la création d'un utilisateur
        $this->roles = ['ROLE_ADMIN'];
        
        // ✅ Définir isSignataire à false par défaut
        $this->isSignataire = false;
    }

    public function __toString(): string
    {
        return (string) ($this->username ?? '');
    }

    // ── Identité & sécurité ────────────────────────────

    public function getUserIdentifier(): string
    {
        return (string) ($this->username ?? '');
    }

    public function eraseCredentials(): void
    {
        // $this->plainPassword = null;
    }

    // ── Getters / Setters ──────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        $this->updateSearchField();
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles   = $this->roles ?? [];
        $roles[] = 'ROLE_ADMIN';
        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(?array $roles): static
    {
        $this->roles = $roles ?? [];
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        $this->updateSearchField();
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        $this->updateSearchField();
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        $this->updateSearchField();
        return $this;
    }

    public function getCivilite(): ?string
    {
        return $this->civilite;
    }

    public function setCivilite(?string $civilite): static
    {
        $this->civilite = $civilite;
        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    public function getFullName2(): string
    {
        return trim(($this->lastName ?? '') . ' ' . ($this->firstName ?? ''));
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        $this->updateSearchField();
        return $this;
    }

    public function getFirstLogin(): bool
    {
        return $this->isFirstLogin;
    }

    public function isFirstLogin(): bool
    {
        return $this->isFirstLogin;
    }

    public function setIsFirstLogin(?bool $isFirstLogin): static
    {
        $this->isFirstLogin = (bool) ($isFirstLogin ?? false);
        return $this;
    }

    // Alias pour compatibilité
    public function setFirstLogin(?bool $isFirstLogin): static
    {
        return $this->setIsFirstLogin($isFirstLogin);
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(?bool $isVerified): static
    {
        $this->isVerified = (bool) ($isVerified ?? true);
        return $this;
    }

    // Alias pour compatibilité
    public function setVerified(?bool $isVerified): static
    {
        return $this->setIsVerified($isVerified);
    }

    public function isDelete(): bool
    {
        return $this->isDelete;
    }

    public function setIsDelete(?bool $isDelete): static
    {
        $this->isDelete = (bool) ($isDelete ?? false);
        return $this;
    }

    // Alias pour compatibilité
    public function setDelete(?bool $isDelete): static
    {
        return $this->setIsDelete($isDelete);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): static
    {
        $this->isActive = (bool) ($isActive ?? true);
        return $this;
    }

    // Alias pour compatibilité
    public function setActive(?bool $isActive): static
    {
        return $this->setIsActive($isActive);
    }

    public function isSignataire(): bool
    {
        return $this->isSignataire;
    }

    public function setIsSignataire(?bool $isSignataire): static
    {
        $this->isSignataire = (bool) ($isSignataire ?? false);
        return $this;
    }

    // Alias pour compatibilité
    public function setSignataire(?bool $isSignataire): static
    {
        return $this->setIsSignataire($isSignataire);
    }

    public function isLangue(): bool
    {
        return $this->langue;
    }

    public function getLangue(): bool
    {
        return $this->langue;
    }

    public function setLangue(?bool $langue): static
    {
        $this->langue = (bool) ($langue ?? false);
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
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

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeInterface $resetTokenExpiresAt): static
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;
        return $this;
    }

    public function getCodeOtp(): ?string
    {
        return $this->codeOtp;
    }

    public function setCodeOtp(?string $codeOtp): static
    {
        $this->codeOtp = $codeOtp;
        return $this;
    }

    public function getExpirationOtp(): ?\DateTimeInterface
    {
        return $this->expirationOtp;
    }

    public function setExpirationOtp(?\DateTimeInterface $expirationOtp): static
    {
        $this->expirationOtp = $expirationOtp;
        return $this;
    }

    // ── Relations helpers ─────────────────────────────

    /** @return Collection<int, Service> */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->setChefService($this);
        }
        return $this;
    }

    public function removeService(Service $service): static
    {
        if ($this->services->removeElement($service) && $service->getChefService() === $this) {
            $service->setChefService(null);
        }
        return $this;
    }

    /** @return Collection<int, Courrier> */
    public function getCourriers(): Collection
    {
        return $this->courriers;
    }

    public function addCourrier(Courrier $courrier): static
    {
        if (!$this->courriers->contains($courrier)) {
            $this->courriers->add($courrier);
            $courrier->setIdCreateur($this);
        }
        return $this;
    }

    public function removeCourrier(Courrier $courrier): static
    {
        if ($this->courriers->removeElement($courrier) && $courrier->getIdCreateur() === $this) {
            $courrier->setIdCreateur(null);
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
            $transmission->setIdEmetteur($this);
        }
        return $this;
    }

    public function removeTransmission(Transmission $transmission): static
    {
        if ($this->transmissions->removeElement($transmission) && $transmission->getIdEmetteur() === $this) {
            $transmission->setIdEmetteur(null);
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
            $reponse->setIdRedacteur($this);
        }
        return $this;
    }

    public function removeReponse(Reponse $reponse): static
    {
        if ($this->reponses->removeElement($reponse) && $reponse->getIdRedacteur() === $this) {
            $reponse->setIdRedacteur(null);
        }
        return $this;
    }

    /** @return Collection<int, CourrierDepart> */
    public function getCourrierDeparts(): Collection
    {
        return $this->courrierDeparts;
    }

    public function addCourrierDepart(CourrierDepart $courrierDepart): static
    {
        if (!$this->courrierDeparts->contains($courrierDepart)) {
            $this->courrierDeparts->add($courrierDepart);
            $courrierDepart->setIdSignataire($this);
        }
        return $this;
    }

    public function removeCourrierDepart(CourrierDepart $courrierDepart): static
    {
        if ($this->courrierDeparts->removeElement($courrierDepart) && $courrierDepart->getIdSignataire() === $this) {
            $courrierDepart->setIdSignataire(null);
        }
        return $this;
    }

    public function getIdService(): ?Service
    {
        return $this->idService;
    }

    public function setIdService(?Service $idService): static
    {
        $this->idService = $idService;
        return $this;
    }

    public function getIdRole(): ?Role
    {
        return $this->idRole;
    }

  
    public function setIdRole(?Role $idRole): static
    {
        $this->idRole = $idRole;

        if ($idRole) {
            $this->roles = [$idRole->getNom()]; // Ex: ROLE_ADMIN, ROLE_CHEF_SERVICE
        }

        return $this;
    }

    public function getIdCorrespondant(): ?Correspondant
    {
        return $this->idCorrespondant;
    }

    public function setIdCorrespondant(?Correspondant $idCorrespondant): static
    {
        $this->idCorrespondant = $idCorrespondant;
        return $this;
    }

    /**
     * @return array<int>|null
     */
    public function getServicesAdditionel(): ?array
    {
        return $this->servicesAdditionel;
    }

    /**
     * @param array<int>|null $servicesAdditionel
     */
    public function setServicesAdditionel(?array $servicesAdditionel): static
    {
        $this->servicesAdditionel = $servicesAdditionel;
        return $this;
    }

    // ── Lifecycle ──────────────────────────────────────

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
            $this->username,
            $this->email,
            $this->lastName,
            $this->firstName,
            $this->phone,
        ]));
    }

    /**
 * Retourne la liste des rôles de l’utilisateur sous forme de collection Doctrine.
 * (utilisé par AccessCheckerService)
 *
 * @return Collection<int, Role>
 */
    public function getUserRoles(): Collection
    {
        $roles = new ArrayCollection();
        if ($this->idRole !== null) {
            $roles->add($this->idRole);
        }
        return $roles;
    }

}
