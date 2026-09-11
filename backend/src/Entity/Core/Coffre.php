<?php

namespace App\Entity\Core;

use App\Repository\Core\CoffreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CoffreRepository::class)]
#[ORM\Table(name: 'core_coffre')]
#[ORM\UniqueConstraint(name: 'unique_nom_salle', columns: ['nom', 'id_salle'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['nom', 'idSalle'], message: 'Ce nom de coffre existe déjà dans cette salle.')]
class Coffre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, nullable: false)]
    #[Groups(['GetCollection:Coffre', 'Get:Coffre'])]
    private ?int $id = null;

    #[ORM\Column(name: 'nom', type: Types::STRING, length: 255, nullable: false)]
    #[Groups(['GetCollection:Coffre', 'Get:Coffre'])]
    private ?string $nom = null;

    #[ORM\Column(name: 'nombre_place_actuelle', type: Types::INTEGER, nullable: false, options: ['default' => 0])]
    #[Groups(['GetCollection:Coffre', 'Get:Coffre'])]
    private int $nombrePlaceActuelle = 0;

    #[ORM\Column(name: 'taille_maximale', type: Types::INTEGER, nullable: false, options: ['default' => 20])]
    #[Groups(['GetCollection:Coffre', 'Get:Coffre'])]
    private int $tailleMaximale = 20;

    #[ORM\Column(name: 'id_salle', type: Types::INTEGER, nullable: false)]
    #[Groups(['GetCollection:Coffre', 'Get:Coffre'])]
    private ?int $idSalle = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, nullable: false, options: ['default' => true])]
    #[Groups(['GetCollection:Coffre', 'Get:Coffre'])]
    private bool $isActive = true;

    #[ORM\Column(name: 'is_delete', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['GetCollection:Coffre', 'Get:Coffre'])]
    private bool $isDelete = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: false)]
    #[Groups(['GetCollection:Coffre', 'Get:Coffre'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: false)]
    #[Groups(['GetCollection:Coffre', 'Get:Coffre'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getNombrePlaceActuelle(): int
    {
        return $this->nombrePlaceActuelle;
    }

    public function setNombrePlaceActuelle(int $nombrePlaceActuelle): static
    {
        $this->nombrePlaceActuelle = $nombrePlaceActuelle;

        return $this;
    }

    public function getTailleMaximale(): int
    {
        return $this->tailleMaximale;
    }

    public function setTailleMaximale(int $tailleMaximale): static
    {
        $this->tailleMaximale = $tailleMaximale;

        return $this;
    }

    public function getIdSalle(): ?int
    {
        return $this->idSalle;
    }

    public function setIdSalle(int $idSalle): static
    {
        $this->idSalle = $idSalle;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    /**
     * Vérifie si le coffre est plein
     */
    public function isPlein(): bool
    {
        return $this->nombrePlaceActuelle >= $this->tailleMaximale;
    }

    /**
     * Retourne le nombre de places disponibles
     */
    public function getPlacesDisponibles(): int
    {
        return max(0, $this->tailleMaximale - $this->nombrePlaceActuelle);
    }

    /**
     * Retourne le taux de remplissage en pourcentage
     */
    public function getTauxRemplissage(): float
    {
        if ($this->tailleMaximale === 0) {
            return 0;
        }
        return ($this->nombrePlaceActuelle / $this->tailleMaximale) * 100;
    }

    /**
     * Incrémente le nombre de places actuelles
     * 
     * @throws \RuntimeException Si le coffre est déjà plein
     */
    public function incrementerPlace(): static
    {
        if ($this->isPlein()) {
            throw new \RuntimeException(
                'Impossible d\'ajouter une archive dans ce coffre car il a déjà atteint la taille maximale. ' .
                'Capacité actuelle: ' . $this->nombrePlaceActuelle . '/' . $this->tailleMaximale
            );
        }

        $this->nombrePlaceActuelle++;

        return $this;
    }

    /**
     * Décrémente le nombre de places actuelles
     */
    public function decrementerPlace(): static
    {
        if ($this->nombrePlaceActuelle > 0) {
            $this->nombrePlaceActuelle--;
        }

        return $this;
    }
}
