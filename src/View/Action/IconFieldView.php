<?php

namespace App\View\Action;

/**
 * The action icon field: a trigger showing the current icon, a hidden input that
 * carries the value, and a popover the client fills with a searchable grid of the
 * available RPG-Awesome icons (window.WB_ICONS, emitted once by the page). Shared
 * by the create form and the config tab.
 */
final class IconFieldView
{
    public function render(string $current, string $name = 'icon'): string
    {
        $current = trim($current);
        $label = $current !== '' ? $current : 'Choisir une icône';

        return '<div class="wb-icon-field" data-icon-picker>'
            . '<button type="button" class="wb-icon-trigger">'
            . '<span class="wb-icon-preview"><i class="ra ' . $this->esc($current) . '"></i></span>'
            . '<span class="wb-icon-label">' . $this->esc($label) . '</span>'
            . '</button>'
            . '<input type="hidden" class="wb-icon-input" name="' . $this->esc($name) . '" value="' . $this->esc($current) . '">'
            . '<div class="wb-icon-pop" hidden>'
            . '<input type="text" class="wb-icon-search" placeholder="Rechercher une icône…" autocomplete="off">'
            . '<div class="wb-icon-grid"></div>'
            . '</div>'
            . '</div>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
