<?php

// src/Entity/EtatBien.php

namespace App\Entity;

use App\Repository\EtatBienRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * État d'un bien patrimonial, associé à un ou plusieurs types de biens.
 */
#[ORM\Entity(repositoryClass: EtatBienRepository::class)]
#[ORM\Table(name: 'etat_bien')]
#[UniqueEntity(fields: ['nom'], message: "Ce nom d'état de bien est déjà utilisé.")]
class EtatBien
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['etat_bien:list', 'etat_bien:detail', 'asset_type:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 191, unique: true)]
    #[Assert\NotBlank(message: "Le nom de l'état de bien est obligatoire.")]
    #[Assert\Length(
        max: 191,
        maxMessage: "Le nom de l'état de bien ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['etat_bien:list', 'etat_bien:detail', 'asset_type:detail'])]
    private ?string $nom = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['etat_bien:list', 'etat_bien:detail', 'asset_type:detail'])]
    private ?int $numeroOrdre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['etat_bien:list', 'etat_bien:detail', 'asset_type:detail'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de création est obligatoire.')]
    #[Groups(['etat_bien:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de mise à jour est obligatoire.')]
    #[Groups(['etat_bien:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['etat_bien:list', 'etat_bien:detail'])]
    private bool $isDelete = false;

    /**
     * Types de biens pour lesquels cet état est autorisé (ManyToMany propriétaire).
     *
     * @var Collection<int, AssetType>
     */
    #[ORM\ManyToMany(targetEntity: AssetType::class, inversedBy: 'etatBiens')]
    #[ORM\JoinTable(name: 'asset_type_etat_bien')]
    #[ORM\JoinColumn(name: 'etat_bien_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'asset_type_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['etat_bien:list', 'etat_bien:detail'])]
    private Collection $assetTypes;

    public function __construct()
    {
        $this->assetTypes = new ArrayCollection();
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

    public function getNumeroOrdre(): ?int
    {
        return $this->numeroOrdre;
    }

    public function setNumeroOrdre(?int $numeroOrdre): static
    {
        $this->numeroOrdre = $numeroOrdre;

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

    public function isDelete(): bool
    {
        return $this->isDelete;
    }

    public function setIsDelete(bool $isDelete): static
    {
        $this->isDelete = $isDelete;

        return $this;
    }

    /**
     * @return Collection<int, AssetType>
     */
    public function getAssetTypes(): Collection
    {
        return $this->assetTypes;
    }

    public function addAssetType(AssetType $assetType): static
    {
        if (!$this->assetTypes->contains($assetType)) {
            $this->assetTypes->add($assetType);
        }

        return $this;
    }

    public function removeAssetType(AssetType $assetType): static
    {
        $this->assetTypes->removeElement($assetType);

        return $this;
    }

    /**
     * @param AssetType[] $assetTypes
     */
    public function syncAssetTypes(array $assetTypes): static
    {
        foreach ($this->assetTypes->toArray() as $existing) {
            if (!in_array($existing, $assetTypes, true)) {
                $this->removeAssetType($existing);
            }
        }

        foreach ($assetTypes as $assetType) {
            $this->addAssetType($assetType);
        }

        return $this;
    }
}
