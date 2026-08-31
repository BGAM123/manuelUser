<?php

namespace App\Entity;

use App\Repository\AssetAssignmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssetAssignmentRepository::class)]
#[ORM\Table(name: 'asset_assignment')]
#[ORM\HasLifecycleCallbacks]
class AssetAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private ?Asset $asset = null;

    #[ORM\ManyToOne(inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private ?Service $service = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'assigned_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_assignment:detail'])]
    private ?User $assignedBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    #[Assert\Choice(choices: ['AFFECTATION', 'RESTITUTION'], message: 'Le type d\'affectation doit être AFFECTATION ou RESTITUTION.')]
    private ?string $typeAffectation = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private ?string $commentaire = null;

    #[ORM\Column]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private bool $detenteur = false;

    /**
     * Cache dénormalisé pour un listing rapide sans jointure vers AcknowledgementOfReceipt
     * (source de vérité réelle, gérée par AcknowledgementService). Tenu à jour à chaque
     * accusé de réception via AcknowledgementNotificationSubscriber.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private bool $received = false;

    #[ORM\Column]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private bool $isDelete = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Utilisateur ayant créé l'affectation
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_assignment:detail'])]
    private ?User $createdBy = null;

    /**
     * Utilisateur ayant modifié l'affectation
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['asset_assignment:detail'])]
    private ?User $updatedBy = null;

    #[ORM\ManyToMany(targetEntity: PieceJointe::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'asset_assignment_piece_jointe')]
    #[ORM\JoinColumn(name: 'asset_assignment_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'piece_jointe_id', referencedColumnName: 'id')]
    #[Groups(['asset_assignment:detail', 'asset:detail'])]
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

    public function getAssignedBy(): ?User
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(?User $assignedBy): static
    {
        $this->assignedBy = $assignedBy;

        return $this;
    }

    public function getTypeAffectation(): ?string
    {
        return $this->typeAffectation;
    }

    public function setTypeAffectation(?string $typeAffectation): static
    {
        $this->typeAffectation = $typeAffectation;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function isDetenteur(): bool
    {
        return $this->detenteur;
    }

    public function setDetenteur(bool $detenteur): static
    {
        $this->detenteur = $detenteur;

        return $this;
    }

    public function isReceived(): bool
    {
        return $this->received;
    }

    public function setReceived(bool $received): static
    {
        $this->received = $received;

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
