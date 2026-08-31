<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Notification générique rattachée à un sujet métier via (subjectType, subjectId), sans
 * relation Doctrine directe vers l'entité concernée. C'est ce couplage faible qui permet à
 * n'importe quel module (affectations, transferts, et demain réformes/maintenances/validations)
 * de brancher des notifications par un simple appel à NotificationService::notify(), sans
 * modification de cette entité ni migration supplémentaire.
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\Index(columns: ['subject_type', 'subject_id'], name: 'idx_notification_subject')]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    public const SUBJECT_ASSET_ASSIGNMENT = 'asset_assignment';
    public const SUBJECT_CONSUMABLE_TRANSFER = 'consumable_transfer';

    public const TYPE_CREATED = 'created';
    public const TYPE_ACKNOWLEDGED = 'acknowledged';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['notification:list', 'notification:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['notification:list', 'notification:detail'])]
    private ?string $subjectType = null;

    #[ORM\Column]
    #[Groups(['notification:list', 'notification:detail'])]
    private ?int $subjectId = null;

    #[ORM\Column(length: 50)]
    #[Groups(['notification:list', 'notification:detail'])]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['notification:detail'])]
    private ?User $recipient = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['notification:detail'])]
    private ?User $sender = null;

    #[ORM\Column(length: 255)]
    #[Groups(['notification:list', 'notification:detail'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['notification:list', 'notification:detail'])]
    private ?string $message = null;

    #[ORM\Column]
    #[Groups(['notification:list', 'notification:detail'])]
    private bool $isRead = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['notification:list', 'notification:detail'])]
    private ?\DateTimeInterface $readAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['notification:list', 'notification:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeInterface $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
