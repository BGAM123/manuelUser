<?php

// src/Entity/Asset.php

namespace App\Entity;

use App\Repository\AssetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Bien patrimonial (Asset).
 * Tous les champs métier sont facultatifs.
 */
#[ORM\Entity(repositoryClass: AssetRepository::class)]
#[ORM\Table(name: 'asset')]
#[UniqueEntity(fields: ['reference'], message: 'Cette référence de bien est déjà utilisée.')]
class Asset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?string $reference = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?string $code = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?string $lien = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?string $numeroSerie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['asset:detail'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?\DateTimeImmutable $dateAcquisition = null;

    /**
     * Exercice patrimonial d'enregistrement du bien (année). Déterminé automatiquement
     * à la création à partir de l'année courante et non modifiable ensuite : il ne
     * représente pas l'exercice courant du patrimoine mais l'exercice d'origine du bien.
     */
    // #[ORM\Column]
    // #[Assert\NotNull(message: "L'exercice est obligatoire.")]
    // #[Assert\Range(min: 2000, max: 2100, notInRangeMessage: "L'exercice doit être une année valide (entre {{ min }} et {{ max }}).")]
    // #[Groups(['asset:list', 'asset:detail'])]
    // private ?int $exercice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?string $valeur = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?string $prixMercurial = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['asset:detail'])]
    private ?string $modeAcquisition = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?string $statut = null;

    /**
     * ✅ Relation ManyToMany avec Input
     * @var Collection<int, Input>
     */
    #[ORM\ManyToMany(targetEntity: Input::class, inversedBy: 'assets')]
    #[ORM\JoinTable(name: 'asset_input')]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'input_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['asset:detail'])]
    private Collection $inputs;

    /**
     * Quantité en stock disponible pour ce bien. Laissé à null pour un bien physique
     * unique (traçabilité par référence/numéro de série) ; renseigné pour un bien de
     * type stock/consomptible (fournitures, matériel de réunion, etc.), auquel cas les
     * sorties via BSP décrémentent automatiquement cette valeur.
     */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'La quantité en stock doit être positive ou nulle.')]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?int $quantiteStock = null;

    // --- Fournisseur (embarqué) ---

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['asset:detail'])]
    private ?string $typeFournisseur = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['asset:detail'])]
    private ?string $fournisseurNom = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: "L'email du fournisseur n'est pas valide.")]
    #[Assert\Length(max: 255)]
    #[Groups(['asset:detail'])]
    private ?string $fournisseurEmail = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['asset:detail'])]
    private ?string $fournisseurTelephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['asset:detail'])]
    private ?string $fournisseurAdresse = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['asset:detail'])]
    private ?string $fournisseurVille = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['asset:detail'])]
    private ?string $fournisseurPays = null;

    /**
     * ✅ Nouveau champ : Valeur initiale à la création du bien
     * Permet de conserver la valeur d'origine pour les comparaisons
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['asset:list', 'asset:detail'])]
    private ?string $valeurInitiale = null;

    /**
     * ✅ Nouveau champ : Activer l'amortissement
     * True = l'amortissement est activé pour ce bien
     * False = l'amortissement est désactivé
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['asset:list', 'asset:detail'])]
    private bool $activeAmortissement = true;

    /**
     * ✅ Nouveau champ : Activer la réévaluation
     * True = la réévaluation est activée pour ce bien
     * False = la réévaluation est désactivée
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['asset:list', 'asset:detail'])]
    private bool $activeReevaluation = false;

    /**
     * ✅ Nouveau champ : Activer la dépréciation
     * True = la dépréciation est activée pour ce bien
     * False = la dépréciation est désactivée
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['asset:list', 'asset:detail'])]
    private bool $activeDepreciation = false;

    #[ORM\Column]
    #[Groups(['asset:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['asset:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Utilisateur ayant créé le bien
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset:detail'])]
    private ?User $createdBy = null;

    /**
     * Utilisateur ayant effectué la dernière modification
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset:detail'])]
    private ?User $updatedBy = null;


    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['asset:list', 'asset:detail'])]
    private bool $isDelete = false;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class)]
    #[ORM\JoinTable(name: 'asset_category')]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['asset:list', 'asset:detail'])]
    private Collection $categories;


    /**
     * ✅ Relation ManyToMany avec Champ
     * @var Collection<int, Champ>
     */
    #[ORM\ManyToMany(targetEntity: Champ::class, inversedBy: 'assets')]
    #[ORM\JoinTable(name: 'asset_champ')]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'champ_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['asset:detail'])]
    private Collection $champs;

    /**
     * @var Collection<int, AssetType>
     */
    #[ORM\ManyToMany(targetEntity: AssetType::class)]
    #[ORM\JoinTable(name: 'asset_asset_type')]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'asset_type_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['asset:list', 'asset:detail'])]
    private Collection $assetTypes;

    /**
     * @var Collection<int, AssetSubType>
     */
    #[ORM\ManyToMany(targetEntity: AssetSubType::class)]
    #[ORM\JoinTable(name: 'asset_asset_sub_type')]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'asset_sub_type_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['asset:list', 'asset:detail'])]
    private Collection $assetSubTypes;

    /**
     * @var Collection<int, EtatBien>
     */
    #[ORM\ManyToMany(targetEntity: EtatBien::class)]
    #[ORM\JoinTable(name: 'asset_etat_bien')]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'etat_bien_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['asset:list', 'asset:detail'])]
    private Collection $etatBiens;

    /**
     * @var Collection<int, Service>
     */
    #[ORM\ManyToMany(targetEntity: Service::class)]
    #[ORM\JoinTable(name: 'asset_service')]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'service_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['asset:list', 'asset:detail'])]
    private Collection $services;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class)]
    #[ORM\JoinTable(name: 'asset_project')]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'project_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['asset:detail'])]
    private Collection $projects;

    /**
     * @var Collection<int, PieceJointe>
     */
    #[ORM\ManyToMany(targetEntity: PieceJointe::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'asset_piece_jointe')]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'piece_jointe_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['asset:detail'])]
    private Collection $piecesJointes;

    /**
     * @var Collection<int, AssetAssignment>
     */
    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: AssetAssignment::class)]
    #[Groups(['asset:detail'])]
    private Collection $assignments;

    /**
     * @var Collection<int, AssetLocation>
     */
    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: AssetLocation::class)]
    #[Groups(['asset:detail'])]
    private Collection $assetLocations;

    /**
     * @var Collection<int, AssetReformRequest>
     */
    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: AssetReformRequest::class)]
    #[Groups(['asset:detail'])]
    private Collection $reformRequests;

    /**
     * @var Collection<int, AssetSecurity>
     */
    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: AssetSecurity::class)]
    #[Groups(['asset:detail'])]
    private Collection $assetSecurities;

    /**
     * @var Collection<int, AssetMaintenance>
     */
    #[ORM\ManyToMany(targetEntity: AssetMaintenance::class, mappedBy: 'assets')]
    #[Groups(['asset:detail'])]
    private Collection $maintenances;

    /**
     * @var Collection<int, AssetReevaluation>
     */
    #[ORM\ManyToMany(targetEntity: AssetReevaluation::class, mappedBy: 'assets')]
    #[Groups(['asset:detail'])]
    private Collection $reevaluations;

    /**
     * @var Collection<int, AssetDepreciation>
     */
    #[ORM\ManyToMany(targetEntity: AssetDepreciation::class, mappedBy: 'assets')]
    #[Groups(['asset:detail'])]
    private Collection $depreciations;

    #[ORM\OneToOne(mappedBy: 'asset')]
    #[Groups(['asset:detail'])]
    private ?AssetExit $sortie = null;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->assetTypes = new ArrayCollection();
        $this->assetSubTypes = new ArrayCollection();
        $this->etatBiens = new ArrayCollection();
        $this->services = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->piecesJointes = new ArrayCollection();
        $this->assignments = new ArrayCollection();
        $this->assetLocations = new ArrayCollection();
        $this->reformRequests = new ArrayCollection();
        $this->assetSecurities = new ArrayCollection();
        $this->maintenances = new ArrayCollection();
        $this->reevaluations = new ArrayCollection();
        $this->depreciations = new ArrayCollection();
        $this->champs = new ArrayCollection(); // ✅ Ajouter cette ligne
        $this->inputs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }



    // ==================== NOUVEAUX GETTERS & SETTERS ====================

    /**
     * ✅ Getter pour la valeur initiale
     */
    public function getValeurInitiale(): ?string
    {
        return $this->valeurInitiale;
    }

    /**
     * ✅ Setter pour la valeur initiale
     */
    public function setValeurInitiale(?string $valeurInitiale): static
    {
        $this->valeurInitiale = $valeurInitiale;
        return $this;
    }

    /**
     * ✅ Getter pour l'activation de l'amortissement
     */
    public function isActiveAmortissement(): bool
    {
        return $this->activeAmortissement;
    }

    /**
     * ✅ Setter pour l'activation de l'amortissement
     */
    public function setActiveAmortissement(bool $activeAmortissement): static
    {
        $this->activeAmortissement = $activeAmortissement;
        return $this;
    }

    /**
     * ✅ Getter pour l'activation de la réévaluation
     */
    public function isActiveReevaluation(): bool
    {
        return $this->activeReevaluation;
    }

    /**
     * ✅ Setter pour l'activation de la réévaluation
     */
    public function setActiveReevaluation(bool $activeReevaluation): static
    {
        $this->activeReevaluation = $activeReevaluation;
        return $this;
    }

    /**
     * ✅ Getter pour l'activation de la dépréciation
     */
    public function isActiveDepreciation(): bool
    {
        return $this->activeDepreciation;
    }

    /**
     * ✅ Setter pour l'activation de la dépréciation
     */
    public function setActiveDepreciation(bool $activeDepreciation): static
    {
        $this->activeDepreciation = $activeDepreciation;
        return $this;
    }


    /**
     * ✅ Getters et setters pour la relation avec Input
     * @return Collection<int, Input>
     */
    public function getInputs(): Collection
    {
        return $this->inputs;
    }

    public function addInput(Input $input): static
    {
        if (!$this->inputs->contains($input)) {
            $this->inputs->add($input);
        }
        return $this;
    }

    public function removeInput(Input $input): static
    {
        $this->inputs->removeElement($input);
        return $this;
    }

    public function syncInputs(array $inputs): static
    {
        foreach ($this->inputs->toArray() as $existing) {
            if (!in_array($existing, $inputs, true)) {
                $this->removeInput($existing);
            }
        }
        foreach ($inputs as $input) {
            $this->addInput($input);
        }
        return $this;
    }


    public function getLien(): ?string
    {
        return $this->lien;
    }

    public function setLien(?string $lien): static
    {
        $this->lien = $lien;

        return $this;
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

    public function getNumeroSerie(): ?string
    {
        return $this->numeroSerie;
    }

    public function setNumeroSerie(?string $numeroSerie): static
    {
        $this->numeroSerie = $numeroSerie;

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

    public function getDateAcquisition(): ?\DateTimeImmutable
    {
        return $this->dateAcquisition;
    }

    public function setDateAcquisition(?\DateTimeImmutable $dateAcquisition): static
    {
        $this->dateAcquisition = $dateAcquisition;

        return $this;
    }

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(?string $valeur): static
    {
        $this->valeur = $valeur;

        return $this;
    }

    public function getPrixMercurial(): ?string
    {
        return $this->prixMercurial;
    }

    public function setPrixMercurial(?string $prixMercurial): static
    {
        $this->prixMercurial = $prixMercurial;

        return $this;
    }

    public function getModeAcquisition(): ?string
    {
        return $this->modeAcquisition;
    }

    public function setModeAcquisition(?string $modeAcquisition): static
    {
        $this->modeAcquisition = $modeAcquisition;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getQuantiteStock(): ?int
    {
        return $this->quantiteStock;
    }

    public function setQuantiteStock(?int $quantiteStock): static
    {
        $this->quantiteStock = $quantiteStock;

        return $this;
    }

    public function getTypeFournisseur(): ?string
    {
        return $this->typeFournisseur;
    }

    public function setTypeFournisseur(?string $typeFournisseur): static
    {
        $this->typeFournisseur = $typeFournisseur;

        return $this;
    }

    public function getFournisseurNom(): ?string
    {
        return $this->fournisseurNom;
    }

    public function setFournisseurNom(?string $fournisseurNom): static
    {
        $this->fournisseurNom = $fournisseurNom;

        return $this;
    }

    public function getFournisseurEmail(): ?string
    {
        return $this->fournisseurEmail;
    }

    public function setFournisseurEmail(?string $fournisseurEmail): static
    {
        $this->fournisseurEmail = $fournisseurEmail;

        return $this;
    }

    public function getFournisseurTelephone(): ?string
    {
        return $this->fournisseurTelephone;
    }

    public function setFournisseurTelephone(?string $fournisseurTelephone): static
    {
        $this->fournisseurTelephone = $fournisseurTelephone;

        return $this;
    }

    public function getFournisseurAdresse(): ?string
    {
        return $this->fournisseurAdresse;
    }

    public function setFournisseurAdresse(?string $fournisseurAdresse): static
    {
        $this->fournisseurAdresse = $fournisseurAdresse;

        return $this;
    }

    public function getFournisseurVille(): ?string
    {
        return $this->fournisseurVille;
    }

    public function setFournisseurVille(?string $fournisseurVille): static
    {
        $this->fournisseurVille = $fournisseurVille;

        return $this;
    }

    public function getFournisseurPays(): ?string
    {
        return $this->fournisseurPays;
    }

    public function setFournisseurPays(?string $fournisseurPays): static
    {
        $this->fournisseurPays = $fournisseurPays;

        return $this;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

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
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    /**
     * @param Category[] $categories
     */
    public function syncCategories(array $categories): static
    {
        foreach ($this->categories->toArray() as $existing) {
            if (!in_array($existing, $categories, true)) {
                $this->removeCategory($existing);
            }
        }
        foreach ($categories as $category) {
            $this->addCategory($category);
        }

        return $this;
    }

    /**
     * @return Collection<int, AssetType>
     */
    public function getAssetTypes(): Collection
    {
        return $this->assetTypes;
    }

    public function addAssetType(AssetType $assetType): static
    {
        if (!$this->assetTypes->contains($assetType)) {
            $this->assetTypes->add($assetType);
        }

        return $this;
    }

    public function removeAssetType(AssetType $assetType): static
    {
        $this->assetTypes->removeElement($assetType);

        return $this;
    }

    /**
     * @param AssetType[] $assetTypes
     */
    public function syncAssetTypes(array $assetTypes): static
    {
        foreach ($this->assetTypes->toArray() as $existing) {
            if (!in_array($existing, $assetTypes, true)) {
                $this->removeAssetType($existing);
            }
        }
        foreach ($assetTypes as $assetType) {
            $this->addAssetType($assetType);
        }

        return $this;
    }


    /**
     * @return Collection<int, EtatBien>
     */
    public function getEtatBiens(): Collection
    {
        return $this->etatBiens;
    }

    public function addEtatBien(EtatBien $etatBien): static
    {
        if (!$this->etatBiens->contains($etatBien)) {
            $this->etatBiens->add($etatBien);
        }

        return $this;
    }

    public function removeEtatBien(EtatBien $etatBien): static
    {
        $this->etatBiens->removeElement($etatBien);

        return $this;
    }

    /**
     * @param EtatBien[] $etatBiens
     */
    public function syncEtatBiens(array $etatBiens): static
    {
        foreach ($this->etatBiens->toArray() as $existing) {
            if (!in_array($existing, $etatBiens, true)) {
                $this->removeEtatBien($existing);
            }
        }
        foreach ($etatBiens as $etatBien) {
            $this->addEtatBien($etatBien);
        }

        return $this;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
        }

        return $this;
    }

    public function removeService(Service $service): static
    {
        $this->services->removeElement($service);

        return $this;
    }

    /**
     * @param Service[] $services
     */
    public function syncServices(array $services): static
    {
        foreach ($this->services->toArray() as $existing) {
            if (!in_array($existing, $services, true)) {
                $this->removeService($existing);
            }
        }
        foreach ($services as $service) {
            $this->addService($service);
        }

        return $this;
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
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        $this->projects->removeElement($project);

        return $this;
    }

    /**
     * @param Project[] $projects
     */
    public function syncProjects(array $projects): static
    {
        foreach ($this->projects->toArray() as $existing) {
            if (!in_array($existing, $projects, true)) {
                $this->removeProject($existing);
            }
        }
        foreach ($projects as $project) {
            $this->addProject($project);
        }

        return $this;
    }

    /**
     * @return Collection<int, PieceJointe>
     */
    public function getPiecesJointes(): Collection
    {
        return $this->piecesJointes;
    }

    public function addPieceJointe(PieceJointe $pieceJointe): static
    {
        if (!$this->piecesJointes->contains($pieceJointe)) {
            $this->piecesJointes->add($pieceJointe);
        }

        return $this;
    }

    public function removePieceJointe(PieceJointe $pieceJointe): static
    {
        $this->piecesJointes->removeElement($pieceJointe);

        return $this;
    }

    /**
     * @return Collection<int, AssetAssignment>
     */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function addAssignment(AssetAssignment $assignment): static
    {
        if (!$this->assignments->contains($assignment)) {
            $this->assignments->add($assignment);
            $assignment->setAsset($this);
        }

        return $this;
    }

    public function removeAssignment(AssetAssignment $assignment): static
    {
        if ($this->assignments->removeElement($assignment)) {
            if ($assignment->getAsset() === $this) {
                $assignment->setAsset(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AssetLocation>
     */
    public function getAssetLocations(): Collection
    {
        return $this->assetLocations;
    }

    public function addAssetLocation(AssetLocation $assetLocation): static
    {
        if (!$this->assetLocations->contains($assetLocation)) {
            $this->assetLocations->add($assetLocation);
            $assetLocation->setAsset($this);
        }

        return $this;
    }

    public function removeAssetLocation(AssetLocation $assetLocation): static
    {
        $this->assetLocations->removeElement($assetLocation);
        return $this;
    }

    /**
     * @return Collection<int, AssetReformRequest>
     */
    public function getReformRequests(): Collection
    {
        return $this->reformRequests;
    }

    public function addReformRequest(AssetReformRequest $reformRequest): static
    {
        if (!$this->reformRequests->contains($reformRequest)) {
            $this->reformRequests->add($reformRequest);
            $reformRequest->setAsset($this);
        }

        return $this;
    }

    public function removeReformRequest(AssetReformRequest $reformRequest): static
    {
        $this->reformRequests->removeElement($reformRequest);
        return $this;
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
            $assetSecurity->setAsset($this);
        }

        return $this;
    }

    public function removeAssetSecurity(AssetSecurity $assetSecurity): static
    {
        $this->assetSecurities->removeElement($assetSecurity);
        return $this;
    }

    /**
     * @return Collection<int, Champ>
     */
    public function getChamps(): Collection
    {
        return $this->champs;
    }

    public function addChamp(Champ $champ): static
    {
        if (!$this->champs->contains($champ)) {
            $this->champs->add($champ);
        }

        return $this;
    }

    public function removeChamp(Champ $champ): static
    {
        $this->champs->removeElement($champ);

        return $this;
    }

    /**
     * @return Collection<int, AssetMaintenance>
     */
    public function getMaintenances(): Collection
    {
        return $this->maintenances;
    }

    public function addMaintenance(AssetMaintenance $maintenance): static
    {
        if (!$this->maintenances->contains($maintenance)) {
            $this->maintenances->add($maintenance);
            $maintenance->addAsset($this);
        }

        return $this;
    }

    public function removeMaintenance(AssetMaintenance $maintenance): static
    {
        if ($this->maintenances->removeElement($maintenance)) {
            $maintenance->removeAsset($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, AssetReevaluation>
     */
    public function getReevaluations(): Collection
    {
        return $this->reevaluations;
    }

    public function addReevaluation(AssetReevaluation $reevaluation): static
    {
        if (!$this->reevaluations->contains($reevaluation)) {
            $this->reevaluations->add($reevaluation);
            $reevaluation->addAsset($this);
        }

        return $this;
    }

    public function removeReevaluation(AssetReevaluation $reevaluation): static
    {
        if ($this->reevaluations->removeElement($reevaluation)) {
            $reevaluation->removeAsset($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, AssetDepreciation>
     */
    public function getDepreciations(): Collection
    {
        return $this->depreciations;
    }

    public function addDepreciation(AssetDepreciation $depreciation): static
    {
        if (!$this->depreciations->contains($depreciation)) {
            $this->depreciations->add($depreciation);
            $depreciation->addAsset($this);
        }

        return $this;
    }

    public function removeDepreciation(AssetDepreciation $depreciation): static
    {
        if ($this->depreciations->removeElement($depreciation)) {
            $depreciation->removeAsset($this);
        }

        return $this;
    }

    public function getSortie(): ?AssetExit
    {
        return $this->sortie;
    }

    public function setSortie(?AssetExit $sortie): static
    {
        $this->sortie = $sortie;
        return $this;
    }

    /**
     * @return Collection<int, AssetSubType>
     */
    public function getAssetSubTypes(): Collection
    {
        return $this->assetSubTypes;
    }

    public function addAssetSubType(AssetSubType $assetSubType): static
    {
        if (!$this->assetSubTypes->contains($assetSubType)) {
            $this->assetSubTypes->add($assetSubType);
        }

        return $this;
    }

    public function removeAssetSubType(AssetSubType $assetSubType): static
    {
        $this->assetSubTypes->removeElement($assetSubType);

        return $this;
    }

    /**
     * @param AssetSubType[] $assetSubTypes
     */
    public function syncAssetSubTypes(array $assetSubTypes): static
    {
        foreach ($this->assetSubTypes->toArray() as $existing) {
            if (!in_array($existing, $assetSubTypes, true)) {
                $this->removeAssetSubType($existing);
            }
        }
        foreach ($assetSubTypes as $assetSubType) {
            $this->addAssetSubType($assetSubType);
        }

        return $this;
    }

        /**
     * Tous les biens actifs correspondant au périmètre optionnel (service/catégorie),
     * SANS pagination : utilisé uniquement pour figer la liste d'une InventoryCampaign
     * au lancement (on a besoin de la totalité, pas d'une page).
     *
     * @return Asset[]
     */
    public function findAllActiveForCampaignScope(?int $serviceId = null, ?int $categoryId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.isDelete = false');

        if (null !== $serviceId) {
            $qb->innerJoin('a.services', 's')
                ->andWhere('s.id = :serviceId')
                ->setParameter('serviceId', $serviceId);
        }
        if (null !== $categoryId) {
            $qb->innerJoin('a.categories', 'c')
                ->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        return $qb->getQuery()->getResult();
    }



}
