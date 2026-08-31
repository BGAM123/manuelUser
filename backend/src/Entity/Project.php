<?php

// src/Entity/Project.php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un Projet regroupe les informations de planification d'une action patrimoniale
 * (nom, dates, statut) et peut mobiliser un ou plusieurs Utilisateurs.
 *
 * Project est le côté propriétaire de la relation ManyToMany avec User (table
 * project_user) : c'est le projet, en tant que ressource créée/gérée via l'API,
 * qui reçoit la liste des utilisateurs qui y travaillent (les responsables).
 *
 * Les responsables du projet sont représentés via la relation ManyToMany avec User
 * dans la table project_user.
 */
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['project:list', 'project:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom du projet est obligatoire.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le nom du projet ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['project:list', 'project:detail'])]
    private ?string $nom = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['project:list', 'project:detail'])]
    private ?string $description = null;

    // Champ responsable supprimé : les responsables sont représentés via la relation ManyToMany avec User

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de début est obligatoire.")]
    #[Groups(['project:list', 'project:detail'])]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'],
    )]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['project:list', 'project:detail'])]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'],
    )]
    private ?\DateTimeImmutable $dateFinPrevue = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotNull(message: "Le statut du projet est obligatoire.")]
    #[Groups(['project:list', 'project:detail'])]
    private string $statut = 'PLANIFIE';

    /**
     * Exercice financier/patrimonial (année) concerné par cette source de financement.
     * Fourni explicitement à la création si connu, sinon année courante par défaut.
     * Fixé à la création : non modifiable ensuite (voir ProjectRepository::applyPayloadToProject).
     */
    #[ORM\Column]
    #[Assert\NotNull(message: "L'exercice est obligatoire.")]
    // #[Assert\Range(min: 2000, max: 2100, notInRangeMessage: "L'exercice doit être une année valide (entre {{ min }} et {{ max }}).")]
    #[Groups(['project:list', 'project:detail'])]
    private ?int $exercice = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de création est obligatoire.")]
    #[Groups(['project:detail'])]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'd-m-Y H:i:s'],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTime::RFC3339],
    )]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de mise à jour est obligatoire.")]
    #[Groups(['project:detail'])]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'd-m-Y H:i:s'],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTime::RFC3339],
    )]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_delete', type: 'boolean', options: ['default' => false])]
    #[SerializedName('is_delete')]
    private bool $isDelete = false;

    /**
     * Côté propriétaire de la relation ManyToMany Project <-> User (table project_user).
     *
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'projects')]
    #[ORM\JoinTable(name: 'project_user')]
    // #[Groups(['project:detail'])]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
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

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFinPrevue(): ?\DateTimeImmutable
    {
        return $this->dateFinPrevue;
    }

    public function setDateFinPrevue(?\DateTimeImmutable $dateFinPrevue): static
    {
        $this->dateFinPrevue = $dateFinPrevue;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getExercice(): ?int
    {
        return $this->exercice;
    }

    public function setExercice(?int $exercice): static
    {
        $this->exercice = $exercice;

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
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        $this->users->removeElement($user);

        return $this;
    }
}
