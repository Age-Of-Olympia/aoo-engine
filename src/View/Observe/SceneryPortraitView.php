<?php

namespace App\View\Observe;

use App\Factory\EntityManagerFactory;
use App\View\SceneryFigure;
use Doctrine\DBAL\Connection;

/**
 * The portrait of a multi-cell scenery object: its whole figure.
 *
 * A nine-piece library was shown by its top-left ninth, because the entity's
 * `portrait` is one image and a figure of that kind has none.
 *
 * It now shows the same picture the board draws, from the same place, so the
 * card and the map can no longer disagree about what an object looks like.
 * Laying the pieces out here is the fallback, for a figure whose picture is
 * not composed yet.
 */
final class SceneryPortraitView
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * The figure as ONE picture, or null when there is nothing to show — a
     * single cell speaks for itself.
     */
    public function compose(int $entityId): ?string
    {
        $figure = (new \App\Service\Map\SceneryFiguresInSight($this->conn))->forEntity($entityId);

        if ($figure !== null) {
            /* Inline, so it wins over the stylesheet's `height: 100%` — a
             * figure keeps its proportions instead of being stretched. */
            return '<span class="card-portrait card-portrait--figure"'
                . ' style="position:relative;display:block;width:100%;height:auto;'
                . 'aspect-ratio:' . $figure['w'] . ' / ' . $figure['h'] . ';">'
                . '<img src="' . htmlspecialchars('/' . $figure['image'], ENT_QUOTES) . '"'
                . ' style="width:100%;height:100%;" alt="" loading="lazy" />'
                . '</span>';
        }

        return $this->fromPieces($entityId);
    }

    /**
     * The old way, kept for a figure with no composed picture yet: read the
     * pieces off the map and lay them out.
     *
     * It is a fallback and no longer the rule, which matters — it is the last
     * thing here that reads `map_foregrounds`, and it fails QUIETLY when the
     * pieces are gone: fewer than two rows and the card silently falls back
     * to the anchor's single image.
     */
    private function fromPieces(int $entityId): ?string
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
