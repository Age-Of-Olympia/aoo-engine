<?php

namespace App\Listener;

use App\Entity\Action;
use App\Service\Action\ActionTypeDiscovery;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/** Fills the Action discriminator map from its folder at metadata load. */
class ActionMetadataListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();
        if ($metadata->getName() === Action::class) {
            ActionTypeDiscovery::fillMetadata($metadata, Action::class);
        }
    }
}
