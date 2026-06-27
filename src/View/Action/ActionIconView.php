<?php

namespace App\View\Action;

use App\Entity\Action;

/**
 * The single place that turns an action icon (a `ra-…` glyph + an optional colour
 * token) into HTML. Every site that shows an action icon — the in-game action
 * buttons, the observe panel, the spell tree, the admin workbench — renders
 * through here, so the markup stays consistent and a colour added in the editor
 * shows up everywhere for free.
 *
 * The colour resolves through {@see ActionIconPalette} (token → allowlisted hex),
 * so an admin-set value can never inject style into the player-facing combat log.
 */
final class ActionIconView
{
    /**
     * @param string             $icon       the `ra-…` glyph class
     * @param string|null        $colorToken a palette token, or null for default
     * @param string             $tag        wrapper element ('i' modern, 'span' legacy)
     * @param array<int, string> $classes    extra CSS classes (e.g. 'wb-item-icon')
     */
    public function render(string $icon, ?string $colorToken = null, string $tag = 'i', array $classes = []): string
    {
        $classAttr = htmlspecialchars(
            trim('ra ' . $icon . ($classes !== [] ? ' ' . implode(' ', $classes) : '')),
            ENT_QUOTES,
            'UTF-8'
        );

        $hex = ActionIconPalette::hex($colorToken);
        $style = $hex !== null ? ' style="color:' . $hex . '"' : '';

        return '<' . $tag . ' class="' . $classAttr . '"' . $style . '></' . $tag . '>';
    }

    /**
     * @param array<int, string> $classes
     */
    public function forAction(Action $action, string $tag = 'i', array $classes = []): string
    {
        return $this->render($action->getIcon(), $action->getIconColor(), $tag, $classes);
    }
}
