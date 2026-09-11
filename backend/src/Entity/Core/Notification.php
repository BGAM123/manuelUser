<?php

namespace App\Entity\Core;

use App\Repository\Core\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'core_notifications')]
#[ORM\Index(name: 'idx_notification_user', columns: ['id_user'])]
#[ORM\Index(name: 'idx_notification_service', columns: ['id_service'])]
#[ORM\Index(name: 'idx_notification_read', columns: ['is_read'])]
#[ORM\Index(name: 'idx_notification_created', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, nullable: false)]
    #[Groups(['notification:read', 'notification:list'])]
    private ?int $id = null;

    #[ORM\Column(name: 'titre', type: Types::STRING, length: 255, nullable: false)]
    #[Groups(['notification:read', 'notification:list'])]
    private ?string $titre = null;

    #[ORM\Column(name: 'message', type: Types::TEXT, nullable: false)]
    #[Groups(['notification:read', 'notification:list'])]
    private ?string $message = null;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['notification:read', 'notification:list'])]
    private ?string $type = null;

    #[ORM\Column(name: 'is_read', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['notification:read', 'notification:list'])]
    private bool $isRead = false;

    #[ORM\Column(name: 'read_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['notification:read', 'notification:list'])]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(name: 'data', type: Types::JSON, nullable: true)]
    #[Groups(['notification:read'])]
    private ?array $data = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    #[Groups(['notification:read', 'notification:list'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(name: 'id_service', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: true)]
    #[Groups(['notification:read', 'notification:list'])]
    private ?Service $service = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['notification:read', 'notification:list'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['notification:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->isRead = false;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        
        // Si on marque comme lu, on met à jour la date de lecture
        if ($isRead && $this->readAt === null) {
            $this->readAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;
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

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
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

    /**
     * Marque la notification comme lue
     */
    public function markAsRead(): static
    {
        $this->isRead = true;
        $this->readAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Marque la notification comme non lue
     */
    public function markAsUnread(): static
    {
        $this->isRead = false;
        $this->readAt = null;
        return $this;
    }
}
