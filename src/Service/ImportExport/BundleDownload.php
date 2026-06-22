<?php

namespace App\Service\ImportExport;

/**
 * Computes the download filename for an exported bundle. Centralised (and unit
 * tested) so the value going into the Content-Disposition header is always a
 * safe, predictable `.json` name with no header-injection or path characters.
 */
final class BundleDownload
{
    /**
     * @param string      $objectType the bundle's objectType (e.g. "action")
     * @param string|null $name       a single object's natural key, or null for a full export
     */
    public static function filename(string $objectType, ?string $name = null): string
    {
        $type = self::slug($objectType, 'bundle');

        if ($name === null) {
            return $type . '-bundle.json';
        }

        return $type . '-' . self::slug($name, 'unnamed') . '.json';
    }

    /**
     * Lowercase, ASCII-safe slug: anything outside [a-z0-9._-] becomes a dash,
     * runs collapse, edges trim. Falls back to $fallback when nothing remains.
     */
    private static function slug(string $value, string $fallback): string
    {
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9._-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : $fallback;
    }
}
