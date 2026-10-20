<?php

namespace App\Service;

use Classes\Player;

/**
 * Admin rights belong to the character, not the session: every check reads the
 * options of the character currently played, so switching character drops them.
 */
class AdminAuthorizationService
{
    public static function DoAdminCheck(): void
    {
        if (!isset($_SESSION['playerId'])) {
            exit();
        }
        if (!self::isAdmin()) {
            exit('Action réservée aux admin');
        }
    }

    public static function DoSuperAdminCheck(): void
    {
        if (!isset($_SESSION['playerId'])) {
            exit();
        }
        if (!self::isSuperAdmin()) {
            exit('Action réservée aux super administrateurs');
        }
    }

    public static function isAdmin(): bool
    {
        return self::currentPlayerHas('isAdmin');
    }

    public static function isSuperAdmin(): bool
    {
        return self::currentPlayerHas('isSuperAdmin');
    }

    private static function currentPlayerHas(string $option): bool
    {
        return isset($_SESSION['playerId'])
            && (bool) (new Player((int) $_SESSION['playerId']))->have_option($option);
    }
}
