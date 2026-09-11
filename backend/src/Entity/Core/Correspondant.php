<?php

namespace App\Entity\Core;

use App\Entity\Cour\Courrier;
use App\Entity\Cour\CourrierDepart;
use App\Repository\Core\CorrespondantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CorrespondantRepository::class)]
#[ORM\Table(name: 'core_correspondant')]
#[ORM\HasLifecycleCallbacks]
class Correspondant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        name: 'id',
        type: Types::INTEGER,
        nullable: false
    )]
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    private ?int $id = null;

    #[ORM\Column(
        name: 'nom',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    private ?string $nom = null;

    #[ORM\Column(
        name: 'adresse',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    private ?string $adresse = null;

    #[ORM\Column(
        name: 'telephone',
        type: Types::STRING,
        length: 50,
        nullable: true
    )]
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    private ?string $telephone = null;

    #[ORM\Column(
        name: 'email',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    private ?string $email = null;

    /** @var Collection<int, CategorieCorrespondant> */
    #[ORM\ManyToMany(targetEntity: CategorieCorrespondant::class, inversedBy: 'correspondants')]
    #[ORM\JoinTable(
        name: 'core_correspondant_categorie',
        joinColumns: [new ORM\JoinColumn(name: 'correspondant_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'categorie_id', referencedColumnName: 'id')]
    )]
    private Collection $categories;

    #[ORM\Column(
        name: 'type',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    private ?string $type = null;

    #[ORM\Column(
        name: 'civilite',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    private ?string $civilite = null;

    #[ORM\Column(
        name: 'matricule',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    private ?string $matricule = null;

    #[ORM\Column(
        name: 'is_delete',
        type: Types::BOOLEAN,
        nullable: false,
        options: ['default' => false]
    )]
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    private bool $isDelete = false;

    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        nullable: false,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        nullable: false,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(
        name: 'search',
        type: Types::STRING,
        length: 1000,
        nullable: true
    )]
    private ?string $search = null;

    /** @var Collection<int, Courrier> */
    #[ORM\OneToMany(targetEntity: Courrier::class, mappedBy: 'idProvenance')]
    private Collection $courriers;

    /** @var Collection<int, CourrierDepart> */
    #[ORM\OneToMany(targetEntity: CourrierDepart::class, mappedBy: 'destinataire')]
    private Collection $courrierDeparts;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'idCorrespondant')]
    private Collection $users;

    public function __construct()
    {
        $this->courriers = new ArrayCollection();
        $this->courrierDeparts = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->categories = new ArrayCollection();
    }

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
        $this->nom = $nom;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    // ✅ MODIFIÉ : Accepte les chaînes vides sans validation
    public function setTelephone(?string $telephone): static
    {
        // Convertir les chaînes vides en null
        $this->telephone = ($telephone === '') ? null : $telephone;
        
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    // ✅ MODIFIÉ : Accepte les chaînes vides sans validation
    public function setEmail(?string $email): static
    {
        // Convertir les chaînes vides en null
        $this->email = ($email === '') ? null : $email;
        
        return $this;
    }

    /** @return Collection<int, CategorieCorrespondant> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategorie(CategorieCorrespondant $categorie): static
    {
        if (!$this->categories->contains($categorie)) {
            $this->categories->add($categorie);
        }
        return $this;
    }

    public function removeCategorie(CategorieCorrespondant $categorie): static
    {
        $this->categories->removeElement($categorie);
        return $this;
    }

    /**
     * Retourne les IDs des catégories (pour la sérialisation)
     * @return array<int>
     */
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    public function getCategorieIds(): array
    {
        return $this->categories->map(fn($cat) => $cat->getId())->toArray();
    }

    /**
     * Retourne les noms des catégories (pour la sérialisation)
     * @return array<string>
     */
    #[Groups(['GetCollection:Correspondant', 'Get:Correspondant'])]
    public function getCategorieNoms(): array
    {
        return $this->categories->map(fn($cat) => $cat->getNom())->toArray();
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
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

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = $matricule;
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
    public function updateTimestampsAndSearch(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        $this->updatedAt = new \DateTimeImmutable();

        $this->search = implode(' <br/> ', array_filter([
            $this->nom ?? '',
            $this->adresse ?? '',
            $this->telephone ?? '',
            $this->email ?? '',
            $this->type ?? '',
            $this->civilite ?? '',
            $this->matricule ?? '',
        ], static fn ($v) => $v !== ''));
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

    /** @return Collection<int, Courrier> */
    public function getCourriers(): Collection
    {
        return $this->courriers;
    }

    public function addCourrier(Courrier $courrier): static
    {
        if (!$this->courriers->contains($courrier)) {
            $this->courriers->add($courrier);
            $courrier->setIdProvenance($this);
        }
        return $this;
    }

    public function removeCourrier(Courrier $courrier): static
    {
        if ($this->courriers->removeElement($courrier)) {
            if ($courrier->getIdProvenance() === $this) {
                $courrier->setIdProvenance(null);
            }
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
            $courrierDepart->setDestinataire($this);
        }
        return $this;
    }

    public function removeCourrierDepart(CourrierDepart $courrierDepart): static
    {
        if ($this->courrierDeparts->removeElement($courrierDepart)) {
            if ($courrierDepart->getDestinataire() === $this) {
                $courrierDepart->setDestinataire(null);
            }
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
            $user->setIdCorrespondant($this);
        }
        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            if ($user->getIdCorrespondant() === $this) {
                $user->setIdCorrespondant(null);
            }
        }
        return $this;
    }
}