<?php

namespace App\View\Action;

use App\Trait\EscapesHtmlTrait;
/**
 * The action icon field: a trigger showing the current icon, a hidden input that
 * carries the value, a popover the client fills with a searchable grid of the
 * available RPG-Awesome icons (window.WB_ICONS, emitted once by the page), and a
 * row of colour swatches (the curated {@see ActionIconPalette}). Shared by the
 * create form, the config tab and the effects admin — assets in
 * admin/js/icon-picker.js + admin/css/icon-picker.css.
 *
 * $withColor: entities without an icon colour column (effects) skip the
 * swatch row entirely rather than posting a stray colour field.
 */
final class IconFieldView
{
    use EscapesHtmlTrait;

    public function render(string $current, string $name = 'icon', ?string $currentColor = null, string $colorName = 'icon_color', bool $withColor = true): string
    {
        $current = trim($current);
        $label = $current !== '' ? $current : 'Choisir une icône';

        return '<div class="wb-icon-field" data-icon-picker>'
            . '<button type="button" class="wb-icon-trigger">'
            . '<span class="wb-icon-preview">' . (new ActionIconView())->render($current, $currentColor) . '</span>'
            . '<span class="wb-icon-label">' . $this->esc($label) . '</span>'
            . '</button>'
            . '<input type="hidden" class="wb-icon-input" name="' . $this->esc($name) . '" value="' . $this->esc($current) . '">'
            . '<div class="wb-icon-pop" hidden>'
            . '<input type="text" class="wb-icon-search" placeholder="Rechercher une icône…" autocomplete="off">'
            . '<div class="wb-icon-grid"></div>'
            . '</div>'
            . ($withColor ? $this->colorSwatches($colorName, $currentColor) : '')
            . '</div>';
    }

    private function colorSwatches(string $name, ?string $currentColor): string
    {
        $swatches = $this->swatch($name, '', 'Défaut', null, $currentColor);
        foreach (ActionIconPalette::all() as $token => $info) {
            $swatches .= $this->swatch($name, (string) $token, $info['label'], $info['hex'], $currentColor);
        }

        return '<div class="wb-icon-colors" role="radiogroup" aria-label="Couleur de l\'icône">' . $swatches . '</div>';
    }

    private function swatch(string $name, string $token, string $label, ?string $hex, ?string $currentColor): string
    {
        $checked = $token === ($currentColor ?? '') ? ' checked' : '';
        $dot = $hex !== null
            ? '<span class="wb-color-dot" style="background:' . $hex . '"></span>'
            : '<span class="wb-color-dot wb-color-dot--default"></span>';

        return '<label class="wb-color-swatch" title="' . $this->esc($label) . '">'
            . '<input type="radio" name="' . $this->esc($name) . '" value="' . $this->esc($token) . '"'
            . ($hex !== null ? ' data-hex="' . $hex . '"' : '') . $checked . '>'
            . $dot . '</label>';
    }

}
