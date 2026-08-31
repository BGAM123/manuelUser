<?php

// src/Entity/User.php

namespace App\Entity;

use App\Entity\Permission;
use App\Entity\Groupe;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\JoinColumn;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: "Cette adresse email est déjà utilisée par un autre utilisateur.")]
#[UniqueEntity(fields: ['cni'], message: "Ce numéro de CNI est déjà utilisé par un autre utilisateur.")]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * Nombre maximum de rôles métier (entité Role) qu'un utilisateur peut se voir
     * attribuer simultanément. Contrainte volontairement applicative (et non uniquement
     * base de données) : elle est vérifiée à la fois par addAssignedRole() (échec rapide,
     * utile en cas d'appel direct depuis un contrôleur) et par la contrainte Assert\Count
     * ci-dessous (garde-fou exécuté par le ValidatorInterface avant persistance).
     */
    public const MAX_ASSIGNED_ROLES = 1;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'user:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 191)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire.")]
    #[Groups(['user:read', 'user:detail'])]
    #[Assert\Length(
        min: 2,
        max: 191,
        minMessage: "Le prénom doit faire au moins {{ limit }} caractères.",
        maxMessage: "Le prénom ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 191)]
    #[Assert\NotBlank(message: "Le nom de famille est obligatoire.")]
    #[Groups(['user:read', 'user:detail'])]
    #[Assert\Length(
        min: 2,
        max: 191,
        minMessage: "Le nom doit faire au moins {{ limit }} caractères.",
        maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $lastName = null;

    #[ORM\Column(length: 191, unique: true)]
    #[Assert\NotBlank(message: "L'adresse email est obligatoire.")]
    #[Groups(['user:read', 'user:detail'])]
    #[Assert\Email(message: "L'adresse email '{{ value }}' n'est pas une adresse valide.")]
    private ?string $email = null;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['user:read', 'user:detail'])]
    private ?string $matricule = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['user:read', 'user:detail'])]
    private ?string $cni = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    /** @var string|null The hashed password */
    #[ORM\Column]
    #[Assert\NotBlank(message: "Le mot de passe est obligatoire.")]
    private ?string $password = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    #[Groups(['user:read', 'user:detail'])]
    #[SerializedName('is_active')]
    private bool $isActive = true;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de création est obligatoire.")]
    #[Groups(['user:read', 'user:detail'])]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'd-m-Y H:i:s'],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTime::RFC3339],
    )]
    private ?\DateTimeImmutable $createdAt = null;


    #[ORM\Column(type: 'string', length: 10, options: ['default' => 'fr'])]
    #[Assert\Choice(choices: ['fr', 'en', 'es', 'de', 'it'], message: 'Langue non supportée')]
    private ?string $langue = 'fr';

    // Getter et Setter pour langue
    public function getLangue(): ?string
    {
        return $this->langue;
    }

    public function setLangue(string $langue): self
    {
        $this->langue = $langue;
        return $this;
    }
    
    #[ManyToOne(targetEntity: Service::class)]
    #[JoinColumn(name: 'service_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['user:read', 'user:detail'])]
    private ?Service $service = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    private bool $isDelete = false;

    #[ORM\Column(name: 'two_factor_enabled', type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read', 'user:detail'])]
    #[SerializedName('twoFactorEnabled')]
    #[Assert\Type(type: 'bool')]
    private bool $twoFactorEnabled = false;

    #[ORM\Column(name: 'otp_code', type: 'string', length: 10, nullable: true)]
    private ?string $otpCode = null;

    #[ORM\Column(name: 'otp_expires_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $otpExpiresAt = null;

    /**
     * Côté propriétaire de la relation ManyToMany User <-> Role (table user_role).
     * C'est l'utilisateur qui "choisit"/reçoit ses rôles, d'où le choix de porter
     * ici la table de jointure. Volontairement nommée différemment du tableau legacy
     * $roles (string[]) utilisé par Symfony Security (UserInterface::getRoles()) : les
     * deux mécanismes cohabitent sans interférer, le second n'est pas modifié par cette
     * relation métier.
     *
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_role')]
    #[Assert\Count(
        max: self::MAX_ASSIGNED_ROLES,
        maxMessage: "Un utilisateur ne peut pas avoir plus de {{ limit }} rôles."
    )]
    #[Groups(['user:read', 'user:detail'])]
    private Collection $assignedRoles;

    #[ORM\ManyToMany(targetEntity: Permission::class)]
    #[ORM\JoinTable(name: 'user_permission_grant')]
    #[Groups(['user:detail'])]
    private Collection $grantedPermissions;

    #[ORM\ManyToMany(targetEntity: Permission::class)]
    #[ORM\JoinTable(name: 'user_permission_revoke')]
    #[Groups(['user:detail'])]
    private Collection $revokedPermissions;

    /**
     * Côté propriétaire de la relation ManyToMany User <-> Groupe (table user_groupe).
     * Contrairement à assignedRoles (max 2), aucune limite de nombre de groupes :
     * un utilisateur peut cumuler l'appartenance à plusieurs groupes organisationnels.
     *
     * @var Collection<int, Groupe>
     */
    #[ORM\ManyToMany(targetEntity: Groupe::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_groupe')]
    #[Groups(['user:read', 'user:detail'])]
    private Collection $assignedGroupes;

    /**
     * Côté inverse de la relation ManyToMany Project <-> User.
     * Le côté propriétaire (table project_user) est porté par Project. Non exposée par
     * défaut via les groupes de sérialisation pour ne pas alourdir les réponses /users
     * (elle est consultable via les endpoints /projects).
     *
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'users')]
    private Collection $projects;



    public function __construct()
    {
        $this->assignedRoles = new ArrayCollection();
        $this->assignedGroupes = new ArrayCollection();
        $this->grantedPermissions = new ArrayCollection();
        $this->revokedPermissions = new ArrayCollection();
        $this->projects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = $matricule;

        return $this;
    }

    public function getCni(): ?string
    {
        return $this->cni;
    }

    public function setCni(?string $cni): static
    {
        $this->cni = $cni;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** Alias pour le sérialiseur Symfony (PropertyAccess cherche getIsActive avant isIsActive). */
    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

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

    /**
     * @return Collection<int, Role>
     */
    public function getAssignedRoles(): Collection
    {
        return $this->assignedRoles;
    }

    /**
     * @return Collection<int, Groupe>
     */
    public function getAssignedGroupes(): Collection
    {
        return $this->assignedGroupes;
    }

    public function addAssignedGroupe(Groupe $groupe): static
    {
        if (!$this->assignedGroupes->contains($groupe)) {
            $this->assignedGroupes->add($groupe);
        }

        return $this;
    }

    public function removeAssignedGroupe(Groupe $groupe): static
    {
        $this->assignedGroupes->removeElement($groupe);

        return $this;
    }

    /**
     * @return Collection<int, Permission>
     */
    public function getGrantedPermissions(): Collection
    {
        return $this->grantedPermissions;
    }

    /**
     * @return Collection<int, Permission>
     */
    public function getRevokedPermissions(): Collection
    {
        return $this->revokedPermissions;
    }

    /**
     * Ajoute un rôle métier à l'utilisateur.
     *
     * @throws \DomainException si l'utilisateur possède déjà le nombre maximum de rôles
     *                          autorisé (voir User::MAX_ASSIGNED_ROLES). C'est un échec
     *                          rapide à destination du contrôleur, en complément de la
     *                          contrainte Assert\Count qui protège en dernier recours.
     */
    public function addAssignedRole(Role $role): static
    {
        if ($this->assignedRoles->contains($role)) {
            return $this;
        }

        if ($this->assignedRoles->count() >= self::MAX_ASSIGNED_ROLES) {
            throw new \DomainException(sprintf(
                "Un utilisateur ne peut pas avoir plus de %d rôles.",
                self::MAX_ASSIGNED_ROLES
            ));
        }

        $this->assignedRoles->add($role);

        return $this;
    }

    public function removeAssignedRole(Role $role): static
    {
        $this->assignedRoles->removeElement($role);

        return $this;
    }

    public function addGrantedPermission(Permission $permission): static
    {
        if (!$this->grantedPermissions->contains($permission)) {
            $this->grantedPermissions->add($permission);
        }

        return $this;
    }

    public function removeGrantedPermission(Permission $permission): static
    {
        $this->grantedPermissions->removeElement($permission);

        return $this;
    }

    public function addRevokedPermission(Permission $permission): static
    {
        if (!$this->revokedPermissions->contains($permission)) {
            $this->revokedPermissions->add($permission);
        }

        return $this;
    }

    public function removeRevokedPermission(Permission $permission): static
    {
        $this->revokedPermissions->removeElement($permission);

        return $this;
    }

    /**
     * Retourne l'ensemble des permissions effectives de l'utilisateur.
     *
     * Formule : permissions(rôles assignés) UNION permissions(groupes assignés)
     *           + permissions individuellement accordées
     *           - permissions individuellement retirées.
     *
     * Un rôle ou un groupe supprimé (isDelete) ou désactivé (isActive=false) ne
     * contribue plus aux permissions effectives, sans qu'aucune action explicite
     * de retrait ne soit nécessaire ailleurs : c'est ce filtrage qui traduit la
     * règle "supprimer un groupe retire ses privilèges à ses membres".
     *
     * @return Permission[]
     */
    public function getEffectivePermissions(): array
    {
        $permissionMap = [];

        foreach ($this->assignedRoles as $role) {
            if ($role->isDelete() || !$role->isActive()) {
                continue;
            }

            foreach ($role->getPermissions() as $permission) {
                if ($permission->isDelete() || !$permission->isActive()) {
                    continue;
                }

                $permissionId = $permission->getId();
                if (null !== $permissionId) {
                    $permissionMap[$permissionId] = $permission;
                }
            }
        }

        foreach ($this->assignedGroupes as $groupe) {
            if ($groupe->isDelete() || !$groupe->isActive()) {
                continue;
            }

            foreach ($groupe->getPermissions() as $permission) {
                if ($permission->isDelete() || !$permission->isActive()) {
                    continue;
                }

                $permissionId = $permission->getId();
                if (null !== $permissionId) {
                    $permissionMap[$permissionId] = $permission;
                }
            }
        }

        foreach ($this->grantedPermissions as $permission) {
            if ($permission->isDelete() || !$permission->isActive()) {
                continue;
            }

            $permissionId = $permission->getId();
            if (null !== $permissionId) {
                $permissionMap[$permissionId] = $permission;
            }
        }

        foreach ($this->revokedPermissions as $permission) {
            $permissionId = $permission->getId();
            if (null !== $permissionId) {
                unset($permissionMap[$permissionId]);
            }
        }

        return array_values($permissionMap);
    }

    /**
     * Décompose l'origine des permissions effectives, pour affichage détaillé
     * (ex. endpoint /profile) : héritées des rôles, héritées des groupes,
     * accordées individuellement, retirées individuellement, effectives.
     *
     * @return array{
     *     inheritedFromRoles: Permission[],
     *     inheritedFromGroupes: Permission[],
     *     granted: Permission[],
     *     revoked: Permission[],
     *     effective: Permission[]
     * }
     */
    public function getPermissionsBreakdown(): array
    {
        $fromRoles = [];
        foreach ($this->assignedRoles as $role) {
            if ($role->isDelete() || !$role->isActive()) {
                continue;
            }
            foreach ($role->getPermissions() as $permission) {
                if ($permission->isDelete() || !$permission->isActive()) {
                    continue;
                }
                $fromRoles[$permission->getId()] = $permission;
            }
        }

        $fromGroupes = [];
        foreach ($this->assignedGroupes as $groupe) {
            if ($groupe->isDelete() || !$groupe->isActive()) {
                continue;
            }
            foreach ($groupe->getPermissions() as $permission) {
                if ($permission->isDelete() || !$permission->isActive()) {
                    continue;
                }
                $fromGroupes[$permission->getId()] = $permission;
            }
        }

        $granted = [];
        foreach ($this->grantedPermissions as $permission) {
            if ($permission->isDelete() || !$permission->isActive()) {
                continue;
            }
            $granted[$permission->getId()] = $permission;
        }

        $revoked = [];
        foreach ($this->revokedPermissions as $permission) {
            $revoked[$permission->getId()] = $permission;
        }

        return [
            'inheritedFromRoles' => array_values($fromRoles),
            'inheritedFromGroupes' => array_values($fromGroupes),
            'granted' => array_values($granted),
            'revoked' => array_values($revoked),
            'effective' => $this->getEffectivePermissions(),
        ];
    }

    public function hasPermission(Permission $permission): bool
    {
        $permissionId = $permission->getId();
        if (null === $permissionId) {
            return false;
        }

        foreach ($this->getEffectivePermissions() as $effectivePermission) {
            if ($effectivePermission->getId() === $permissionId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->addUser($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            $project->removeUser($this);
        }

        return $this;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function getIsTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(bool $twoFactorEnabled): static
    {
        $this->twoFactorEnabled = $twoFactorEnabled;

        return $this;
    }

    public function getOtpCode(): ?string
    {
        return $this->otpCode;
    }

    public function setOtpCode(?string $otpCode): static
    {
        $this->otpCode = $otpCode;

        return $this;
    }

    public function getOtpExpiresAt(): ?\DateTimeInterface
    {
        return $this->otpExpiresAt;
    }

    public function setOtpExpiresAt(?\DateTimeInterface $otpExpiresAt): static
    {
        $this->otpExpiresAt = $otpExpiresAt;

        return $this;
    }
}
