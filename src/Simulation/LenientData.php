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
     * When true, reading an unmodelled property throws instead of returning null.
     * Off by default (so simulations stay tolerant); turn on while debugging to
     * surface a field the simulator should be modelling rather than masking.
     */
    public static bool $strict = false;

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
        if (self::$strict) {
            throw new \RuntimeException("SimulatedPlayer data has no '{$name}' property; model it in the simulation input.");
        }

        return null;
    }

    public function __isset(string $name): bool
    {
        return false;
    }
}
