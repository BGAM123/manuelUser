<?php

namespace App\Entity;

use App\Repository\LocationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\Table(name: 'location')]
#[ORM\HasLifecycleCallbacks]
class Location
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['location:detail', 'asset:detail'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 8, nullable: true)]
    #[Groups(['location:detail', 'asset:detail'])]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 11, scale: 8, nullable: true)]
    #[Groups(['location:detail', 'asset:detail'])]
    private ?string $longitude = null;

    /**
     * Type de géométrie GeoJSON d'origine (Point, LineString, Polygon, MultiPolygon, MultiPoint...).
     * Toujours renseigné : "Point" par défaut pour une saisie manuelle ou une coordonnée isolée.
     * latitude/longitude restent le point représentatif (le point lui-même, ou le centroïde
     * pour une géométrie complexe) afin que les consommateurs existants n'aient rien à changer.
     */
    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['location:detail', 'asset:detail'])]
    private ?string $geometryType = null;

    /**
     * Géométrie GeoJSON complète ({"type": "...", "coordinates": [...]}), conservée telle quelle
     * pour les formes non ponctuelles (LineString, Polygon, MultiPolygon...). Null pour un Point
     * simple : latitude/longitude suffisent déjà, pas de duplication.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['location:detail', 'asset:detail'])]
    private ?array $geometry = null;

    #[ORM\Column]
    #[Groups(['location:detail', 'asset:detail'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['location:detail', 'asset:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'location', targetEntity: AssetLocation::class)]
    private Collection $assetLocations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->assetLocations = new ArrayCollection();
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

    public function getLatitude(): ?float
    {
        return $this->latitude !== null ? (float) $this->latitude : null;
    }

    public function setLatitude(?float $latitude): static
    {
        if ($latitude !== null) {
            if ($latitude < -90 || $latitude > 90) {
                throw new \InvalidArgumentException('La latitude doit être entre -90 et 90.');
            }
            $this->latitude = (string) $latitude;
        } else {
            $this->latitude = null;
        }
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude !== null ? (float) $this->longitude : null;
    }

    public function setLongitude(?float $longitude): static
    {
        if ($longitude !== null) {
            if ($longitude < -180 || $longitude > 180) {
                throw new \InvalidArgumentException('La longitude doit être entre -180 et 180.');
            }
            $this->longitude = (string) $longitude;
        } else {
            $this->longitude = null;
        }
        return $this;
    }

    public function getGeometryType(): ?string
    {
        return $this->geometryType;
    }

    public function setGeometryType(?string $geometryType): static
    {
        $this->geometryType = $geometryType;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getGeometry(): ?array
    {
        return $this->geometry;
    }

    /**
     * @param array<string, mixed>|null $geometry
     */
    public function setGeometry(?array $geometry): static
    {
        $this->geometry = $geometry;
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
     * @return Collection<int, AssetLocation>
     */
    public function getAssetLocations(): Collection
    {
        return $this->assetLocations;
    }

    public function addAssetLocation(AssetLocation $assetLocation): static
    {
        if (!$this->assetLocations->contains($assetLocation)) {
            $this->assetLocations->add($assetLocation);
            $assetLocation->setLocation($this);
        }

        return $this;
    }

    public function removeAssetLocation(AssetLocation $assetLocation): static
    {
        if ($this->assetLocations->removeElement($assetLocation)) {
            if ($assetLocation->getLocation() === $this) {
                $assetLocation->setLocation(null);
            }
        }

        return $this;
    }
}
