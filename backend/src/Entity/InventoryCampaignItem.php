<?php

namespace App\Entity;

use App\Repository\InventaireCampagneRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * "Photo" d'un bien au lancement d'une InventoryCampaign : une ligne par bien actif
 * figé au moment du lancement (jamais ajoutée/retirée après coup, cf. commentaire de
 * InventoryCampaign). retrouve passe à true lors du pointage physique par un agent.
 */
#[ORM\Entity(repositoryClass: InventoryCampaignItemRepository::class)]
#[ORM\Table(name: 'inventory_campaign_item')]
#[ORM\HasLifecycleCallbacks]
class InventoryCampaignItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['inventory_campaign_item:list', 'inventory_campaign_item:detail'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InventaireCampagne $campaign = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['inventory_campaign_item:list', 'inventory_campaign_item:detail'])]
    private ?Asset $asset = null;

    #[ORM\Column]
    #[Groups(['inventory_campaign_item:list', 'inventory_campaign_item:detail'])]
    private bool $retrouve = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['inventory_campaign_item:detail'])]
    private ?\DateTimeImmutable $dateVerification = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'verifie_par_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['inventory_campaign_item:detail'])]
    private ?User $verifiePar = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['inventory_campaign_item:detail'])]
    private ?string $observations = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['inventory_campaign_item:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['inventory_campaign_item:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getCampaign(): ?    InventaireCampagne      
    {
        return $this->campaign;
    }

    public function setCampaign(?InventaireCampagne $campaign): static
    {
        $this->campaign = $campaign;

        return $this;
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

    public function isRetrouve(): bool
    {
        return $this->retrouve;
    }

    public function getDateVerification(): ?\DateTimeImmutable
    {
        return $this->dateVerification;
    }

    public function getVerifiePar(): ?User
    {
        return $this->verifiePar;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    /**
     * Marque le bien comme physiquement retrouvé. Idempotent : rappeler ne change rien
     * de mal (le contrôleur peut choisir de refuser un second pointage s'il le souhaite,
     * mais l'entité elle-même ne l'empêche pas).
     */
    public function markAsFound(User $verifiePar, ?string $observations = null): void
    {
        $this->retrouve = true;
        $this->dateVerification = new \DateTimeImmutable();
        $this->verifiePar = $verifiePar;
        $this->observations = $observations;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}