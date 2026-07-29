<?php

namespace App\Service\Map;

/**
 * The one picture the board draws a multi-cell figure with.
 *
 * Deliberately NOT the artist's whole-object drawing. That drawing is what
 * the Tiled palette offers, and it is right for that — it is the object as
 * drawn. On the board it decides what a player sees and what hides them, and
 * it has drifted: `triton_statue`'s is the horizontal mirror of its pieces,
 * `asteroide`'s claims a row the figure does not have. `footprint verify`
 * measures it; two families out of twenty-four lie.
 *
 * The composed sprite cannot: it is stitched from the very per-cell images
 * the board drew before, holes left transparent. So the board reads only
 * `_composed`, and a family without one keeps its pieces rather than being
 * drawn wrong.
 *
 * Composing is an authoring act (`footprint compose`), never a render one:
 * `CompositeSpriteService` globs the folder and stats every piece, which has
 * no business on a path walked on every board.
 */
final class EntitySpriteService
{
    /** @var array<string, string|null> memo, keyed "dir/family" */
    private static array $sprites = [];

    /**
     * Web path of the figure's picture, or null when there is none to trust.
     */
    public function spanImage(string $imageDir, string $family): ?string
    {
        if ($family === '') {
            return null;
        }

        $key = $imageDir . '/' . $family;

        if (array_key_exists($key, self::$sprites)) {
            return self::$sprites[$key];
        }

        $root = empty($_SERVER['DOCUMENT_ROOT'])
            ? dirname(__DIR__, 3)
            : $_SERVER['DOCUMENT_ROOT'];

        $composed = 'img/' . $imageDir . '/_composed/' . $family . '.png';

        if (is_file($root . '/' . $composed)) {
            return self::$sprites[$key] = $composed;
        }

        return self::$sprites[$key] = $this->composeOnce($imageDir, $family);
    }

    /**
     * Stitch a figure that has no picture yet — once, then never again.
     *
     * `img/` is not versioned, so a fresh deployment starts without any
     * composed sprite and the decor would draw piece by piece until someone
     * remembered a console command. It builds its own instead: the cost is
     * paid once per family, by whoever looks first, and the memo above keeps
     * a single render from asking twice.
     */
    private function composeOnce(string $imageDir, string $family): ?string
    {
        $footprint = (new EntityTypeFootprintService())->catalogue()[$family] ?? null;

        if ($footprint === null || $footprint->isSingleCell()) {
            return null;
        }

        $pieces = (new SceneryFootprintDeriver())->piecesOnDisk()[$family] ?? [];

        return (new CompositeSpriteService())->composedSprite($imageDir, $family, $footprint, $pieces);
    }

    /** Between two renders in one process, and between tests. */
    public static function forget(): void
    {
        self::$sprites = [];
    }
}
