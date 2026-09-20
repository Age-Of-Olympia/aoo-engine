<?php

namespace App\Service\Map;

use App\Service\RaceService;

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

    /** @var array<string, Footprint>|null request-wide: one map read, not one per type */
    private static ?array $catalogue = null;

    /**
     * The picture of a multi-cell TYPE, in the folder its kind keeps its
     * images in ({@see \App\Entity\Race::imageDir()}) — the one path for
     * a scenery figure, a building or a character with a cut-out.
     */
    public function spriteOf(string $type): ?string
    {
        foreach ($this->dirsOf($type) as $dir) {
            $image = $this->spanImage($dir, $type);

            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }

    /**
     * The kind's folder first, then every other folder pieces live in:
     * pieces left where a type used to belong — a trade hall was scenery,
     * its pieces are still in img/foregrounds — draw it all the same.
     *
     * @return list<string> empty for a name the catalogue does not know
     */
    public function dirsOf(string $type): array
    {
        $own = $this->imageDirOf($type);

        return $own === null ? [] : array_values(array_unique(array_merge([$own], $this->pieceDirs())));
    }

    /** null for a name the catalogue does not know. */
    public function imageDirOf(string $type): ?string
    {
        return (new RaceService())->getImageDirMap()[$type] ?? null;
    }

    /** @return list<string> every folder a type may keep pieces in */
    public function pieceDirs(): array
    {
        return array_values(array_unique((new RaceService())->getImageDirMap()));
    }

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
        self::$catalogue ??= (new EntityTypeFootprintService())->catalogue();
        $footprint = self::$catalogue[$family] ?? null;

        if ($footprint === null || $footprint->isSingleCell()) {
            return null;
        }

        $pieces = (new SceneryFootprintDeriver())->piecesOnDisk($imageDir)[$family] ?? [];

        return (new CompositeSpriteService())->composedSprite($imageDir, $family, $footprint, $pieces);
    }

    /** Between two renders in one process, and between tests. */
    public static function forget(): void
    {
        self::$sprites = [];
        self::$catalogue = null;
    }
}
