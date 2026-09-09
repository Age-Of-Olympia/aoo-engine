<?php

namespace App\View\Classement;

/**
 * A ranking is rendered once and served from datas/public/classements
 * until the code that draws it changes: the file has no expiry of its
 * own, so a new column would otherwise never appear.
 */
final class RankingCache
{
    /**
     * @param string   $path   the cache file
     * @param string   $source the view's own file (__FILE__), whose age bounds the cache
     * @param callable $render echoes the ranking
     */
    public static function serve(string $path, string $source, callable $render): void
    {
        $freshUntil = max(filemtime($source), filemtime(__FILE__), filemtime(__DIR__ . '/PlayersTableView.php'));
        if (self::enabled() && file_exists($path) && filemtime($path) >= $freshUntil) {
            echo file_get_contents($path);
            return;
        }

        ob_start();
        $render();
        $data = ob_get_clean();

        file_put_contents($path, $data);
        echo $data;
    }

    /** Read through constant(): the define() is a switch, not a literal to fold. */
    private static function enabled(): bool
    {
        return (bool) constant('CACHED_CLASSEMENTS');
    }
}
