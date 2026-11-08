<?php

namespace App\View;

use App\Interface\HasMatriculeInterface;
use App\Service\AdminAuthorizationService;

/**
 * The matricule players see is display_id, numbered per type: a PNJ and a
 * player can share one. The console takes the id, so an admin under sudo
 * gets both, to talk with players and to type commands.
 */
final class MatriculeView
{
    public static function label(HasMatriculeInterface $who): string
    {
        return self::forIds((int) $who->getId(), $who->getDisplayId());
    }

    /** For a bare SQL row, with no object to hand over. */
    public static function forIds(int $id, int $displayId): string
    {
        return $id !== $displayId && AdminAuthorizationService::isAdmin()
            ? $displayId . ' · id ' . $id
            : (string) $displayId;
    }
}
