<?php

namespace App\Service\Map;

/**
 * The one image a multi-piece object shows in the Tiled palette.
 *
 * An artist sometimes ships a whole-object image next to the pieces
 * (`img/foregrounds/hutte_pilotis/hutte_pilotis.png`). Most families have
 * none — 25 out of the ~130 on disk — and those appeared in the palette as
 * loose pieces, which is what made an anvil impossible to place as one thing.
 *
 * When it is missing, it is composed from the pieces the cut-out names, and
 * cached. Deriving it beats waiting for an asset: the figure is already known
 * from the catalogue, holes included.
 */
final class CompositeSpriteService
{
    /** Composed sprites live apart, so a rebuild never touches artist files. */
    private const CACHE_DIR = '_composed';

    private const CELL = 50;

    /**
     * Web path of the object's whole image, composing it if needed.
     *
     * @param array<int, string> $pieceImages piece index => web path
     * @return string|null null when nothing can be drawn
     */
    public function spriteFor(string $imageDir, string $family, Footprint $footprint, array $pieceImages): ?string
    {
        /* Empty, not absent, outside the web: `??` would let '' through and
         * the composition would write to the filesystem root. */
        $root = empty($_SERVER['DOCUMENT_ROOT'])
            ? dirname(__DIR__, 3)
            : $_SERVER['DOCUMENT_ROOT'];

        /* The artist's own image wins: it is the object as drawn, not as
         * reassembled. */
        $authored = 'img/' . $imageDir . '/' . $family . '/' . $family . '.png';

        if (is_file($root . '/' . $authored)) {
            return $authored;
        }

        return $this->composedSprite($imageDir, $family, $footprint, $pieceImages);
    }

    /**
     * The stitched picture, ignoring whatever the artist drew.
     *
     * What the board wants: the authored drawing is a claim about the figure,
     * this one is made of the figure's own cells. Two families out of
     * twenty-four have a drawing that contradicts them — see `SceneryArtAudit`.
     *
     * @param array<int, string> $pieceImages piece index => web path
     */
    public function composedSprite(string $imageDir, string $family, Footprint $footprint, array $pieceImages): ?string
    {
        if ($pieceImages === []) {
            return null;
        }

        $root = empty($_SERVER['DOCUMENT_ROOT'])
            ? dirname(__DIR__, 3)
            : $_SERVER['DOCUMENT_ROOT'];

        $cached = 'img/' . $imageDir . '/' . self::CACHE_DIR . '/' . $family . '.png';
        $absolute = $root . '/' . $cached;

        if (is_file($absolute) && !$this->staleAgainst($absolute, $root, $pieceImages)) {
            return $cached;
        }

        return $this->compose($absolute, $cached, $root, $footprint, $pieceImages);
    }

    /** A piece redrawn since the composition means the cache lies. */
    private function staleAgainst(string $cached, string $root, array $pieceImages): bool
    {
        $composedAt = (int) filemtime($cached);

        foreach ($pieceImages as $path) {
            $file = $root . '/' . ltrim($path, '/');

            if (is_file($file) && (int) filemtime($file) > $composedAt) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $pieceImages
     */
    private function compose(
        string $absolute,
        string $webPath,
        string $root,
        Footprint $footprint,
        array $pieceImages
    ): ?string {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $dir = dirname($absolute);

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $canvas = imagecreatetruecolor($footprint->width() * self::CELL, $footprint->height() * self::CELL);

        if ($canvas === false) {
            return null;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefilledrectangle(
            $canvas,
            0,
            0,
            imagesx($canvas) - 1,
            imagesy($canvas) - 1,
            imagecolorallocatealpha($canvas, 0, 0, 0, 127)
        );
        /* Blending stays OFF: a piece must land on the canvas verbatim, alpha
         * included. Composited instead, a semi-transparent pixel comes out
         * darker than the piece the board drew — the stitched picture would
         * no longer BE the pieces. */

        $drawn = 0;

        foreach ($footprint->grid() as $piece => [$col, $row]) {
            if (!isset($pieceImages[$piece])) {
                continue; /* a holed figure simply leaves that cell empty */
            }

            $sprite = $this->read($root . '/' . ltrim($pieceImages[$piece], '/'));

            if ($sprite === null) {
                continue;
            }

            /* A piece already at cell size is copied, not resampled:
             * resampling interpolates, and the stitched picture has to be the
             * pieces themselves — it is what makes it trustworthy. */
            if (imagesx($sprite) === self::CELL && imagesy($sprite) === self::CELL) {
                imagecopy($canvas, $sprite, $col * self::CELL, $row * self::CELL, 0, 0, self::CELL, self::CELL);
            } else {
                imagecopyresampled(
                    $canvas,
                    $sprite,
                    $col * self::CELL,
                    $row * self::CELL,
                    0,
                    0,
                    self::CELL,
                    self::CELL,
                    imagesx($sprite),
                    imagesy($sprite)
                );
            }
            imagedestroy($sprite);
            $drawn++;
        }

        if ($drawn === 0) {
            imagedestroy($canvas);

            return null;
        }

        /* Written aside then renamed, which is atomic on one filesystem: two
         * boards composing the same missing figure at once would otherwise
         * each write half a PNG into the file the other is reading. */
        $temporary = $absolute . '.' . getmypid() . '.tmp';
        $written = imagepng($canvas, $temporary);
        imagedestroy($canvas);

        if (!$written || !@rename($temporary, $absolute)) {
            @unlink($temporary);

            return null;
        }

        return $webPath;
    }

    private function read(string $file): ?\GdImage
    {
        if (!is_file($file)) {
            return null;
        }

        $image = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'png'  => @imagecreatefrompng($file),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            'gif'  => @imagecreatefromgif($file),
            'jpg', 'jpeg' => @imagecreatefromjpeg($file),
            default => false,
        };

        return $image === false ? null : $image;
    }
}
