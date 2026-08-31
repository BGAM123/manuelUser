<?php

namespace App\Entity;

use App\Repository\AssetReevaluationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AssetReevaluationRepository::class)]
#[ORM\Table(name: 'asset_reevaluation')]
#[ORM\HasLifecycleCallbacks]
class AssetReevaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['asset_reevaluation:detail', 'asset:detail'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['asset_reevaluation:detail', 'asset:detail'])]
    private ?string $valeurActuelle = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['asset_reevaluation:detail', 'asset:detail'])]
    private ?string $nouvelleValeur = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['asset_reevaluation:detail', 'asset:detail'])]
    private ?string $methodeEvaluation = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['asset_reevaluation:detail', 'asset:detail'])]
    private ?Service $service = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['asset_reevaluation:detail', 'asset:detail'])]
    private ?\DateTimeInterface $dateReevaluation = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['asset_reevaluation:detail', 'asset:detail'])]
    private ?string $motif = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['asset_reevaluation:detail', 'asset:detail'])]
    private ?string $observations = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['asset_reevaluation:detail', 'asset:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['asset_reevaluation:detail', 'asset:detail'])]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Utilisateur ayant créé la réévaluation
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_reevaluation:detail'])]
    private ?User $createdBy = null;

    /**
     * Utilisateur ayant modifié la réévaluation
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_reevaluation:detail'])]
    private ?User $updatedBy = null;

    #[ORM\ManyToMany(targetEntity: Asset::class, inversedBy: 'reevaluations')]
    #[ORM\JoinTable(name: 'asset_reevaluation_link')]
    #[ORM\JoinColumn(name: 'reevaluation_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'asset_id', referencedColumnName: 'id')]
    #[Groups(['asset_reevaluation:detail'])]
    private Collection $assets;

    #[ORM\ManyToMany(targetEntity: PieceJointe::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'reevaluation_piece_jointe')]
    #[ORM\JoinColumn(name: 'reevaluation_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'piece_jointe_id', referencedColumnName: 'id')]
    #[Groups(['asset_reevaluation:detail', 'asset:detail'])]
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

    public function getValeurActuelle(): ?string
    {
        return $this->valeurActuelle;
    }

    public function setValeurActuelle(?string $valeurActuelle): static
    {
        $this->valeurActuelle = $valeurActuelle;

        return $this;
    }

    public function getNouvelleValeur(): ?string
    {
        return $this->nouvelleValeur;
    }

    public function setNouvelleValeur(?string $nouvelleValeur): static
    {
        $this->nouvelleValeur = $nouvelleValeur;

        return $this;
    }

    public function getMethodeEvaluation(): ?string
    {
        return $this->methodeEvaluation;
    }

    public function setMethodeEvaluation(?string $methodeEvaluation): static
    {
        $this->methodeEvaluation = $methodeEvaluation;

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

    public function getDateReevaluation(): ?\DateTimeInterface
    {
        return $this->dateReevaluation;
    }

    public function setDateReevaluation(?\DateTimeInterface $dateReevaluation): static
    {
        $this->dateReevaluation = $dateReevaluation;

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
