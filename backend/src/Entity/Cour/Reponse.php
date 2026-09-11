<?php

namespace App\Entity\Cour;

use App\Entity\Core\Service;
use App\Entity\Core\TypeReponse;
use App\Entity\Core\User;
use App\Repository\Cour\ReponseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ReponseRepository::class)]
#[ORM\Table(name: 'cour_reponse')]
#[ORM\HasLifecycleCallbacks]
class Reponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?int $id = null;

    /** @var Collection<int, Courrier> */
    #[ORM\ManyToMany(targetEntity: Courrier::class, inversedBy: 'reponses')]
    #[ORM\JoinTable(
        name: 'cour_reponse_courrier',
        joinColumns: [new ORM\JoinColumn(name: 'reponse_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'courrier_id', referencedColumnName: 'id')]
    )]
    private Collection $courriers;

    #[ORM\ManyToOne(inversedBy: 'reponses')]
    #[ORM\JoinColumn(name: 'id_type_reponse', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?TypeReponse $typeReponse = null;

    #[ORM\Column(name: 'types_courrier_ids', type: Types::JSON, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?array $typesCourrierIds = null;

    #[ORM\Column(name: 'id_transmission', type: Types::JSON, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?array $idTransmission = null;

    #[ORM\Column(name: 'id_reponses', type: Types::JSON, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?array $idReponses = null;

    #[ORM\Column(name: 'id_courrier_interne', type: Types::JSON, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?array $idCourrierInternes = null;

    #[ORM\Column(name: 'objet', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?string $objet = null;

    #[ORM\Column(name: 'commentaire_public', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?string $commentairePublic = null;

    #[ORM\Column(name: 'commentaire_interne', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?string $commentaireInterne = null;

    #[ORM\Column(name: 'priorite', type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?string $priorite = null;

    #[ORM\Column(name: 'statut', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?string $statut = null;

    #[ORM\ManyToOne(inversedBy: 'reponses')]
    #[ORM\JoinColumn(name: 'id_service_destinataire', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?Service $idServiceDestinataire = null;

    #[ORM\ManyToOne(inversedBy: 'reponses')]
    #[ORM\JoinColumn(name: 'id_redacteur', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?User $idRedacteur = null;

    #[ORM\Column(name: 'date_reponse', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?\DateTimeInterface $dateReponse = null;

    #[ORM\Column(name: 'classe_courrier', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?string $classeCourrier = null;

    #[ORM\Column(name: 'type_transmission', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?string $typeTransmission = null;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'search', type: Types::STRING, length: 1000, nullable: true)]
    private ?string $search = null;

    #[ORM\Column(name: 'nombre_piece_jointe', type: Types::INTEGER, nullable: true, options: ['default' => 0])]
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    private ?int $nombrePieceJointe = 0;

    #[ORM\Column(name: 'accuse_reception', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private bool $accuseReception = false;

    #[ORM\Column(name: 'isinstance', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private bool $isinstance = false;

    #[ORM\Column(name: 'is_geled', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private bool $isGeled = false;

    public function isGeled(): bool { return $this->isGeled; }

    public function setGeled(bool $isGeled): static
    {
        $this->isGeled = $isGeled;
        return $this;
    }

    public function isinstance(): bool { return $this->isinstance; }
    public function setInstance(bool $isinstance): static { $this->isinstance = $isinstance; return $this; }

    public function isAccuseReception(): bool { return $this->accuseReception; }
    public function setAccuseReception(bool $accuseReception): static { $this->accuseReception = $accuseReception; return $this; }

    public function __construct()
    {
        $this->courriers = new ArrayCollection();
    }

    // ──────────────── GETTERS / SETTERS ────────────────

    public function getId(): ?int
    {
        return $this->id;
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
        }
        return $this;
    }

    public function removeCourrier(Courrier $courrier): static
    {
        $this->courriers->removeElement($courrier);
        return $this;
    }

    /**
     * Retourne les IDs des courriers
     * @return array<int>
     */
    #[Groups(['GetCollection:Reponse', 'Get:Reponse'])]
    public function getCourrierIds(): array
    {
        return $this->courriers->map(fn($c) => $c->getId())->toArray();
    }

    public function getTypeReponse(): ?TypeReponse
    {
        return $this->typeReponse;
    }

    public function setTypeReponse(?TypeReponse $typeReponse): static
    {
        $this->typeReponse = $typeReponse;
        $this->updateSearchField();
        return $this;
    }

    public function getTypesCourrierIds(): ?array
    {
        return $this->typesCourrierIds;
    }

    public function setTypesCourrierIds(?array $typesCourrierIds): static
    {
        $this->typesCourrierIds = $typesCourrierIds;
        return $this;
    }

    public function getIdTransmission(): ?array
    {
        return $this->idTransmission;
    }

    public function setIdTransmission(?array $idTransmission): static
    {
        $this->idTransmission = $idTransmission;
        return $this;
    }

    public function getIdReponses(): ?array
    {
        return $this->idReponses;
    }

    public function setIdReponses(?array $idReponses): static
    {
        $this->idReponses = $idReponses;
        return $this;
    }

    public function getIdCourrierInternes(): ?array
    {
        return $this->idCourrierInternes;
    }

    public function setIdCourrierInternes(?array $idCourrierInternes): static
    {
        $this->idCourrierInternes = $idCourrierInternes;
        return $this;
    }

    public function getObjet(): ?string
    {
        return $this->objet;
    }

    public function setObjet(?string $objet): static
    {
        $this->objet = trim($objet ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getCommentairePublic(): ?string
    {
        return $this->commentairePublic;
    }

    public function setCommentairePublic(?string $commentairePublic): static
    {
        $this->commentairePublic = trim($commentairePublic ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getCommentaireInterne(): ?string
    {
        return $this->commentaireInterne;
    }

    public function setCommentaireInterne(?string $commentaireInterne): static
    {
        $this->commentaireInterne = trim($commentaireInterne ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getPriorite(): ?string
    {
        return $this->priorite;
    }

    public function setPriorite(?string $priorite): static
    {
        $this->priorite = trim($priorite ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = trim($statut ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getIdServiceDestinataire(): ?Service
    {
        return $this->idServiceDestinataire;
    }

    public function setIdServiceDestinataire(?Service $idServiceDestinataire): static
    {
        $this->idServiceDestinataire = $idServiceDestinataire;
        return $this;
    }

    public function getIdRedacteur(): ?User
    {
        return $this->idRedacteur;
    }

    public function setIdRedacteur(?User $idRedacteur): static
    {
        $this->idRedacteur = $idRedacteur;
        return $this;
    }

    public function getDateReponse(): ?\DateTimeInterface
    {
        return $this->dateReponse;
    }

    public function setDateReponse(?\DateTimeInterface $dateReponse): static
    {
        $this->dateReponse = $dateReponse;
        return $this;
    }

    public function getClasseCourrier(): ?string
    {
        return $this->classeCourrier;
    }

    public function setClasseCourrier(?string $classeCourrier): static
    {
        $this->classeCourrier = trim($classeCourrier ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getTypeTransmission(): ?string
    {
        return $this->typeTransmission;
    }

    public function setTypeTransmission(?string $typeTransmission): static
    {
        $this->typeTransmission = trim($typeTransmission ?? '');
        $this->updateSearchField();
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

    public function refreshStatut(): static
    {
        if ($this->isGeled) {
            $this->statut = 'Classé';
        } elseif ($this->isinstance) {
            $this->statut = 'Instancié';
        } elseif ($this->accuseReception) {
            $this->statut = 'Reçu';
        } else {
            $this->statut = 'Transmis';
        }

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateStatut(): void
    {
        $this->refreshStatut();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateSearchField(): void
    {
        $this->refreshStatut();
        $this->search = implode(' <br/> ', array_filter([
            $this->typeReponse?->getNom(),
            $this->objet,
            $this->commentairePublic,
            $this->commentaireInterne,
            $this->priorite,
            $this->statut,
            $this->classeCourrier,
            $this->typeTransmission,
            $this->idRedacteur?->getLastname(),
            $this->idServiceDestinataire?->getNom(),
        ]));
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

    public function getNombrePieceJointe(): ?int
    {
        return $this->nombrePieceJointe;
    }

    public function setNombrePieceJointe(?int $nombrePieceJointe): static
    {
        $this->nombrePieceJointe = $nombrePieceJointe;
        return $this;
    }
}
