<?php

namespace App\View\Observe;

use App\Entity\EntityManagerFactory;
use App\View\SceneryFigure;
use Doctrine\DBAL\Connection;

/**
 * The portrait of a multi-cell scenery object: its whole figure, recomposed.
 *
 * A nine-piece library was shown by its top-left ninth, because the entity's
 * `portrait` is one image and a figure of that kind has no single one. The
 * pieces are read from the map rather than guessed from the family name:
 * three suffix conventions coexist on disk, and the map already holds the
 * exact file each cell carries.
 *
 * Laid out in percentages, so the figure fits the card's portrait column
 * whatever its size, and a fourteen-piece fort needs no more room than a
 * two-piece tower.
 */
final class SceneryPortraitView
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * The figure as positioned images, or null when there is nothing to
     * recompose — a single cell speaks for itself.
     */
    public function compose(int $entityId): ?string
    {
        $cells = $this->conn->fetchAllAssociative(
            'SELECT f.name, ec.x, ec.y
               FROM entity_cells ec
               JOIN map_foregrounds f ON f.coords_id = ec.coords_id
              WHERE ec.player_id = ?
              ORDER BY ec.piece',
            [$entityId]
        );

        if (count($cells) < 2) {
            return null;
        }

        $figure = SceneryFigure::grid(array_map(
            static fn(array $cell): array => [
                'url' => '/img/foregrounds/' . $cell['name'] . '.png',
                'x'   => (int) $cell['x'],
                'y'   => (int) $cell['y'],
            ],
            $cells
        ));

        $pieces = '';

        /* Percentages rather than pixels: the figure then fits whatever box
         * the card gives it, and a sixteen-piece fort needs no more room than
         * a two-piece tower. */
        foreach ($figure['cells'] as $cell) {
            $pieces .= '<img src="' . htmlspecialchars($cell['url'], ENT_QUOTES) . '"'
                . ' style="position:absolute;'
                . 'left:' . round($cell['col'] * 100 / $figure['w'], 4) . '%;'
                . 'top:' . round($cell['row'] * 100 / $figure['h'], 4) . '%;'
                . 'width:' . round(100 / $figure['w'], 4) . '%;'
                . 'height:' . round(100 / $figure['h'], 4) . '%;"'
                . ' alt="" loading="lazy" />';
        }

        /* Inline, so it wins over the stylesheet's `height: 100%` — a figure
         * keeps its proportions instead of being stretched to the box. */
        return '<span class="card-portrait card-portrait--figure"'
            . ' style="position:relative;display:block;width:100%;height:auto;'
            . 'aspect-ratio:' . $figure['w'] . ' / ' . $figure['h'] . ';">'
            . $pieces . '</span>';
    }
}
