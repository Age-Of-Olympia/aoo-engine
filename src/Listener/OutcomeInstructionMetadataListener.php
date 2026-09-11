<?php

namespace App\Listener;

use App\Entity\OutcomeInstruction;
use App\Service\Action\ActionTypeDiscovery;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/** Fills the OutcomeInstruction discriminator map from its folder at metadata load. */
class OutcomeInstructionMetadataListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();
        if ($metadata->getName() === OutcomeInstruction::class) {
            ActionTypeDiscovery::fillMetadata($metadata, OutcomeInstruction::class);
        }
    }
}
