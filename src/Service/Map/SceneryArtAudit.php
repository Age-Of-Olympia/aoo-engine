<?php

namespace App\Service\Map;

/**
 * Whether a family's whole-object drawing agrees with the pieces on the board.
 *
 * The drawing is about to matter: it is what a figure would be rendered from
 * if the board drew objects whole. But it is authored art, and it has drifted
 * — `triton_statue`'s is the horizontal MIRROR of its pieces, which no size
 * check can see: 100×150 for a 2×3 box is a perfect match on paper.
 *
 * So the comparison is done in pixels, slot by slot, against the pieces the
 * board actually draws today. A family whose drawing disagrees keeps drawing
 * per piece rather than shipping a silently flipped statue.
 */
final class SceneryArtAudit
{
    private const CELL = 50;

    /** Above this share of differing pixels, a slot is not the same picture. */
    private const TOLERANCE = 0.02;

    public const FAITHFUL = 'faithful';
    public const MIRRORED = 'mirrored';
    public const MISPLACED = 'misplaced';
    public const WRONG_SIZE = 'wrong-size';
    public const NO_ART = 'no-art';
    public const UNREADABLE = 'unreadable';

    /**
     * @param array<int, string> $pieceImages piece index => web path
     * @return array{verdict: string, worst: float, art: ?array{0:int,1:int}, detail: string}
     */
    public function audit(string $imageDir, string $family, Footprint $footprint, array $pieceImages): array
    {
        $root = empty($_SERVER['DOCUMENT_ROOT'])
            ? dirname(__DIR__, 3)
            : $_SERVER['DOCUMENT_ROOT'];

        $authored = $root . '/img/' . $imageDir . '/' . $family . '/' . $family . '.png';

        if (!is_file($authored)) {
            return $this->verdict(self::NO_ART, 0.0, null, 'aucune image d\'ensemble : la figure se compose depuis ses morceaux');
        }

        if (!function_exists('imagecreatefrompng')) {
            return $this->verdict(self::UNREADABLE, 0.0, null, 'GD absent');
        }

        $whole = @imagecreatefrompng($authored);

        if ($whole === false) {
            return $this->verdict(self::UNREADABLE, 0.0, null, 'image d\'ensemble illisible');
        }

        imagepalettetotruecolor($whole);

        $size = [imagesx($whole), imagesy($whole)];
        $expected = [$footprint->width() * self::CELL, $footprint->height() * self::CELL];

        if ($size !== $expected) {
            imagedestroy($whole);

            return $this->verdict(
                self::WRONG_SIZE,
                1.0,
                $size,
                sprintf(
                    'image %d×%d pour une figure de %d×%d cases (attendu %d×%d)',
                    $size[0],
                    $size[1],
                    $footprint->width(),
                    $footprint->height(),
                    $expected[0],
                    $expected[1]
                )
            );
        }

        $straight = $this->worstSlotDifference($whole, $footprint, $pieceImages, $root, false);
        $mirrored = $this->worstSlotDifference($whole, $footprint, $pieceImages, $root, true);

        imagedestroy($whole);

        if ($straight === null) {
            return $this->verdict(self::UNREADABLE, 0.0, $size, 'aucun morceau lisible sur le disque');
        }

        if ($straight <= self::TOLERANCE) {
            return $this->verdict(self::FAITHFUL, $straight, $size, 'l\'image d\'ensemble redit exactement les morceaux');
        }

        if ($mirrored !== null && $mirrored <= self::TOLERANCE) {
            return $this->verdict(
                self::MIRRORED,
                $straight,
                $size,
                'l\'image d\'ensemble est le miroir horizontal des morceaux posés'
            );
        }

        return $this->verdict(
            self::MISPLACED,
            $straight,
            $size,
            sprintf('les morceaux ne se retrouvent pas dans l\'image (au pire %.0f %% de pixels différents)', $straight * 100)
        );
    }

    /**
     * @return array{verdict: string, worst: float, art: ?array{0:int,1:int}, detail: string}
     */
    private function verdict(string $verdict, float $worst, ?array $art, string $detail): array
    {
        return ['verdict' => $verdict, 'worst' => $worst, 'art' => $art, 'detail' => $detail];
    }

    /**
     * Worst per-piece disagreement, each piece against the slot the figure
     * says it occupies. Null when no piece could be read at all.
     *
     * @param array<int, string> $pieceImages
     */
    private function worstSlotDifference(
        \GdImage $whole,
        Footprint $footprint,
        array $pieceImages,
        string $root,
        bool $mirror
    ): ?float {
        $reference = $mirror ? $this->mirrored($whole) : $whole;
        $worst = null;

        foreach ($footprint->grid() as $piece => [$col, $row]) {
            if (!isset($pieceImages[$piece])) {
                continue; /* a hole, or art the family never shipped */
            }

            $sprite = @imagecreatefrompng($root . '/' . ltrim($pieceImages[$piece], '/'));

            if ($sprite === false) {
                continue;
            }

            /* Half the art is palette-indexed, and `imagecolorat` hands back
             * an INDEX there and a colour elsewhere — comparing the two makes
             * every pixel differ, which reads as a damning 100 %. */
            imagepalettetotruecolor($sprite);

            $difference = $this->difference($sprite, $reference, $col * self::CELL, $row * self::CELL);
            imagedestroy($sprite);

            $worst = $worst === null ? $difference : max($worst, $difference);
        }

        if ($mirror) {
            imagedestroy($reference);
        }

        return $worst;
    }

    private function mirrored(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $flipped = imagecreatetruecolor($width, $height);
        imagealphablending($flipped, false);
        imagesavealpha($flipped, true);
        /* A copy then a flip, not a resample: resampling interpolates, and a
         * comparison counting differing pixels would read the blur as drift. */
        imagecopy($flipped, $image, 0, 0, 0, 0, $width, $height);
        imageflip($flipped, IMG_FLIP_HORIZONTAL);

        return $flipped;
    }

    /** Share of the cell's pixels that differ, alpha included. */
    private function difference(\GdImage $piece, \GdImage $reference, int $atX, int $atY): float
    {
        $differing = 0;

        for ($y = 0; $y < self::CELL; $y++) {
            for ($x = 0; $x < self::CELL; $x++) {
                if (
                    imagecolorat($piece, $x, $y)
                    !== imagecolorat($reference, $atX + $x, $atY + $y)
                ) {
                    $differing++;
                }
            }
        }

        return $differing / (self::CELL * self::CELL);
    }
}
