<?php

namespace App\Entity\Cour;

use App\Entity\Core\Correspondant;
use App\Entity\Core\Service;
use App\Entity\Core\TypeCourrier;
use App\Entity\Core\User;
use App\Repository\Cour\CourrierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CourrierRepository::class)]
#[ORM\Table(name: 'cour_courrier')]
#[ORM\HasLifecycleCallbacks]
class Courrier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?int $id = null;

    
    #[ORM\Column(name: 'numero', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $numero = null;

    #[ORM\Column(name: 'reference', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $reference = null;

    #[ORM\Column(name: 'objet', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $objet = null;

    #[ORM\Column(name: 'commentaire', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $commentaire = null;

    #[ORM\Column(name: 'commentaire_public', type: Types::TEXT, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $commentairePublic = null;

    #[ORM\Column(name: 'commentaire_interne', type: Types::TEXT, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $commentaireInterne = null;

    #[ORM\Column(name: 'priorite', type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $priorite = null;

    #[ORM\Column(name: 'is_confidentiel', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private bool $isConfidentiel = false;

    #[ORM\Column(name: 'date_arrivee', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?\DateTimeInterface $dateArrivee = null;

    #[ORM\Column(name: 'date_enregistrement', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?\DateTimeInterface $dateEnregistrement = null;

    #[ORM\ManyToOne(inversedBy: 'courriers')]
    #[ORM\JoinColumn(name: 'id_provenance', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Groups(['Get:Courrier'])]
    private ?Correspondant $idProvenance = null;

    #[ORM\ManyToOne(inversedBy: 'courriers')]
    #[ORM\JoinColumn(name: 'id_service_traitant', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?Service $idServiceTraitant = null;

    #[ORM\ManyToOne(inversedBy: 'courriers')]
    #[ORM\JoinColumn(name: 'id_createur', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?User $idCreateur = null;

    #[ORM\Column(name: 'statut', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $statut = null;

    #[ORM\Column(name: 'is_geled', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private bool $isGeled = false;

    #[ORM\Column(name: 'document', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['Get:Courrier'])]
    private ?string $document = null;

    #[ORM\Column(name: 'bordereau_remise', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['Get:Courrier'])]
    private ?string $bordereauRemise = null;

    #[ORM\Column(name: 'date_remise_effective', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['Get:Courrier'])]
    private ?\DateTimeInterface $dateRemiseEffective = null;

    #[ORM\Column(name: 'date_cloture', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['Get:Courrier'])]
    private ?\DateTimeInterface $dateCloture = null;

    #[ORM\Column(name: 'telephone', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $telephone = null;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 150, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $email = null;

    #[ORM\Column(name: 'adresse', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $adresse = null;

    #[ORM\Column(name: 'civilite', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $civilite = null;

    #[ORM\Column(name: 'nom', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $nom = null;

    #[ORM\Column(name: 'matricule', type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $matricule = null;

    #[ORM\Column(name: 'type_transfert', type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $typeTransfert = null;

    #[ORM\Column(name: 'classe_courrier', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $classeCourrier = null;

    #[ORM\Column(name: 'categorie', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $categorie = null;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'is_archive', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private bool $isArchive = false;

    #[ORM\Column(name: 'statut_archive', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?string $statutArchive = null;

    #[ORM\Column(name: 'vider_par', type: Types::JSON, nullable: true)]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?array $viderPar = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'search', type: Types::STRING, length: 1000, nullable: true)]
    private ?string $search = null;

    #[ORM\Column(name: 'nombre_piece_jointe', type: Types::INTEGER, nullable: true, options: ['default' => 0])]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?int $nombrePieceJointe = 0;

    /** @var Collection<int, Transmission> */
    #[ORM\OneToMany(targetEntity: Transmission::class, mappedBy: 'idCourrier', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $transmissions;

    /** @var Collection<int, Reponse> */
    #[ORM\ManyToMany(targetEntity: Reponse::class, mappedBy: 'courriers')]
    private Collection $reponses;

    /** @var Collection<int, CourrierDepart> */
    #[ORM\OneToMany(targetEntity: CourrierDepart::class, mappedBy: 'idCourrier')]
    private Collection $courrierDeparts;

    #[ORM\ManyToOne(inversedBy: 'courriers')]
    #[ORM\JoinColumn(name: 'type_courrier_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Groups(['GetCollection:Courrier', 'Get:Courrier'])]
    private ?TypeCourrier $typeCourrier = null;

    public function __construct()
    {
        $this->transmissions = new ArrayCollection();
        $this->reponses = new ArrayCollection();
        $this->courrierDeparts = new ArrayCollection();
    }

    // ──────────────── GETTERS / SETTERS ────────────────

    public function getId(): ?int { return $this->id; }

    
    public function getNumero(): ?string { return $this->numero; }

    public function setNumero(?string $numero): static
    {
        $this->numero = trim($numero ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getReference(): ?string { return $this->reference; }

    public function setReference(?string $reference): static
    {
        $this->reference = trim($reference ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getObjet(): ?string { return $this->objet; }

    public function setObjet(?string $objet): static
    {
        $this->objet = trim($objet ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getCommentaire(): ?string { return $this->commentaire; }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = trim($commentaire ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getCommentairePublic(): ?string { return $this->commentairePublic; }

    public function setCommentairePublic(?string $commentairePublic): static
    {
        $this->commentairePublic = $commentairePublic;
        $this->updateSearchField();
        return $this;
    }

    public function getCommentaireInterne(): ?string { return $this->commentaireInterne; }

    public function setCommentaireInterne(?string $commentaireInterne): static
    {
        $this->commentaireInterne = $commentaireInterne;
        $this->updateSearchField();
        return $this;
    }

    public function getPriorite(): ?string { return $this->priorite; }

    public function setPriorite(?string $priorite): static
    {
        $this->priorite = trim($priorite ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function isConfidentiel(): bool { return $this->isConfidentiel; }

    public function setConfidentiel(bool $isConfidentiel): static
    {
        $this->isConfidentiel = $isConfidentiel;
        return $this;
    }

    public function getDateArrivee(): ?\DateTimeInterface { return $this->dateArrivee; }

    
    public function setDateArrivee(null|string|\DateTimeInterface $dateArrivee): self
    {
        if ($dateArrivee === null) {
            $this->dateArrivee = null;
        } elseif (is_string($dateArrivee)) {
            // Essayer plusieurs formats
            $formats = ['Y-m-d H:i:s', 'Y-m-d'];
            $convertedDate = false;

            foreach ($formats as $format) {
                $convertedDate = \DateTime::createFromFormat($format, $dateArrivee);
                if ($convertedDate) {
                    break; // Conversion réussie
                }
            }

            if (!$convertedDate) {
                throw new \InvalidArgumentException("Format de date invalide pour dateAchat : $dateArrivee. Formats acceptés : 'YYYY-MM-DD' ou 'YYYY-MM-DD HH:MM:SS'.");
            }

            $this->dateArrivee = $convertedDate;
        } elseif ($dateArrivee instanceof \DateTimeInterface) {
            $this->dateArrivee = new \DateTime($dateArrivee->format('Y-m-d H:i:s'));
        } else {
            throw new \InvalidArgumentException("Le type de dateAchat est invalide. Attendu : null, string ou \DateTimeInterface.");
        }

        $this->updateSearchField();
        return $this;
    }

    public function getDateEnregistrement(): ?\DateTimeInterface { return $this->dateEnregistrement; }

    
    
    public function setDateEnregistrement(null|string|\DateTimeInterface $dateEnregistrement): self
    {
        if ($dateEnregistrement === null) {
            $this->dateEnregistrement = null;
        } elseif (is_string($dateEnregistrement)) {
            // Essayer plusieurs formats
            $formats = ['Y-m-d H:i:s', 'Y-m-d'];
            $convertedDate = false;

            foreach ($formats as $format) {
                $convertedDate = \DateTime::createFromFormat($format, $dateEnregistrement);
                if ($convertedDate) {
                    break; // Conversion réussie
                }
            }

            if (!$convertedDate) {
                throw new \InvalidArgumentException("Format de date invalide pour dateAchat : $dateEnregistrement. Formats acceptés : 'YYYY-MM-DD' ou 'YYYY-MM-DD HH:MM:SS'.");
            }

            $this->dateEnregistrement = $convertedDate;
        } elseif ($dateEnregistrement instanceof \DateTimeInterface) {
            $this->dateEnregistrement = new \DateTime($dateEnregistrement->format('Y-m-d H:i:s'));
        } else {
            throw new \InvalidArgumentException("Le type de dateAchat est invalide. Attendu : null, string ou \DateTimeInterface.");
        }

        $this->updateSearchField();
        return $this;
    }

    public function getIdProvenance(): ?Correspondant { return $this->idProvenance; }

    public function setIdProvenance(?Correspondant $idProvenance): static
    {
        $this->idProvenance = $idProvenance;
        return $this;
    }

    public function getIdServiceTraitant(): ?Service { return $this->idServiceTraitant; }

    public function setIdServiceTraitant(?Service $idServiceTraitant): static
    {
        $this->idServiceTraitant = $idServiceTraitant;
        return $this;
    }

    public function getIdCreateur(): ?User { return $this->idCreateur; }

    public function setIdCreateur(?User $idCreateur): static
    {
        $this->idCreateur = $idCreateur;
        return $this;
    }

    public function getStatut(): ?string { return $this->statut; }

    public function setStatut(?string $statut): static
    {
        $this->statut = trim($statut ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function isGeled(): bool { return $this->isGeled; }

    public function setGeled(bool $isGeled): static
    {
        $this->isGeled = $isGeled;
        return $this;
    }

    public function getDocument(): ?string { return $this->document; }

    public function setDocument(?string $document): static
    {
        $this->document = trim($document ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getBordereauRemise(): ?string { return $this->bordereauRemise; }

    public function setBordereauRemise(?string $bordereauRemise): static
    {
        $this->bordereauRemise = trim($bordereauRemise ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getDateRemiseEffective(): ?\DateTimeInterface { return $this->dateRemiseEffective; }

    public function setDateRemiseEffective(?\DateTimeInterface $dateRemiseEffective): static
    {
        $this->dateRemiseEffective = $dateRemiseEffective;
        return $this;
    }

    public function getDateCloture(): ?\DateTimeInterface { return $this->dateCloture; }

    public function setDateCloture(?\DateTimeInterface $dateCloture): static
    {
        $this->dateCloture = $dateCloture;
        return $this;
    }

        public function getTelephone(): ?string { return $this->telephone; }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = trim($telephone ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getEmail(): ?string { return $this->email; }

    public function setEmail(?string $email): static
    {
        $this->email = trim($email ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getAdresse(): ?string { return $this->adresse; }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = trim($adresse ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getCivilite(): ?string { return $this->civilite; }

    public function setCivilite(?string $civilite): static
    {
        $this->civilite = trim($civilite ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getNom(): ?string { return $this->nom; }

    public function setNom(?string $nom): static
    {
        $this->nom = trim($nom ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getMatricule(): ?string { return $this->matricule; }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = trim($matricule ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getTypeTransfert(): ?string { return $this->typeTransfert; }

    public function setTypeTransfert(?string $typeTransfert): static
    {
        $this->typeTransfert = trim($typeTransfert ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getClasseCourrier(): ?string { return $this->classeCourrier; }

    public function setClasseCourrier(?string $classeCourrier): static
    {
        $this->classeCourrier = trim($classeCourrier ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function getCategorie(): ?string { return $this->categorie; }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = trim($categorie ?? '');
        $this->updateSearchField();
        return $this;
    }

    public function isDelete(): bool { return $this->isDelete; }

    public function setDelete(bool $isDelete): static
    {
        $this->isDelete = $isDelete;
        return $this;
    }

    public function isArchive(): bool { return $this->isArchive; }

    public function setArchive(bool $isArchive): static
    {
        $this->isArchive = $isArchive;
        return $this;
    }

    public function getStatutArchive(): ?string { return $this->statutArchive; }

    public function setStatutArchive(?string $statutArchive): static
    {
        $this->statutArchive = $statutArchive;
        return $this;
    }

    public function getViderPar(): ?array { return $this->viderPar; }

    public function setViderPar(?array $viderPar): static
    {
        $this->viderPar = $viderPar;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

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
        $this->search = implode(' <br/> ', array_filter([
            $this->numero,
            $this->reference,
            $this->objet,
            $this->commentaire,
            $this->priorite,
            $this->statut,
            $this->document,
            $this->bordereauRemise,
            $this->telephone,
            $this->email,
            $this->adresse,
            $this->civilite,
            $this->nom,
            $this->matricule,
            $this->typeTransfert,
            $this->classeCourrier,
            $this->categorie,
        ]));
    }

    public function getSearch(): ?string { return $this->search; }

    public function setSearch(?string $search): static
    {
        $this->search = $search;
        return $this;
    }

    public function getNombrePieceJointe(): ?int { return $this->nombrePieceJointe; }

    public function setNombrePieceJointe(?int $nombrePieceJointe): static
    {
        $this->nombrePieceJointe = $nombrePieceJointe;
        return $this;
    }

    // ──────────────── Relations ────────────────

    /** @return Collection<int, Transmission> */
    public function getTransmissions(): Collection { return $this->transmissions; }

    public function addTransmission(Transmission $transmission): static
    {
        if (!$this->transmissions->contains($transmission)) {
            $this->transmissions->add($transmission);
            $transmission->setIdCourrier($this);
        }
        return $this;
    }

    public function removeTransmission(Transmission $transmission): static
    {
        if ($this->transmissions->removeElement($transmission) && $transmission->getIdCourrier() === $this) {
            $transmission->setIdCourrier(null);
        }
        return $this;
    }

    /** @return Collection<int, Reponse> */
    public function getReponses(): Collection { return $this->reponses; }

    public function addReponse(Reponse $reponse): static
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses->add($reponse);
            $reponse->addCourrier($this);
        }
        return $this;
    }

    public function removeReponse(Reponse $reponse): static
    {
        if ($this->reponses->removeElement($reponse)) {
            $reponse->removeCourrier($this);
        }
        return $this;
    }


    /** @return Collection<int, CourrierDepart> */
    public function getCourrierDeparts(): Collection { return $this->courrierDeparts; }

    public function addCourrierDepart(CourrierDepart $courrierDepart): static
    {
        if (!$this->courrierDeparts->contains($courrierDepart)) {
            $this->courrierDeparts->add($courrierDepart);
            $courrierDepart->setIdCourrier($this);
        }
        return $this;
    }

    public function removeCourrierDepart(CourrierDepart $courrierDepart): static
    {
        if ($this->courrierDeparts->removeElement($courrierDepart) && $courrierDepart->getIdCourrier() === $this) {
            $courrierDepart->setIdCourrier(null);
        }
        return $this;
    }

    public function getTypeCourrier(): ?TypeCourrier
    {
        return $this->typeCourrier;
    }

    public function setTypeCourrier(?TypeCourrier $typeCourrier): static
    {
        $this->typeCourrier = $typeCourrier;

        return $this;
    }
}
