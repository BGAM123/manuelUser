<?php

// src/Entity/Bsp.php

namespace App\Entity;

use App\Repository\BspRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Bon de Sortie Provisoire (BSP) — document de traçabilité d'une sortie de bien (AssetExit)
 * pour un bénéficiaire donné. Une sortie peut être matérialisée par plusieurs BSP
 * (un par bénéficiaire).
 */
#[ORM\Entity(repositoryClass: BspRepository::class)]
#[ORM\Table(name: 'bsp')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['numero'], message: 'Ce numéro de BSP est déjà utilisé.')]
class Bsp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['bsp:list', 'bsp:detail', 'asset_exit:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['bsp:list', 'bsp:detail', 'asset_exit:detail'])]
    private ?string $numero = null;

    #[ORM\ManyToOne(targetEntity: AssetExit::class, inversedBy: 'bsps')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['bsp:detail'])]
    private ?AssetExit $assetExit = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['bsp:list', 'bsp:detail', 'asset_exit:detail'])]
    private ?Service $service = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'beneficiaire_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['bsp:list', 'bsp:detail', 'asset_exit:detail'])]
    private ?User $beneficiaire = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['bsp:detail'])]
    private ?User $createdBy = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['bsp:list', 'bsp:detail'])]
    private ?int $quantiteDemandee = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['bsp:list', 'bsp:detail'])]
    private ?int $quantiteAccordee = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La quantité servie est obligatoire.')]
    #[Assert\Positive(message: 'La quantité servie doit être supérieure à 0.')]
    #[Groups(['bsp:list', 'bsp:detail'])]
    private ?int $quantiteServie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['bsp:detail'])]
    private ?string $observations = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['bsp:list', 'bsp:detail'])]
    private ?\DateTimeInterface $dateEtablissement = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['bsp:list', 'bsp:detail'])]
    private ?\DateTimeInterface $dateRetourEffective = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['bsp:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['bsp:detail'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['bsp:list', 'bsp:detail'])]
    private bool $isDelete = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['bsp:list', 'bsp:detail'])]
    private bool $retour = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(
        name: 'id_validateur_retour',
        referencedColumnName: 'id',
        nullable: true,
        onDelete: 'SET NULL'
    )]
    #[Groups(['bsp:detail'])]
    private ?User $validateurRetour = null;

    /**
     * @var Collection<int, PieceJointe>
     */
    #[ORM\ManyToMany(targetEntity: PieceJointe::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'bsp_piece_jointe')]
    #[ORM\JoinColumn(name: 'bsp_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'piece_jointe_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['bsp:detail'])]
    private Collection $pieceJointes;

    public function __construct()
    {
        $this->pieceJointes = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        if (null === $this->dateEtablissement) {
            $this->dateEtablissement = new \DateTime();
        }
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

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;
        return $this;
    }

    public function getAssetExit(): ?AssetExit
    {
        return $this->assetExit;
    }

    public function setAssetExit(?AssetExit $assetExit): static
    {
        $this->assetExit = $assetExit;
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

    public function getBeneficiaire(): ?User
    {
        return $this->beneficiaire;
    }

    public function setBeneficiaire(?User $beneficiaire): static
    {
        $this->beneficiaire = $beneficiaire;
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

    public function getQuantiteDemandee(): ?int
    {
        return $this->quantiteDemandee;
    }

    public function setQuantiteDemandee(?int $quantiteDemandee): static
    {
        $this->quantiteDemandee = $quantiteDemandee;
        return $this;
    }

    public function getQuantiteAccordee(): ?int
    {
        return $this->quantiteAccordee;
    }

    public function setQuantiteAccordee(?int $quantiteAccordee): static
    {
        $this->quantiteAccordee = $quantiteAccordee;
        return $this;
    }

    public function getQuantiteServie(): ?int
    {
        return $this->quantiteServie;
    }

    public function setQuantiteServie(?int $quantiteServie): static
    {
        $this->quantiteServie = $quantiteServie;
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

    public function getDateEtablissement(): ?\DateTimeInterface
    {
        return $this->dateEtablissement;
    }

    public function setDateEtablissement(?\DateTimeInterface $dateEtablissement): static
    {
        $this->dateEtablissement = $dateEtablissement;
        return $this;
    }

    public function getDateRetourEffective(): ?\DateTimeInterface
    {
        return $this->dateRetourEffective;
    }

    public function setDateRetourEffective(?\DateTimeInterface $dateRetourEffective): static
    {
        $this->dateRetourEffective = $dateRetourEffective;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
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

    public function isRetour(): bool
    {
        return $this->retour;
    }

    public function setRetour(bool $retour): static
    {
        $this->retour = $retour;
        return $this;
    }

    public function getValidateurRetour(): ?User
    {
        return $this->validateurRetour;
    }

    public function setValidateurRetour(?User $validateurRetour): static
    {
        $this->validateurRetour = $validateurRetour;
        return $this;
    }
}
