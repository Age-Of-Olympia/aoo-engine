<?php

namespace App\Service;

use RuntimeException;

/**
 * The language the game is written in: a global option of the admin
 * dashboard (admin_settings, key `game_language`). Pages declare it as
 * their `lang`, and what follows the language reads it there: js/modal.js
 * applies French spacing to the alerts only in French.
 */
class GameLanguageService
{
    public const SETTING = 'game_language';
    public const DEFAULT = 'fr';

    /** Languages offered: code (as in <html lang>) => label shown on the dashboard */
    public const LANGUAGES = [
        'fr' => 'Français',
    ];

    /** Read once per request: it does not change while a page renders */
    private static ?string $currentCache = null;

    public function current(): string
    {
        if (self::$currentCache === null) {
            $value = (new AdminSettingsService())->get(self::SETTING, self::DEFAULT);
            self::$currentCache = isset(self::LANGUAGES[$value]) ? $value : self::DEFAULT;
        }

        return self::$currentCache;
    }

    public function set(string $language): void
    {
        if (!isset(self::LANGUAGES[$language])) {
            throw new RuntimeException('Langue inconnue.');
        }

        (new AdminSettingsService())->set(self::SETTING, $language);
        self::$currentCache = $language;
    }
}
