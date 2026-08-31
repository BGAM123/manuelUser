<?php

namespace App\Entity;

use App\Repository\AssetMaintenanceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AssetMaintenanceRepository::class)]
#[ORM\Table(name: 'asset_maintenance')]
#[ORM\HasLifecycleCallbacks]
class AssetMaintenance
{
    public const STATUT_EN_COURS = 'EN COURS';
    public const STATUT_TERMINEE = 'TERMINEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EtatBien::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
    private ?EtatBien $etatBien = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
    private ?string $motif = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
    private ?string $cout = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
    private ?\DateTimeInterface $dateIntervention = null;

    /**
     * Date de récupération réelle/effective — posée uniquement à la clôture (terminate()).
     * NULL tant que la maintenance est en cours ; c'est elle qui pilote refreshStatut().
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
    private ?\DateTimeInterface $dateRecuperation = null;

    /**
     * Date de récupération estimée, donnée par le réparateur à la création (ou en cours de
     * maintenance). Purement indicative — ne pilote jamais le statut.
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
    private ?\DateTimeInterface $dateRecuperationPrevue = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
    private ?string $observations = null;


    /**
     * Utilisateur qui a créé la maintenance
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['asset_maintenance:detail'])]
    private ?User $createdBy = null;

    /**
     * Utilisateur qui a modifié la maintenance
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['asset_maintenance:detail'])]
    private ?User $updatedBy = null;

    /**
     * Dérivé automatiquement depuis dateRecuperation (cf. refreshStatut()) — jamais modifiable
     * directement, pour ne pas se désynchroniser de la vraie source de vérité.
     */
    #[ORM\Column(length: 20)]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
    private string $statut = self::STATUT_EN_COURS;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToMany(targetEntity: Asset::class, inversedBy: 'maintenances')]
    #[ORM\JoinTable(name: 'asset_maintenance_link')]
    #[ORM\JoinColumn(name: 'maintenance_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'asset_id', referencedColumnName: 'id')]
    #[Groups(['asset_maintenance:detail'])]
    private Collection $assets;

    #[ORM\ManyToMany(targetEntity: PieceJointe::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'maintenance_piece_jointe')]
    #[ORM\JoinColumn(name: 'maintenance_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'piece_jointe_id', referencedColumnName: 'id')]
    #[Groups(['asset_maintenance:detail', 'asset:detail'])]
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
        $this->refreshStatut();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
        $this->refreshStatut();
    }

    private function refreshStatut(): void
    {
        $this->statut = null !== $this->dateRecuperation ? self::STATUT_TERMINEE : self::STATUT_EN_COURS;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function getEtatBien(): ?EtatBien
    {
        return $this->etatBien;
    }

    public function setEtatBien(?EtatBien $etatBien): static
    {
        $this->etatBien = $etatBien;

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

    public function getCout(): ?string
    {
        return $this->cout;
    }

    public function setCout(?string $cout): static
    {
        $this->cout = $cout;

        return $this;
    }

    public function getDateIntervention(): ?\DateTimeInterface
    {
        return $this->dateIntervention;
    }

    public function setDateIntervention(?\DateTimeInterface $dateIntervention): static
    {
        $this->dateIntervention = $dateIntervention;

        return $this;
    }

    public function getDateRecuperation(): ?\DateTimeInterface
    {
        return $this->dateRecuperation;
    }

    public function setDateRecuperation(?\DateTimeInterface $dateRecuperation): static
    {
        $this->dateRecuperation = $dateRecuperation;

        return $this;
    }

    public function getDateRecuperationPrevue(): ?\DateTimeInterface
    {
        return $this->dateRecuperationPrevue;
    }

    public function setDateRecuperationPrevue(?\DateTimeInterface $dateRecuperationPrevue): static
    {
        $this->dateRecuperationPrevue = $dateRecuperationPrevue;

        return $this;
    }

    /**
     * Date de récupération à afficher côté client sous une seule clé : la réelle si la
     * maintenance est terminée, sinon l'estimée (si connue).
     */
    public function getDateRecuperationUnifiee(): ?\DateTimeInterface
    {
        return $this->dateRecuperation ?? $this->dateRecuperationPrevue;
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
