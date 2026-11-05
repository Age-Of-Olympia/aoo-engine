<?php

namespace App\Module;

/**
 * A complete game feature an admin turns on or off, server by server.
 *
 * The code lists the modules; the `modules` table only holds their state
 * (Modules). A module stays here once its feature ships: it is not a
 * temporary flag.
 */
enum Module: string
{
    case Tutorial = 'tutorial';

    public function label(): string
    {
        return match ($this) {
            self::Tutorial => 'Tutoriel',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Tutorial => 'Le tutoriel des nouveaux joueurs. Désactivé : seuls les testeurs y ont accès.',
        };
    }
}
