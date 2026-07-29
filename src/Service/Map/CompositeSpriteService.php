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

        if ($pieceImages === []) {
            return null;
        }

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
        imagealphablending($canvas, true);

        $drawn = 0;

        foreach ($this->grid($footprint) as $piece => [$col, $row]) {
            if (!isset($pieceImages[$piece])) {
                continue; /* a holed figure simply leaves that cell empty */
            }

            $sprite = $this->read($root . '/' . ltrim($pieceImages[$piece], '/'));

            if ($sprite === null) {
                continue;
            }

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
            imagedestroy($sprite);
            $drawn++;
        }

        if ($drawn === 0) {
            imagedestroy($canvas);

            return null;
        }

        $written = imagepng($canvas, $absolute);
        imagedestroy($canvas);

        return $written ? $webPath : null;
    }

    /**
     * Piece index => (column, row) on screen: y grows upwards on the board
     * and downwards on the image.
     *
     * @return array<int, array{0:int,1:int}>
     */
    private function grid(Footprint $footprint): array
    {
        $offsets = $footprint->offsets();
        $xs = array_column($offsets, 0);
        $ys = array_column($offsets, 1);

        $minX = min($xs);
        $maxY = max($ys);

        $cells = [];

        foreach ($offsets as $piece => [$dx, $dy]) {
            $cells[$piece] = [$dx - $minX, $maxY - $dy];
        }

        return $cells;
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
