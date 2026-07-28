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
 */
final class SceneryPortraitView
{
    /** Portraits are a fixed box; the figure is scaled to sit inside it. */
    private const BOX = 150;

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
        $side = (int) floor(self::BOX / max($width, $height));

        if ($side < 1) {
            return null;
        }

        $pieces = '';

        foreach ($cells as $cell) {
            /* y grows upwards on the board and downwards on screen. */
            $left = ((int) $cell['x'] - min($xs)) * $side;
            $top = (max($ys) - (int) $cell['y']) * $side;

            $pieces .= '<img src="/img/foregrounds/' . htmlspecialchars((string) $cell['name'], ENT_QUOTES) . '.png"'
                . ' style="position:absolute;left:' . $left . 'px;top:' . $top . 'px;'
                . 'width:' . $side . 'px;height:' . $side . 'px;" alt="" loading="lazy" />';
        }

        return '<span class="card-portrait card-portrait--figure"'
            . ' style="position:relative;display:inline-block;'
            . 'width:' . ($width * $side) . 'px;height:' . ($height * $side) . 'px;">'
            . $pieces . '</span>';
    }
}
