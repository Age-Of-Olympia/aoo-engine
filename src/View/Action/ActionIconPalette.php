<?php

namespace App\View\Action;

/**
 * The curated palette of action-icon colours — the single source mapping a stored
 * token (e.g. "rouge") to a hex value. Storing a token (not a raw hex) keeps the
 * value an allowlist (no CSS injection from admin input into player-facing HTML)
 * and lets the whole game be restyled from one place. A null/empty/unknown token
 * means "default colour" (the icon inherits its surrounding text colour).
 */
final class ActionIconPalette
{
    /** @var array<string, array{label: string, hex: string}> token => label + hex */
    public const COLORS = [
        'rouge'  => ['label' => 'Rouge',  'hex' => '#c0392b'],
        'orange' => ['label' => 'Orange', 'hex' => '#e67e22'],
        'or'     => ['label' => 'Or',     'hex' => '#f1c40f'],
        'vert'   => ['label' => 'Vert',   'hex' => '#27ae60'],
        'bleu'   => ['label' => 'Bleu',   'hex' => '#2980b9'],
        'cyan'   => ['label' => 'Cyan',   'hex' => '#16a8b8'],
        'violet' => ['label' => 'Violet', 'hex' => '#8e44ad'],
        'rose'   => ['label' => 'Rose',   'hex' => '#e84393'],
        'blanc'  => ['label' => 'Blanc',  'hex' => '#ecf0f1'],
        'gris'   => ['label' => 'Gris',   'hex' => '#95a5a6'],
        'noir'   => ['label' => 'Noir',   'hex' => '#2c3e50'],
    ];

    /** The hex for a token, or null for the default colour / an unknown token. */
    public static function hex(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }

        return self::COLORS[$token]['hex'] ?? null;
    }

    /** Whether a token is storable (null/empty = default is allowed). */
    public static function isValid(?string $token): bool
    {
        return $token === null || $token === '' || isset(self::COLORS[$token]);
    }

    /**
     * @return array<string, array{label: string, hex: string}>
     */
    public static function all(): array
    {
        return self::COLORS;
    }
}
