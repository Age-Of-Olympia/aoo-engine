<?php

namespace App\Simulation;

/**
 * The `data` object of a SimulatedPlayer. Explicit defaults are set as real
 * properties; any other property the combat code reads (a field the simulator
 * does not model) returns null instead of raising "Undefined property", so the
 * engine's many `$player->data->...` reads never warn.
 */
class LenientData extends \stdClass
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(array $properties)
    {
        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function __get(string $name): mixed
    {
        return null;
    }

    public function __isset(string $name): bool
    {
        return false;
    }
}
