<?php

namespace App\View\Hud;

use App\View\AnimatedLayersView;

/**
 * Board animation quality, as a video player's quality ladder: five
 * rates in one row, one click to change, a water swatch flowing at the
 * chosen rate and a line saying what it means. Per browser
 * (localStorage), applied by js/hud.js applyAnimationQuality; the same
 * markup serves the board options popover and the profile panel.
 */
final class AnimationQualityView
{
    /** Stored value => [label, hint]; 'smooth' animates every frame, 'off' pauses. */
    public const QUALITIES = [
        'smooth' => ['60', '60 images par seconde, le plus gourmand'],
        '30'     => ['30', '30 images par seconde, fluide'],
        '12'     => ['12', '12 images par seconde, recommandé'],
        '6'      => ['6', '6 images par seconde, le plus léger'],
        'off'    => ['', 'Aucune animation, plateau figé'],
    ];

    public static function render(): string
    {
        $default = (string) AnimatedLayersView::STEPS_PER_SECOND;

        $rates = '';
        foreach (self::QUALITIES as $quality => [$label, $hint]) {
            // PHP turns the numeric keys into integers
            $quality = (string) $quality;
            $checked = $quality === $default;
            $rates .= '<button type="button" role="radio" aria-checked="' . ($checked ? 'true' : 'false') . '" tabindex="' . ($checked ? '0' : '-1') . '"'
                . ' data-quality="' . $quality . '" data-hint="' . $hint . '"'
                . ($quality === 'off' ? ' aria-label="Pause"><span class="anim-quality-pause"></span>' : ' aria-label="' . $label . ' images par seconde">' . $label)
                . ($quality === 'smooth' ? '<span class="anim-quality-hd" aria-hidden="true">HD</span>' : '')
                . '</button>';
        }

        return '<div class="anim-quality" data-default="' . $default . '">'
            . '<div class="anim-quality-row">'
            . '<span class="anim-quality-preview" aria-hidden="true"><span class="anim-quality-preview-move" data-segments="1"></span></span>'
            . '<div class="anim-quality-rates" role="radiogroup" aria-label="Images par seconde des animations du plateau">' . $rates . '</div>'
            . '</div>'
            . '<p class="anim-quality-hint" aria-live="polite">' . self::QUALITIES[$default][1] . '</p>'
            . '</div>';
    }
}
