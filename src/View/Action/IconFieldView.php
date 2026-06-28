<?php

namespace App\View\Action;

/**
 * The action icon field: a trigger showing the current icon, a hidden input that
 * carries the value, a popover the client fills with a searchable grid of the
 * available RPG-Awesome icons (window.WB_ICONS, emitted once by the page), and a
 * row of colour swatches (the curated {@see ActionIconPalette}). Shared by the
 * create form and the config tab.
 */
final class IconFieldView
{
    use EscapesHtml;

    public function render(string $current, string $name = 'icon', ?string $currentColor = null, string $colorName = 'icon_color'): string
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
            . $this->colorSwatches($colorName, $currentColor)
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
