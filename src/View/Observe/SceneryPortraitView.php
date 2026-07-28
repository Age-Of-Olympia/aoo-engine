<?php

namespace App\View\Observe;

use App\Entity\EntityManagerFactory;
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

        $xs = array_map('intval', array_column($cells, 'x'));
        $ys = array_map('intval', array_column($cells, 'y'));

        $width = max($xs) - min($xs) + 1;
        $height = max($ys) - min($ys) + 1;

        $pieces = '';

        foreach ($cells as $cell) {
            /* Percentages rather than pixels: the figure then fits whatever
             * box the card gives it, and a fourteen-piece fort needs no more
             * room than a two-piece tower. */
            $left = round(((int) $cell['x'] - min($xs)) * 100 / $width, 4);
            /* y grows upwards on the board and downwards on screen. */
            $top = round((max($ys) - (int) $cell['y']) * 100 / $height, 4);

            $pieces .= '<img src="/img/foregrounds/' . htmlspecialchars((string) $cell['name'], ENT_QUOTES) . '.png"'
                . ' style="position:absolute;left:' . $left . '%;top:' . $top . '%;'
                . 'width:' . round(100 / $width, 4) . '%;height:' . round(100 / $height, 4) . '%;"'
                . ' alt="" loading="lazy" />';
        }

        /* Inline, so it wins over the stylesheet's `height: 100%` — a figure
         * keeps its proportions instead of being stretched to the box. */
        return '<span class="card-portrait card-portrait--figure"'
            . ' style="position:relative;display:block;width:100%;height:auto;'
            . 'aspect-ratio:' . $width . ' / ' . $height . ';">'
            . $pieces . '</span>';
    }
}
