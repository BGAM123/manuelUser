<?php

namespace App\Entity;

use App\Repository\AssetReformRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AssetReformRequestRepository::class)]
#[ORM\Table(name: 'asset_reform_request')]
#[ORM\HasLifecycleCallbacks]
class AssetReformRequest
{
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_VALIDEE = 'VALIDEE';
    public const STATUT_REJETEE = 'REJETEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['asset_reform:detail'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reformRequests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['asset_reform:detail', 'asset:detail'])]
    private ?Asset $asset = null;

    #[ORM\Column(length: 50)]
    #[Groups(['asset_reform:detail', 'asset:detail'])]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['asset_reform:detail', 'asset:detail'])]
    private ?string $previousEtat = null;

    #[ORM\Column]
    #[Groups(['asset_reform:detail', 'asset:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_reform:detail', 'asset:detail'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['asset_reform:detail', 'asset:detail'])]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'validated_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_reform:detail', 'asset:detail'])]
    private ?User $validatedBy = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    // #[SerializedName('is_delete')]
    #[Groups(['asset_reform:detail'])]
    private bool $isDelete = false;

    /**
     * @var Collection<int, PieceJointe>
     */
    #[ORM\ManyToMany(targetEntity: PieceJointe::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'asset_reform_request_piece_jointe')]
    #[ORM\JoinColumn(name: 'asset_reform_request_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'piece_jointe_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['asset_reform:detail', 'asset:detail'])]
    private Collection $pieceJointes;

    public function __construct()
    {
        $this->pieceJointes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
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

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        if (!in_array($statut, [self::STATUT_EN_ATTENTE, self::STATUT_VALIDEE, self::STATUT_REJETEE], true)) {
            throw new \InvalidArgumentException('Statut invalide. Valeurs autorisées: EN_ATTENTE, VALIDEE, REJETEE');
        }
        $this->statut = $statut;
        return $this;
    }

    public function getPreviousEtat(): ?string
    {
        return $this->previousEtat;
    }

    public function setPreviousEtat(?string $previousEtat): static
    {
        $this->previousEtat = $previousEtat;
        return $this;
    }

    public function isEnAttente(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }

    public function isValidee(): bool
    {
        return $this->statut === self::STATUT_VALIDEE;
    }

    public function isRejetee(): bool
    {
        return $this->statut === self::STATUT_REJETEE;
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): static
    {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;
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
