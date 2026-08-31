<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use App\Repository\InventaireCampagneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Campagne d'inventaire physique : fige la liste des biens actifs concernés au lancement
 * (via InventoryCampaignItem), permet de pointer chaque bien retrouvé lors du contrôle
 * physique, et sert de source de vérité pour détecter les biens attendus mais non
 * retrouvés (cf. module Intelligence Patrimoniale, catégorie d'anomalie "inventaire").
 */
#[ORM\Entity(repositoryClass: InventaireCampagneRepository::class)]
#[ORM\Table(name: 'inventory_campaign')]
#[ORM\HasLifecycleCallbacks]
class InventaireCampagne implements BlameableInterface
{
    use BlameableTrait;

    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_CLOTUREE = 'CLOTUREE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['inventory_campaign:list', 'inventory_campaign:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de la campagne est obligatoire.')]
    #[Groups(['inventory_campaign:list', 'inventory_campaign:detail'])]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['inventory_campaign:detail'])]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Groups(['inventory_campaign:list', 'inventory_campaign:detail'])]
    private string $statut = self::STATUT_EN_COURS;

    /**
     * Périmètre optionnel de la campagne : si renseigné, seuls les biens actifs de ce
     * service ont été figés dans les items. Null = campagne globale (tous les services).
     */
    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['inventory_campaign:list', 'inventory_campaign:detail'])]
    private ?Service $service = null;

    /**
     * Périmètre optionnel additionnel : si renseigné, seuls les biens actifs de cette
     * catégorie ont été figés (combinable avec service).
     */
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['inventory_campaign:list', 'inventory_campaign:detail'])]
    private ?Category $category = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['inventory_campaign:list', 'inventory_campaign:detail'])]
    private ?\DateTimeImmutable $dateDebut = null;

    /**
     * Posée uniquement à la clôture (close()) - null tant que la campagne est ouverte.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['inventory_campaign:list', 'inventory_campaign:detail'])]
    private ?\DateTimeImmutable $dateCloture = null;

    #[ORM\Column]
    #[Groups(['inventory_campaign:detail'])]
    private bool $isDelete = false;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['inventory_campaign:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['inventory_campaign:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, InventoryCampaignItem>
     */
    #[ORM\OneToMany(mappedBy: 'campaign', targetEntity: InventoryCampaignItem::class, cascade: ['persist'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->dateDebut = $now;
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function getDateCloture(): ?\DateTimeImmutable
    {
        return $this->dateCloture;
    }

    public function isDelete(): bool
    {
        return $this->isDelete;
    }

    public function setIsDelete(bool $isDelete): static
    {
        $this->isDelete = $isDelete;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Clôture la campagne : plus aucun pointage n'est possible après. Idempotent côté
     * appelant : le contrôleur doit vérifier isCloturee() avant d'appeler close() pour
     * renvoyer un 409 explicite plutôt que d'écraser dateCloture silencieusement.
     */
    public function close(): void
    {
        $this->statut = self::STATUT_CLOTUREE;
        $this->dateCloture = new \DateTimeImmutable();
    }

    public function isCloturee(): bool
    {
        return self::STATUT_CLOTUREE === $this->statut;
    }

    /**
     * @return Collection<int, InventoryCampaignItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(InventoryCampaignItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCampaign($this);
        }

        return $this;
    }
}