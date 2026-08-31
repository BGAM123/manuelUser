<?php

namespace App\Entity;

use App\Repository\AcknowledgementOfReceiptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Accusé de réception générique, rattaché à un sujet métier via (subjectType, subjectId)
 * — même principe de couplage faible que Notification. La contrainte unique garantit qu'un
 * sujet ne peut être accusé réceptionné qu'une seule fois, y compris en cas de concurrence
 * (défense en profondeur : la vérification applicative dans AcknowledgementService la double).
 */
#[ORM\Entity(repositoryClass: AcknowledgementOfReceiptRepository::class)]
#[ORM\Table(name: 'acknowledgement_of_receipt')]
#[ORM\UniqueConstraint(name: 'uniq_ack_subject', columns: ['subject_type', 'subject_id'])]
#[ORM\HasLifecycleCallbacks]
class AcknowledgementOfReceipt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['acknowledgement:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['acknowledgement:detail'])]
    private ?string $subjectType = null;

    #[ORM\Column]
    #[Groups(['acknowledgement:detail'])]
    private ?int $subjectId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['acknowledgement:detail'])]
    private ?User $recipient = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['acknowledgement:detail'])]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['acknowledgement:detail'])]
    private ?\DateTimeInterface $acknowledgedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        if (null === $this->acknowledgedAt) {
            $this->acknowledgedAt = new \DateTime();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function setSubjectType(string $subjectType): static
    {
        $this->subjectType = $subjectType;
        return $this;
    }

    public function getSubjectId(): ?int
    {
        return $this->subjectId;
    }

    public function setSubjectId(int $subjectId): static
    {
        $this->subjectId = $subjectId;
        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getAcknowledgedAt(): ?\DateTimeInterface
    {
        return $this->acknowledgedAt;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
