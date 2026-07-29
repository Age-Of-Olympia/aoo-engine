<?php

namespace App\Service\Map;

/**
 * When the code that draws the board last changed.
 *
 * A player's board is cached whole, per viewer, with no expiry: the file
 * exists, so it is served. That was fine while the render never changed
 * shape, and became a trap the day it did — someone who does not move keeps
 * a pre-change board forever, and the only cure was remembering a console
 * command after every deployment.
 *
 * A cache older than the renderer is stale. Nothing to bump by hand, nothing
 * to run after a deployment: a board drawn by code that no longer exists is
 * simply redrawn.
 */
final class BoardRenderStamp
{
    /**
     * What the board is drawn by. A file missing from here only means its
     * change does not force a redraw — the old behaviour, never a wrong one.
     */
    private const SOURCES = [
        'Classes/View.php',
        'src/View/MainView.php',
        'src/Service/Map/*.php',
    ];

    private static ?int $stamp = null;

    /** Newest mtime among the renderer's own sources. */
    public static function renderedAt(): int
    {
        if (self::$stamp !== null) {
            return self::$stamp;
        }

        $root = empty($_SERVER['DOCUMENT_ROOT'])
            ? dirname(__DIR__, 3)
            : $_SERVER['DOCUMENT_ROOT'];

        $newest = 0;

        foreach (self::SOURCES as $source) {
            foreach (glob($root . '/' . $source) ?: [] as $file) {
                $newest = max($newest, (int) @filemtime($file));
            }
        }

        return self::$stamp = $newest;
    }

    /** True when a cached board predates the code that would draw it now. */
    public static function isStale(string $cachedFile): bool
    {
        if (!is_file($cachedFile)) {
            return true;
        }

        $stamp = self::renderedAt();

        return $stamp > 0 && (int) @filemtime($cachedFile) < $stamp;
    }

    /** Between tests, and after a deployment inside one long-lived process. */
    public static function forget(): void
    {
        self::$stamp = null;
    }
}
