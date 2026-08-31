<?php

namespace App\Entity;

/**
 * Implémentée par les entités dont createdBy/updatedBy doivent être peuplées
 * automatiquement par BlameableListener (src/EventListener/BlameableListener.php).
 */
interface BlameableInterface
{
    public function getCreatedBy(): ?User;

    public function setCreatedBy(?User $createdBy): static;

    public function getUpdatedBy(): ?User;

    public function setUpdatedBy(?User $updatedBy): static;
}
