<?php

namespace App\Service;

/**
 * Admin rights belong to whoever logged in (originalPlayerId), never to the
 * character played: handing a PNJ over grants nothing. A PNJ holds rights
 * only as a login of its own, with a password (PlayerOptionsService).
 * Disabled until `sudo` enables them for the session — an admin's own
 * character plays like any other — and impersonating keeps the level.
 * Played through a real player's character they stay disabled, unless forced
 * for that very character (`sudo admin -force`): no admin gesture lands on
 * a PJ by mistake.
 */
class AdminAuthorizationService
{
    public const SUDO_ADMIN = 'admin';
    public const SUDO_SUPERADMIN = 'superadmin';

    public static function DoAdminCheck(): void
    {
        if (!isset($_SESSION['playerId'])) {
            exit();
        }
        if (!self::isAdmin()) {
            exit(self::refusal('admin', 'isAdmin', 'Action réservée aux admin'));
        }
    }

    public static function DoSuperAdminCheck(): void
    {
        if (!isset($_SESSION['playerId'])) {
            exit();
        }
        if (!self::isSuperAdmin()) {
            exit(self::refusal('superadmin', 'isSuperAdmin', 'Action réservée aux super administrateurs'));
        }
    }

    public static function isAdmin(): bool
    {
        return in_array($_SESSION['sudo'] ?? null, [self::SUDO_ADMIN, self::SUDO_SUPERADMIN], true)
            && self::playedAllows()
            && self::loginHas('isAdmin');
    }

    public static function isSuperAdmin(): bool
    {
        return ($_SESSION['sudo'] ?? null) === self::SUDO_SUPERADMIN
            && self::playedAllows()
            && self::loginHas('isSuperAdmin');
    }

    /** Whether the login holds rights `sudo` could enable: opens the console. */
    public static function canElevate(): bool
    {
        return self::loginHas('isAdmin') || self::loginHas('isSuperAdmin');
    }

    /**
     * Enables the login's rights up to $level for the session; null
     * disables them. $force opens them on the real player's character
     * played right now, and on that one only.
     */
    public static function sudo(?string $level, bool $force = false): void
    {
        $needs = [self::SUDO_ADMIN => 'isAdmin', self::SUDO_SUPERADMIN => 'isSuperAdmin'];
        if ($level !== null && (!isset($needs[$level]) || !self::loginHas($needs[$level]))) {
            throw new \RuntimeException('Niveau ' . $level . ' non détenu par ce compte.');
        }

        unset($_SESSION['sudo'], $_SESSION['sudoForced']);
        if ($level !== null) {
            $_SESSION['sudo'] = $level;
            if ($force) {
                $_SESSION['sudoForced'] = (int) ($_SESSION['playerId'] ?? 0);
            }
        }
    }

    /** The admin button: the highest level held, forced on the character played. */
    public static function sudoHighest(): void
    {
        self::sudo(self::loginHas('isSuperAdmin') ? self::SUDO_SUPERADMIN : self::SUDO_ADMIN, true);
    }

    /** A PNJ (negative id) may carry the rights; a real player's character only when forced. */
    private static function playedAllows(): bool
    {
        $playedId = (int) ($_SESSION['playerId'] ?? 0);

        return $playedId < 0 || ($playedId !== 0 && ($_SESSION['sudoForced'] ?? null) === $playedId);
    }

    private static function refusal(string $level, string $option, string $plain): string
    {
        if (!self::loginHas($option)) {
            return $plain;
        }
        if (($_SESSION['sudo'] ?? null) !== null && !self::playedAllows()) {
            return $plain . ' : vous jouez un personnage joueur, incarnez un PNJ ou tapez « sudo ' . $level . ' -force ».';
        }

        return $plain . ' : tapez « sudo ' . $level . ' » dans la console.';
    }

    private static function loginHas(string $option): bool
    {
        $loginId = (int) ($_SESSION['originalPlayerId'] ?? 0);

        return $loginId !== 0 && (new PlayerOptionsService())->hasOption($loginId, $option) > 0;
    }
}
