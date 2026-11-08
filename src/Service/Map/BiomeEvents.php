<?php

namespace App\Service\Map;

/** What a character's move does with the biomes it crosses */
enum BiomeEvents
{
    /** Leave the biome of the cell left, enter the biome of the cell reached: a step, a teleport */
    case Cross;

    /** Enter the biome of the cell reached only: the character appears there (creation, first placement) */
    case Arrive;

    /** Neither: a placement the admin chose to keep silent */
    case None;

    /**
     * The admin's answer to "trigger the biome entry and exit?", asked
     * before a placement: yes crosses, no or no answer keeps silent.
     */
    public static function fromAdminAnswer(mixed $answer): self
    {
        return $answer === '1' ? self::Cross : self::None;
    }
}
