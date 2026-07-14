<?php

namespace App\Service;

use RuntimeException;

/**
 * Format d'affichage des dates du jeu — option globale du tableau de
 * bord admin (admin_settings, clé `date_format`).
 *
 * Passerelle unique : tout affichage de date côté jeu doit passer par
 * format() pour suivre l'option. Aujourd'hui : chroniques de l'accueil
 * et admin de l'accueil ; les date() hérités migrent au fil de l'eau.
 * La SAISIE admin reste au format français (parseFrench), quel que
 * soit le format d'affichage choisi.
 */
class DateFormatService
{
    public const SETTING = 'date_format';
    public const DEFAULT = 'long_fr';

    /** Formats proposés : code => libellé montré dans le tableau de bord. */
    public const FORMATS = [
        'long_fr'  => 'Français long — « 10 juillet 2026 »',
        'short_fr' => 'Français court — « 10/07/2026 »',
        'iso'      => 'ISO — « 2026-07-10 »',
    ];

    private const FRENCH_MONTHS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    /** Option lue une fois par requête (elle ne change pas en cours de rendu). */
    private static ?string $currentCache = null;

    /** Format actif (code de self::FORMATS). */
    public function current(): string
    {
        if (self::$currentCache === null) {
            $value = (new AdminSettingsService())->get(self::SETTING, self::DEFAULT);
            self::$currentCache = isset(self::FORMATS[$value]) ? $value : self::DEFAULT;
        }

        return self::$currentCache;
    }

    /** Change le format actif (tableau de bord admin). */
    public function set(string $format): void
    {
        if (!isset(self::FORMATS[$format])) {
            throw new RuntimeException('Format de date inconnu.');
        }

        (new AdminSettingsService())->set(self::SETTING, $format);
        self::$currentCache = $format;
    }

    /** « AAAA-MM-JJ » → la date dans le format d'affichage actif. */
    public function format(string $isoDate): string
    {
        [$year, $month, $day] = array_map('intval', explode('-', $isoDate));

        return match ($this->current()) {
            'short_fr' => sprintf('%02d/%02d/%04d', $day, $month, $year),
            'iso'      => sprintf('%04d-%02d-%02d', $year, $month, $day),
            default    => $day . ' ' . (self::FRENCH_MONTHS[$month] ?? '') . ' ' . $year,
        };
    }

    /**
     * « JJ/MM/AAAA » (saisie admin, format français) → « AAAA-MM-JJ ».
     * Accepte aussi l'ISO tel quel. Lève si la date n'existe pas.
     */
    public static function parseFrench(string $input): string
    {
        $input = trim($input);

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $input, $m)) {
            [, $day, $month, $year] = array_map('intval', $m);
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $input, $m)) {
            [, $year, $month, $day] = array_map('intval', $m);
        } else {
            throw new RuntimeException('Date invalide : utilisez le format JJ/MM/AAAA.');
        }

        if (!checkdate($month, $day, $year)) {
            throw new RuntimeException('Cette date n\'existe pas (JJ/MM/AAAA).');
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
