<?php

namespace App\Service\Map;

/**
 * Fractal Perlin noise for biome grounds: the same (x, y, seed) always
 * gives the same value, so a brush painting in several strokes draws what
 * one stroke over the whole area would.
 *
 * Perlin: a unit gradient on every node of a square grid, drawn from the
 * node's position and the seed; the value at a point blends the dot
 * products of the four surrounding gradients with the offsets to the
 * point. Octaves add layers twice as fine and half as strong.
 */
final class GroundNoise
{
    private const OCTAVES = 4;

    /** 32-bit hash arithmetic, kept inside PHP's 64-bit ints */
    private const MASK = 0xFFFFFFFF;

    public function __construct(private int $seed)
    {
    }

    /** The noise at (x, y), within [-1, 1] */
    public function at(float $x, float $y): float
    {
        $sum = 0.0;
        $amplitude = 1.0;
        $amplitudes = 0.0;
        $frequency = 1.0;
        for ($octave = 0; $octave < self::OCTAVES; $octave++) {
            // A shift per octave, so the octaves' grids never line up (Perlin is 0 on its nodes)
            $shift = 0.37 * ($octave + 1);
            $sum += $amplitude * $this->perlin($x * $frequency + $shift, $y * $frequency + $shift, $octave);
            $amplitudes += $amplitude;
            $amplitude *= 0.5;
            $frequency *= 2.0;
        }

        return max(-1.0, min(1.0, $sum / $amplitudes));
    }

    private function perlin(float $x, float $y, int $octave): float
    {
        $x0 = (int) floor($x);
        $y0 = (int) floor($y);
        $dx = $x - $x0;
        $dy = $y - $y0;

        $top = self::lerp(
            $this->dot($x0, $y0, $dx, $dy, $octave),
            $this->dot($x0 + 1, $y0, $dx - 1, $dy, $octave),
            self::fade($dx)
        );
        $bottom = self::lerp(
            $this->dot($x0, $y0 + 1, $dx, $dy - 1, $octave),
            $this->dot($x0 + 1, $y0 + 1, $dx - 1, $dy - 1, $octave),
            self::fade($dx)
        );

        // A 2D Perlin value stays within ±√2/2: scaled to ±1
        return self::lerp($top, $bottom, self::fade($dy)) * M_SQRT2;
    }

    /** The node's gradient, dotted with the offset from the node to the point */
    private function dot(int $nodeX, int $nodeY, float $dx, float $dy, int $octave): float
    {
        $angle = $this->hash($nodeX, $nodeY, $octave) / self::MASK * 2 * M_PI;

        return cos($angle) * $dx + sin($angle) * $dy;
    }

    private function hash(int $x, int $y, int $octave): int
    {
        $h = ((($x * 374761393) & self::MASK) + (($y * 668265263) & self::MASK)
            + ((($this->seed + $octave * 1013) * 2246822519) & self::MASK)) & self::MASK;
        $h = (($h ^ ($h >> 13)) * 1274126177) & self::MASK;

        return $h ^ ($h >> 16);
    }

    /** Smoothstep of degree 5: no crease where two grid squares meet */
    private static function fade(float $t): float
    {
        return $t * $t * $t * ($t * ($t * 6 - 15) + 10);
    }

    private static function lerp(float $a, float $b, float $t): float
    {
        return $a + $t * ($b - $a);
    }
}
