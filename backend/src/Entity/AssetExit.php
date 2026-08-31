<?php

namespace App\Entity;

use App\Repository\AssetExitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AssetExitRepository::class)]
#[ORM\Table(name: 'asset_exit')]
#[ORM\HasLifecycleCallbacks]
class AssetExit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['asset_exit:detail', 'asset:detail'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'sortie')]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    #[Groups(['asset_exit:detail'])]
    private ?Asset $asset = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['asset_exit:detail', 'asset:detail'])]
    private ?Service $service = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_exit:detail', 'asset:detail'])]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['asset_exit:detail', 'asset:detail'])]
    private ?string $motifSortie = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['asset_exit:detail', 'asset:detail'])]
    private ?\DateTimeInterface $dateSortie = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['asset_exit:detail', 'asset:detail'])]
    private ?string $protocoleReference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['asset_exit:detail', 'asset:detail'])]
    private ?string $observations = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['asset_exit:detail', 'asset:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['asset_exit:detail', 'asset:detail'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['asset_exit:detail'])]
    private bool $isDelete = false;

    /**
     * Utilisateur ayant créé la sortie
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_exit:detail'])]
    private ?User $createdBy = null;

    /**
     * Utilisateur ayant modifié la sortie
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_exit:detail'])]
    private ?User $updatedBy = null;

    #[ORM\ManyToOne(targetEntity: ExitType::class, inversedBy: 'assetExits')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['asset_exit:detail', 'asset:detail'])]
    private ?ExitType $exitType = null;

    #[ORM\ManyToMany(targetEntity: PieceJointe::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'asset_exit_piece_jointe')]
    #[ORM\JoinColumn(name: 'asset_exit_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'piece_jointe_id', referencedColumnName: 'id')]
    #[Groups(['asset_exit:detail', 'asset:detail'])]
    private Collection $pieceJointes;

    /**
     * @var Collection<int, Bsp>
     */
    #[ORM\OneToMany(mappedBy: 'assetExit', targetEntity: Bsp::class)]
    #[Groups(['asset_exit:detail'])]
    private Collection $bsps;

    public function __construct()
    {
        $this->pieceJointes = new ArrayCollection();
        $this->bsps = new ArrayCollection();
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

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): static
    {
        $this->asset = $asset;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }


    public function getExitType(): ?ExitType
    {
        return $this->exitType;
    }

    public function setExitType(?ExitType $exitType): static
    {
        $this->exitType = $exitType;
        return $this;
    }

    public function getMotifSortie(): ?string
    {
        return $this->motifSortie;
    }

    public function setMotifSortie(?string $motifSortie): static
    {
        $this->motifSortie = $motifSortie;
        return $this;
    }

    public function getDateSortie(): ?\DateTimeInterface
    {
        return $this->dateSortie;
    }

    public function setDateSortie(?\DateTimeInterface $dateSortie): static
    {
        $this->dateSortie = $dateSortie;
        return $this;
    }

    public function getProtocoleReference(): ?string
    {
        return $this->protocoleReference;
    }

    public function setProtocoleReference(?string $protocoleReference): static
    {
        $this->protocoleReference = $protocoleReference;
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

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
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

    /**
     * @return Collection<int, Bsp>
     */
    public function getBsps(): Collection
    {
        return $this->bsps;
    }

    public function addBsp(Bsp $bsp): static
    {
        if (!$this->bsps->contains($bsp)) {
            $this->bsps->add($bsp);
            $bsp->setAssetExit($this);
        }
        return $this;
    }

    public function removeBsp(Bsp $bsp): static
    {
        if ($this->bsps->removeElement($bsp)) {
            if ($bsp->getAssetExit() === $this) {
                $bsp->setAssetExit(null);
            }
        }
        return $this;
    }
}
