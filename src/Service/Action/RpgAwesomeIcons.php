<?php

namespace App\Service\Action;

/**
 * The list of RPG-Awesome glyph icons (ra-*), parsed from the shipped stylesheet
 * so the action icon picker always matches the font that is actually loaded.
 * Only glyph rules (".ra-x:before { content }") count — the layout modifiers
 * (ra-lg, ra-spin, ra-fw, …) are not icons.
 */
final class RpgAwesomeIcons
{
    private ?string $cssPath;

    public function __construct(?string $cssPath = null)
    {
        $this->cssPath = $cssPath;
    }

    /**
     * @return list<string> sorted, unique icon class names (e.g. "ra-crossed-swords")
     */
    public function all(): array
    {
        $path = $this->cssPath ?? (($_SERVER['DOCUMENT_ROOT'] ?? '') . '/css/rpg-awesome.min.css');
        $css = is_file($path) ? (string) file_get_contents($path) : '';

        preg_match_all('/\.ra-([a-z0-9-]+):before/', $css, $matches);
        $icons = array_values(array_unique(array_map(static fn (string $name): string => 'ra-' . $name, $matches[1])));
        sort($icons);

        return $icons;
    }
}
