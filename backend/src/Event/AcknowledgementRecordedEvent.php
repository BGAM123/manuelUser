<?php

namespace App\Event;

use App\Entity\AcknowledgementOfReceipt;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatché après l'enregistrement d'un accusé de réception. Point d'extension pour tout
 * effet de bord réagissant à un accusé (boucle de rétroaction vers l'émetteur initial,
 * futurs modules) sans coupler AcknowledgementService à ces effets.
 */
final class AcknowledgementRecordedEvent extends Event
{
    public function __construct(
        private readonly AcknowledgementOfReceipt $acknowledgement,
        private readonly ?User $sender,
    ) {
    }

    public function getAcknowledgement(): AcknowledgementOfReceipt
    {
        return $this->acknowledgement;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }
}
