<?php

namespace App\View;

/**
 * Laying a multi-piece figure out on a grid, for whoever draws it.
 *
 * The palette thumbnail and the observation portrait both do this: normalise
 * the pieces to a top-left origin, flip the y axis — the board grows upwards,
 * the screen downwards — and give each piece a column and a row.
 *
 * Only the units differ afterwards: the palette sizes cells in pixels, the
 * portrait in percentages of whatever box it is given. That difference is
 * theirs; the geometry is shared and lives here.
 */
final class SceneryFigure
{
    /**
     * @param list<array{url: string, x: int, y: int}> $pieces board positions
     * @return array{w: int, h: int, cells: list<array{url: string, col: int, row: int}>}
     */
    public static function grid(array $pieces): array
    {
        if ($pieces === []) {
            return ['w' => 0, 'h' => 0, 'cells' => []];
        }

        $xs = array_column($pieces, 'x');
        $ys = array_column($pieces, 'y');

        $minX = min($xs);
        $maxY = max($ys);

        $cells = [];

        foreach ($pieces as $piece) {
            $cells[] = [
                'url' => $piece['url'],
                'col' => $piece['x'] - $minX,
                'row' => $maxY - $piece['y'],
            ];
        }

        return [
            'w'     => max($xs) - $minX + 1,
            'h'     => $maxY - min($ys) + 1,
            'cells' => $cells,
        ];
    }
}
