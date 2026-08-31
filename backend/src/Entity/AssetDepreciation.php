<?php

namespace App\Entity;

use App\Repository\AssetDepreciationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AssetDepreciationRepository::class)]
#[ORM\Table(name: 'asset_depreciation')]
#[ORM\HasLifecycleCallbacks]
class AssetDepreciation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?string $typeDepreciation = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?string $methodeAmortissement = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?int $dureeVie = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?string $valeurActuelle = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?string $tauxDepreciation = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?string $montantDepreciation = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?\DateTimeInterface $dateDepreciation = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?string $motif = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?string $observations = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Utilisateur ayant créé la dépréciation
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_depreciation:detail'])]
    private ?User $createdBy = null;

    /**
     * Utilisateur ayant modifié la dépréciation
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_depreciation:detail'])]
    private ?User $updatedBy = null;

    #[ORM\ManyToMany(targetEntity: Asset::class, inversedBy: 'depreciations')]
    #[ORM\JoinTable(name: 'asset_depreciation_link')]
    #[ORM\JoinColumn(name: 'depreciation_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'asset_id', referencedColumnName: 'id')]
    #[Groups(['asset_depreciation:detail'])]
    private Collection $assets;

    #[ORM\ManyToMany(targetEntity: PieceJointe::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'depreciation_piece_jointe')]
    #[ORM\JoinColumn(name: 'depreciation_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'piece_jointe_id', referencedColumnName: 'id')]
    #[Groups(['asset_depreciation:detail', 'asset:detail'])]
    private Collection $pieceJointes;

    public function __construct()
    {
        $this->assets = new ArrayCollection();
        $this->pieceJointes = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeDepreciation(): ?string
    {
        return $this->typeDepreciation;
    }

    public function setTypeDepreciation(?string $typeDepreciation): static
    {
        $this->typeDepreciation = $typeDepreciation;

        return $this;
    }

    public function getMethodeAmortissement(): ?string
    {
        return $this->methodeAmortissement;
    }

    public function setMethodeAmortissement(?string $methodeAmortissement): static
    {
        $this->methodeAmortissement = $methodeAmortissement;

        return $this;
    }

    public function getDureeVie(): ?int
    {
        return $this->dureeVie;
    }

    public function setDureeVie(?int $dureeVie): static
    {
        $this->dureeVie = $dureeVie;

        return $this;
    }

    public function getValeurActuelle(): ?string
    {
        return $this->valeurActuelle;
    }

    public function setValeurActuelle(?string $valeurActuelle): static
    {
        $this->valeurActuelle = $valeurActuelle;

        return $this;
    }

    public function getTauxDepreciation(): ?string
    {
        return $this->tauxDepreciation;
    }

    public function setTauxDepreciation(?string $tauxDepreciation): static
    {
        $this->tauxDepreciation = $tauxDepreciation;

        return $this;
    }

    public function getMontantDepreciation(): ?string
    {
        return $this->montantDepreciation;
    }

    public function setMontantDepreciation(?string $montantDepreciation): static
    {
        $this->montantDepreciation = $montantDepreciation;

        return $this;
    }

    public function getDateDepreciation(): ?\DateTimeInterface
    {
        return $this->dateDepreciation;
    }

    public function setDateDepreciation(?\DateTimeInterface $dateDepreciation): static
    {
        $this->dateDepreciation = $dateDepreciation;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
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

    /**
     * @return Collection<int, Asset>
     */
    public function getAssets(): Collection
    {
        return $this->assets;
    }

    public function addAsset(Asset $asset): static
    {
        if (!$this->assets->contains($asset)) {
            $this->assets->add($asset);
        }

        return $this;
    }

    public function removeAsset(Asset $asset): static
    {
        $this->assets->removeElement($asset);

        return $this;
    }

    /**
     * @return Collection<int, PieceJointe>
     */
    public function getPieceJointes(): Collection
    {
        return $this->pieceJointes;
    }

    public function addPieceJointe(PieceJointe $pieceJointe): static
    {
        if (!$this->pieceJointes->contains($pieceJointe)) {
            $this->pieceJointes->add($pieceJointe);
        }

        return $this;
    }

    public function removePieceJointe(PieceJointe $pieceJointe): static
    {
        $this->pieceJointes->removeElement($pieceJointe);

        return $this;
    }
}
