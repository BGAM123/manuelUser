<?php

namespace App\Entity\Cour;

use App\Entity\Core\Correspondant;
use App\Entity\Core\User;
use App\Repository\Cour\CourrierDepartRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CourrierDepartRepository::class)]
#[ORM\Table(name: 'cour_courrier_depart')]
#[ORM\HasLifecycleCallbacks]
class CourrierDepart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'courrierDeparts')]
    #[ORM\JoinColumn(name: 'id_courrier', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?Courrier $idCourrier = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_courrier_interne', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?CourrierInterne $idCourrierInterne = null;

    #[ORM\Column(name: 'date_signature', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?\DateTimeInterface $dateSignature = null;

    #[ORM\Column(name: 'type_courrier', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?string $typeCourrier = null;

    #[ORM\Column(name: 'commentaire', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?string $commentaire = null;

    #[ORM\ManyToOne(inversedBy: 'courrierDeparts')]
    #[ORM\JoinColumn(name: 'id_signataire', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?User $idSignataire = null;

    #[ORM\ManyToOne(inversedBy: 'courrierDeparts')]
    #[ORM\JoinColumn(name: 'id_destinataire', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?Correspondant $destinataire = null;

    #[ORM\Column(name: 'numero_reference', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?string $numeroReference = null;

    #[ORM\Column(name: 'classe_courrier', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?string $classeCourrier = null;

    #[ORM\Column(name: 'categorie', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?string $categorie = null;

    #[ORM\Column(name: 'document', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?string $document = null;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?string $email = null;

    #[ORM\Column(name: 'numero_telephone', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?string $numeroTelephone = null;

    #[ORM\Column(name: 'numero_acte', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?string $numeroActe = null;

    // ✅ NOUVEAU CHAMP: Provenances en copie (JSON)
    #[ORM\Column(name: 'provenances_copie', type: Types::JSON, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?array $provenancesCopie = null;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'is_archive', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private bool $isArchive = false;

    #[ORM\Column(name: 'statut', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $statut ='Transmis';


    #[ORM\Column(name: 'statut_archive', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?string $statutArchive = null;

    #[ORM\Column(name: 'vider_par', type: Types::JSON, nullable: true)]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?array $viderPar = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'search', type: Types::STRING, length: 1000, nullable: true)]
    private ?string $search = null;

    #[ORM\Column(name: 'nombre_piece_jointe', type: Types::INTEGER, nullable: true, options: ['default' => 0])]
    #[Groups(['GetCollection:CourrierDepart', 'Get:CourrierDepart'])]
    private ?int $nombrePieceJointe = 0;

    // ──────────────── GETTERS / SETTERS ────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdCourrier(): ?Courrier
    {
        return $this->idCourrier;
    }

    public function setIdCourrier(?Courrier $idCourrier): static
    {
        $this->idCourrier = $idCourrier;
        return $this;
    }

    public function getIdCourrierInterne(): ?CourrierInterne
    {
        return $this->idCourrierInterne;
    }

    public function setIdCourrierInterne(?CourrierInterne $idCourrierInterne): static
    {
        $this->idCourrierInterne = $idCourrierInterne;
        return $this;
    }

    public function getStatut(): ?string { return $this->statut; }

    public function setStatut(?string $statut): static
    {
        $this->statut = trim($statut ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getDateSignature(): ?\DateTimeInterface
    {
        return $this->dateSignature;
    }

    public function setDateSignature(null|string|\DateTimeInterface $dateSignature): self
    {
        if ($dateSignature === null) {
            $this->dateSignature = null;
        } elseif (is_string($dateSignature)) {
            // Essayer plusieurs formats
            $formats = ['Y-m-d H:i:s', 'Y-m-d'];
            $convertedDate = false;

            foreach ($formats as $format) {
                $convertedDate = \DateTime::createFromFormat($format, $dateSignature);
                if ($convertedDate) {
                    break; // Conversion réussie
                }
            }

            if (!$convertedDate) {
                throw new \InvalidArgumentException("Format de date invalide pour dateSignature : $dateSignature. Formats acceptés : 'YYYY-MM-DD' ou 'YYYY-MM-DD HH:MM:SS'.");
            }

            $this->dateSignature = $convertedDate;
        } elseif ($dateSignature instanceof \DateTimeInterface) {
            $this->dateSignature = new \DateTime($dateSignature->format('Y-m-d H:i:s'));
        } else {
            throw new \InvalidArgumentException("Le type de dateSignature est invalide. Attendu : null, string ou \DateTimeInterface.");
        }

        $this->updateSearchField();
        return $this;
    }
  

    public function getTypeCourrier(): ?string
    {
        return $this->typeCourrier;
    }

    public function setTypeCourrier(?string $typeCourrier): static
    {
        $this->typeCourrier = trim($typeCourrier ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = trim($commentaire ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getIdSignataire(): ?User
    {
        return $this->idSignataire;
    }

    public function setIdSignataire(?User $idSignataire): static
    {
        $this->idSignataire = $idSignataire;
        return $this;
    }

    public function getDestinataire(): ?Correspondant
    {
        return $this->destinataire;
    }

    public function setDestinataire(?Correspondant $destinataire): static
    {
        $this->destinataire = $destinataire;
        return $this;
    }

    public function getNumeroReference(): ?string
    {
        return $this->numeroReference;
    }

    public function setNumeroReference(?string $numeroReference): static
    {
        $this->numeroReference = trim($numeroReference ?? '');
        $this->updateSearchField();
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

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = trim($categorie ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getDocument(): ?string
    {
        return $this->document;
    }

    public function setDocument(?string $document): static
    {
        $this->document = trim($document ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = trim($email ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getNumeroTelephone(): ?string
    {
        return $this->numeroTelephone;
    }

    public function setNumeroTelephone(?string $numeroTelephone): static
    {
        $this->numeroTelephone = trim($numeroTelephone ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getNumeroActe(): ?string
    {
        return $this->numeroActe;
    }

    public function setNumeroActe(?string $numeroActe): static
    {
        $this->numeroActe = trim($numeroActe ?? '');
        $this->updateSearchField();
        return $this;
    }

    // ✅ NOUVEAU GETTER/SETTER pour provenancesCopie
    public function getProvenancesCopie(): ?array
    {
        return $this->provenancesCopie;
    }

    public function setProvenancesCopie(?array $provenancesCopie): static
    {
        $this->provenancesCopie = $provenancesCopie;
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

    public function isArchive(): bool
    {
        return $this->isArchive;
    }

    public function setArchive(bool $isArchive): static
    {
        $this->isArchive = $isArchive;
        return $this;
    }

    public function getStatutArchive(): ?string
    {
        return $this->statutArchive;
    }

    public function setStatutArchive(?string $statutArchive): static
    {
        $this->statutArchive = $statutArchive;
        return $this;
    }

    public function getViderPar(): ?array
    {
        return $this->viderPar;
    }

    public function setViderPar(?array $viderPar): static
    {
        $this->viderPar = $viderPar;
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

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateSearchField(): void
    {
        // ✅ Inclure provenancesCopie dans le champ search
        $copieString = $this->provenancesCopie ? implode(', ', array_map(fn($item) => is_array($item) ? ($item['nom'] ?? '') : $item, $this->provenancesCopie)) : null;

        $this->search = implode(' <br/> ', array_filter([
            $this->typeCourrier,
            $this->commentaire,
            $this->numeroReference,
            $this->classeCourrier,
            $this->categorie,
            $this->document,
            $this->email,
            $this->numeroTelephone,
            $this->numeroActe,
            $copieString,
            $this->destinataire?->getNom(),
            $this->idSignataire?->getLastname(),
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
