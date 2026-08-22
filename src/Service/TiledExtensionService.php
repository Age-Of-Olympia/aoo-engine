<?php

namespace App\Service;

/**
 * Minimum version of the Tiled extension this instance talks to.
 *
 * The extension announces its version in the X-AoO-Tiled-Version header
 * and api/admin/map/* refuses anything below the bar. An extension older
 * than the bar speaks a protocol the server no longer serves and misreads
 * the answers as success, so refusing it — with a message saying what to
 * download — beats letting it work on stale assumptions.
 *
 * The bar is an admin setting rather than a constant: raising it after an
 * extension release is a dashboard edit, no deployment, and each instance
 * carries its own (experimental may demand newer than production).
 */
final class TiledExtensionService
{
    public const SETTING = 'tiled_min_extension';

    /** First release announcing its version: anything older is unidentifiable, so never accepted. */
    public const FIRST_VERSIONED = '0.4.0';

    public const DOWNLOAD_URL = 'https://gitlab.com/age-of-olympia/aoo-tiled-extension'
        . '/-/releases/permalink/latest/downloads/aoo-tiled-extension.zip';

    /** Shape of a version number: one to three figures, the tag's leading v tolerated. */
    private const VERSION_PATTERN = '/^v?\d+(\.\d+){0,2}$/';

    private AdminSettingsService $settings;

    public function __construct(?AdminSettingsService $settings = null)
    {
        $this->settings = $settings ?? new AdminSettingsService();
    }

    /** The bar in force. An unreadable stored value falls back rather than locking every editor out. */
    public function minimum(): string
    {
        $stored = self::normalize($this->settings->get(self::SETTING, self::FIRST_VERSIONED));

        return $stored !== '' ? $stored : self::FIRST_VERSIONED;
    }

    /** @throws \RuntimeException when the version is not a readable number */
    public function setMinimum(string $version): void
    {
        $clean = self::normalize($version);

        if ($clean === '') {
            throw new \RuntimeException(
                'Version invalide : « ' . $version . ' » (attendu un numéro comme 0.4.0, 1.2 ou 2).'
            );
        }

        $this->settings->set(self::SETTING, $clean);
    }

    /** An announced version in comparable form; '' when absent or unreadable. */
    public static function normalize(?string $announced): string
    {
        $version = trim((string) $announced);

        return preg_match(self::VERSION_PATTERN, $version) ? ltrim($version, 'vV') : '';
    }

    public function accepts(string $announced): bool
    {
        return $announced !== '' && version_compare($announced, $this->minimum(), '>=');
    }

    /** Read by the mapmaker inside Tiled, so it says what to do, not only no. */
    public function refusalMessage(string $announced): string
    {
        $seen = $announced !== ''
            ? 'v' . $announced
            : 'version non annoncée, donc antérieure à la v' . self::FIRST_VERSIONED;

        return 'Extension Tiled trop ancienne (' . $seen . ') : cette instance demande la v'
            . $this->minimum() . ' ou plus récente. Télécharger ' . self::DOWNLOAD_URL
            . ' puis dézipper par-dessus le dossier « aoo » des extensions de Tiled, '
            . 'redémarrer Tiled et re-puller les plans ouverts.';
    }
}
