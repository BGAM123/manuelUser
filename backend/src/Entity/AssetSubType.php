<?php

// src/Entity/AssetSubType.php

namespace App\Entity;

use App\Repository\AssetSubTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Sous-type de bien (ex. pour le type "Véhicule léger" : "Berline", "4x4", "Pick-up").
 *
 * AssetSubType est l'ENFANT dans la relation parent-enfant avec AssetType :
 * chaque AssetSubType est obligatoirement rattaché à un AssetType.
 * et un AssetSubType (3 niveaux de nomenclature).
 */
#[ORM\Entity(repositoryClass: AssetSubTypeRepository::class)]
#[UniqueEntity(fields: ['nom'], message: "Ce nom de sous-type de bien est déjà utilisé.")]
class AssetSubType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['asset_sub_type:list', 'asset_sub_type:detail', 'asset_type:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: "Le nom du sous-type de bien est obligatoire.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le nom du sous-type de bien ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['asset_sub_type:list', 'asset_sub_type:detail', 'asset_type:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['asset_sub_type:list', 'asset_sub_type:detail', 'asset_type:detail'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de création est obligatoire.")]
    #[Groups(['asset_sub_type:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de mise à jour est obligatoire.")]
    #[Groups(['asset_sub_type:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    #[Groups(['asset_sub_type:list', 'asset_sub_type:detail', 'asset_type:detail'])]
    private bool $isDelete = false;

    /**
     * Type de bien parent. Obligatoire : un sous-type ne peut pas exister sans type de bien.
     * onDelete: RESTRICT -> une suppression définitive (force=true) d'un AssetType échoue tant
     * que des sous-types y sont rattachés. La suppression logique, elle, réassigne les
     * sous-types actifs vers le type de bien par défaut avant de procéder (cf.
     * DefaultAssetReferencesService::reassignAssetSubTypesToDefaultAssetType), jamais bloquée.
     */
    #[ORM\ManyToOne(targetEntity: AssetType::class, inversedBy: 'subTypes')]
    #[ORM\JoinColumn(name: 'asset_type_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: "Le type de bien est obligatoire.")]
    private ?AssetType $assetType = null;

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

    public function getAssetType(): ?AssetType
    {
        return $this->assetType;
    }

    public function setAssetType(?AssetType $assetType): static
    {
        $this->assetType = $assetType;

        return $this;
    }

    public function getAssetTypeId(): ?int
    {
        return null === $this->assetType ? null : $this->assetType->getId();
    }

    #[SerializedName('asset_type_id')]
    #[Groups(['asset_sub_type:list', 'asset_sub_type:detail'])]
    public function getAssetTypeIdSerialized(): ?int
    {
        return $this->getAssetTypeId();
    }
}