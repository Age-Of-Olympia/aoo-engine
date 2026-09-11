<?php

namespace App\Factory;

use App\Entity\OutcomeInstruction;
use App\Service\Action\ActionTypeDiscovery;

class OutcomeInstructionFactory
{
    /**
     * Maps each STI discriminator key to its fully-qualified class.
     *
     * @return array<string, class-string>
     */
    public static function typeMap(): array
    {
        return ActionTypeDiscovery::typeMap(OutcomeInstruction::class);
    }

    public static function typeOf(object $instruction): string
    {
        return ActionTypeDiscovery::typeOf($instruction);
    }
}
