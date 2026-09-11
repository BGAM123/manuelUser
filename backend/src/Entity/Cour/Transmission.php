<?php

namespace App\Entity\Cour;

use App\Entity\Core\Service;
use App\Entity\Core\User;
use App\Repository\Cour\TransmissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TransmissionRepository::class)]
#[ORM\Table(name: 'cour_transmission')]
#[ORM\HasLifecycleCallbacks]
class Transmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, nullable: false)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'transmissions')]
    #[ORM\JoinColumn(name: 'id_courrier', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?Courrier $idCourrier = null;

    #[ORM\ManyToOne(inversedBy: 'transmissions')]
    #[ORM\JoinColumn(name: 'id_service_destinataire', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?Service $idServiceDestinataire = null;

    #[ORM\Column(name: 'structures_copie', type: Types::JSON, nullable: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?array $structuresCopie = null;

    #[ORM\Column(name: 'date_instruction', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?\DateTimeInterface $dateInstruction = null;

    // ✅ NOUVEAU CHAMP
    #[ORM\Column(name: 'date_reception', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?\DateTimeInterface $dateReception = null;

    #[ORM\Column(name: 'instruction', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?string $instruction = null;

    #[ORM\Column(name: 'delai_traitement', type: Types::INTEGER, nullable: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?int $delaiTraitement = null;

    #[ORM\Column(name: 'type_transfert', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?string $typeTransfert = null;

    #[ORM\ManyToOne(inversedBy: 'transmissions')]
    #[ORM\JoinColumn(name: 'id_emetteur', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?User $idEmetteur = null;

    #[ORM\Column(name: 'accuse_reception', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private bool $accuseReception = false;

    #[ORM\Column(name: 'traite_par', type: Types::JSON, nullable: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?array $traitePar = null;

    #[ORM\Column(name: 'statut', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?string $statut = null;

    #[ORM\Column(name: 'piece_jointe', type: Types::JSON, nullable: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?array $pieceJointe = null;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'is_archive', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private bool $isArchive = false;

    #[ORM\Column(name: 'isinstance', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private bool $isinstance = false;

    #[ORM\Column(name: 'statut_archive', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?string $statutArchive = null;

    #[ORM\Column(name: 'vider_par', type: Types::JSON, nullable: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?array $viderPar = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'search', type: Types::STRING, length: 1000, nullable: true)]
    private ?string $search = null;

    #[ORM\Column(name: 'nombre_piece_jointe', type: Types::INTEGER, nullable: true, options: ['default' => 0])]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private ?int $nombrePieceJointe = 0;

    #[ORM\OneToMany(targetEntity: PieceJointe::class, mappedBy: 'transmission', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['GetCollection:Transmission', 'Get:Transmission'])]
    private Collection $piecesJointes;

    public function __construct()
    {
        $this->piecesJointes = new ArrayCollection();
    }

    // ──────────────── GETTERS / SETTERS ────────────────

    public function getId(): ?int { return $this->id; }

    public function getIdCourrier(): ?Courrier { return $this->idCourrier; }
    public function setIdCourrier(?Courrier $idCourrier): static { $this->idCourrier = $idCourrier; return $this; }

    public function getIdServiceDestinataire(): ?Service { return $this->idServiceDestinataire; }
    public function setIdServiceDestinataire(?Service $idServiceDestinataire): static { $this->idServiceDestinataire = $idServiceDestinataire; return $this; }

    public function getStructuresCopie(): ?array { return $this->structuresCopie; }
    public function setStructuresCopie(?array $structuresCopie): static { $this->structuresCopie = $structuresCopie; return $this; }

    public function getDateInstruction(): ?\DateTimeInterface { return $this->dateInstruction; }
  
    public function setDateInstruction(null|string|\DateTimeInterface $dateInstruction): self
    {
        if ($dateInstruction === null) {
            $this->dateInstruction = null;
        } elseif (is_string($dateInstruction)) {
            $formats = ['Y-m-d H:i:s', 'Y-m-d'];
            $convertedDate = false;

            foreach ($formats as $format) {
                $convertedDate = \DateTime::createFromFormat($format, $dateInstruction);
                if ($convertedDate) {
                    break;
                }
            }

            if (!$convertedDate) {
                throw new \InvalidArgumentException("Format de date invalide pour dateInstruction : $dateInstruction. Formats acceptés : 'YYYY-MM-DD' ou 'YYYY-MM-DD HH:MM:SS'.");
            }

            $this->dateInstruction = $convertedDate;
        } elseif ($dateInstruction instanceof \DateTimeInterface) {
            $this->dateInstruction = new \DateTime($dateInstruction->format('Y-m-d H:i:s'));
        } else {
            throw new \InvalidArgumentException("Le type de dateInstruction est invalide. Attendu : null, string ou \DateTimeInterface.");
        }

        $this->updateSearchField();
        return $this;
    }

    // ✅ NOUVEAU GETTER/SETTER pour date_reception
    public function getDateReception(): ?\DateTimeInterface { return $this->dateReception; }
  
    public function setDateReception(null|string|\DateTimeInterface $dateReception): self
    {
        if ($dateReception === null) {
            $this->dateReception = null;
        } elseif (is_string($dateReception)) {
            $formats = ['Y-m-d H:i:s', 'Y-m-d'];
            $convertedDate = false;

            foreach ($formats as $format) {
                $convertedDate = \DateTime::createFromFormat($format, $dateReception);
                if ($convertedDate) {
                    break;
                }
            }

            if (!$convertedDate) {
                throw new \InvalidArgumentException("Format de date invalide pour dateReception : $dateReception. Formats acceptés : 'YYYY-MM-DD' ou 'YYYY-MM-DD HH:MM:SS'.");
            }

            $this->dateReception = $convertedDate;
        } elseif ($dateReception instanceof \DateTimeInterface) {
            $this->dateReception = new \DateTime($dateReception->format('Y-m-d H:i:s'));
        } else {
            throw new \InvalidArgumentException("Le type de dateReception est invalide. Attendu : null, string ou \DateTimeInterface.");
        }

        return $this;
    }

    public function getInstruction(): ?string { return $this->instruction; }
    public function setInstruction(?string $instruction): static {
        $this->instruction = trim($instruction ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getDelaiTraitement(): ?int { return $this->delaiTraitement; }
    public function setDelaiTraitement(?int $delaiTraitement): static { $this->delaiTraitement = $delaiTraitement; return $this; }

    public function getTypeTransfert(): ?string { return $this->typeTransfert; }
    public function setTypeTransfert(?string $typeTransfert): static {
        $this->typeTransfert = trim($typeTransfert ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getIdEmetteur(): ?User { return $this->idEmetteur; }
    public function setIdEmetteur(?User $idEmetteur): static { $this->idEmetteur = $idEmetteur; return $this; }

    public function isAccuseReception(): bool { return $this->accuseReception; }
    public function setAccuseReception(bool $accuseReception): static { $this->accuseReception = $accuseReception; return $this; }

    public function getTraitePar(): ?array { return $this->traitePar; }
    public function setTraitePar(?array $traitePar): static { $this->traitePar = $traitePar; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): static {
        $this->statut = trim($statut ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getPieceJointe(): ?array { return $this->pieceJointe; }
    public function setPieceJointe(?array $pieceJointe): static { $this->pieceJointe = $pieceJointe; return $this; }

    public function isDelete(): bool { return $this->isDelete; }
    public function setDelete(bool $isDelete): static { $this->isDelete = $isDelete; return $this; }

    public function isArchive(): bool { return $this->isArchive; }
    public function setArchive(bool $isArchive): static { $this->isArchive = $isArchive; return $this; }

    public function isinstance(): bool { return $this->isinstance; }
    public function setInstance(bool $isinstance): static { $this->isinstance = $isinstance; return $this; }

    public function getStatutArchive(): ?string { return $this->statutArchive; }
    public function setStatutArchive(?string $statutArchive): static { $this->statutArchive = $statutArchive; return $this; }

    public function getViderPar(): ?array { return $this->viderPar; }
    public function setViderPar(?array $viderPar): static { $this->viderPar = $viderPar; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

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
        $copieString = $this->structuresCopie ? implode(', ', $this->structuresCopie) : null;

        // Gestion sécurisée des relations pour éviter les erreurs EntityNotFoundException
        $serviceNom = null;
        if ($this->idServiceDestinataire) {
            try {
                $serviceNom = $this->idServiceDestinataire->getNom();
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                // Service supprimé ou introuvable, on ignore
                $serviceNom = null;
            }
        }

        $emetteurNom = null;
        if ($this->idEmetteur) {
            try {
                $emetteurNom = $this->idEmetteur->getLastName();
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                // Utilisateur supprimé ou introuvable, on ignore
                $emetteurNom = null;
            }
        }

        $this->search = implode(' <br/> ', array_filter([
            $this->instruction,
            $this->typeTransfert,
            $serviceNom,
            $emetteurNom,
            $copieString
        ]));
    }

    public function getSearch(): ?string { return $this->search; }
    public function setSearch(?string $search): static {
        $this->search = $search;
        return $this;
    }

    public function getNombrePieceJointe(): ?int { return $this->nombrePieceJointe; }
    public function setNombrePieceJointe(?int $nombrePieceJointe): static {
        $this->nombrePieceJointe = $nombrePieceJointe;
        return $this;
    }

    /**
     * @return Collection<int, PieceJointe>
     */
    public function getPiecesJointes(): Collection
    {
        return $this->piecesJointes;
    }

    public function addPiecesJointe(PieceJointe $piecesJointe): static
    {
        if (!$this->piecesJointes->contains($piecesJointe)) {
            $this->piecesJointes->add($piecesJointe);
            $piecesJointe->setTransmission($this);
        }

        return $this;
    }

    public function removePiecesJointe(PieceJointe $piecesJointe): static
    {
        if ($this->piecesJointes->removeElement($piecesJointe)) {
            // set the owning side to null (unless already changed)
            if ($piecesJointe->getTransmission() === $this) {
                $piecesJointe->setTransmission(null);
            }
        }

        return $this;
    }
}