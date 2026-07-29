<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * The scenery figures a board window has to draw, each as ONE picture.
 *
 * Scenery used to be drawn a piece at a time, from `map_foregrounds`. Each
 * piece was a row with no idea it belonged to anything, which is what made a
 * decor impossible to observe, to shoot at, or to remove as one object.
 *
 * A figure is now an entity holding cells, so it is drawn once, across its
 * whole box. Cells carry absolute x/y, so the box is read off them and no
 * offset has to be re-derived — and a figure whose body reaches into the
 * window is drawn even when its anchor lies outside it.
 *
 * Only figures we can draw FAITHFULLY span: the picture comes from the
 * composed sprite, built from the very pieces the board drew before. Anything
 * else keeps its pieces, so nothing disappears while art is missing.
 */
final class SceneryFiguresInSight
{
    private ?Connection $conn;

    private EntitySpriteService $sprites;

    public function __construct(?Connection $conn = null, ?EntitySpriteService $sprites = null)
    {
        $this->conn = $conn;
        $this->sprites = $sprites ?? new EntitySpriteService();
    }

    private function conn(): Connection
    {
        return $this->conn ??= EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * @param list<int> $coordsIds the window
     * @return array{
     *     figures: list<array{id:int, name:string, family:string, image:string, x:int, y:int, w:int, h:int}>,
     *     covered: array<int, true>
     * } figures to draw, and the cells they take over from the piece rows
     */
    public function forWindow(array $coordsIds): array
    {
        $empty = ['figures' => [], 'covered' => []];

        if ($coordsIds === []) {
            return $empty;
        }

        $ids = $this->sceneryInWindow($coordsIds);

        if ($ids === []) {
            return $empty; /* no decor in view: nothing more to ask */
        }

        return $this->figuresOf($ids);
    }

    /**
     * One figure, for whoever asks about an entity rather than a window — the
     * observation card, which shows the same object in a portrait.
     *
     * @return array{id:int, name:string, family:string, image:string, x:int, y:int, w:int, h:int}|null
     */
    public function forEntity(int $entityId): ?array
    {
        return $this->figuresOf([$entityId])['figures'][0] ?? null;
    }

    /**
     * @param list<int> $coordsIds
     * @return list<int>
     */
    private function sceneryInWindow(array $coordsIds): array
    {
        $in = implode(',', array_map('intval', $coordsIds));

        return array_map('intval', $this->conn()->fetchFirstColumn(
            "SELECT DISTINCT ec.player_id
               FROM entity_cells ec
               JOIN players p ON p.id = ec.player_id AND p.player_type = 'scenery'
              WHERE ec.coords_id IN ({$in})"
        ));
    }

    /**
     * @param list<int> $ids
     * @return array{figures: list<array{id:int, name:string, family:string, image:string, x:int, y:int, w:int, h:int}>, covered: array<int, true>}
     */
    private function figuresOf(array $ids): array
    {
        $in = implode(',', array_map('intval', $ids));

        /* Every cell of those figures, window or not: the box is the whole
         * body, and a figure straddling the edge must not be cut in half. */
        $rows = $this->conn()->fetchAllAssociative(
            "SELECT ec.player_id, ec.coords_id, ec.x, ec.y,
                    p.name, p.race,
                    f.w AS box_w, f.h AS box_h
               FROM entity_cells ec
               JOIN players p ON p.id = ec.player_id
               LEFT JOIN entity_type_footprints f ON f.type_name = p.race
              WHERE ec.player_id IN ({$in})"
        );

        $bodies = [];

        foreach ($rows as $row) {
            $id = (int) $row['player_id'];
            $x = (int) $row['x'];
            $y = (int) $row['y'];

            $body = $bodies[$id] ?? null;

            $bodies[$id] = [
                'name'   => (string) $row['name'],
                'family' => (string) $row['race'],
                'boxW'   => $row['box_w'] === null ? null : (int) $row['box_w'],
                'boxH'   => $row['box_h'] === null ? null : (int) $row['box_h'],
                'minX'   => $body === null ? $x : min($body['minX'], $x),
                'maxX'   => $body === null ? $x : max($body['maxX'], $x),
                'minY'   => $body === null ? $y : min($body['minY'], $y),
                'maxY'   => $body === null ? $y : max($body['maxY'], $y),
                'cells'  => array_merge($body['cells'] ?? [], [(int) $row['coords_id']]),
            ];
        }

        $figures = [];
        $covered = [];

        foreach ($bodies as $id => $body) {
            $width = $body['boxW'] ?? ($body['maxX'] - $body['minX'] + 1);
            $height = $body['boxH'] ?? ($body['maxY'] - $body['minY'] + 1);

            if ($width * $height < 2) {
                continue; /* one cell: today's avatar already draws it right */
            }

            $image = $this->sprites->spanImage('foregrounds', $body['family']);

            if ($image === null) {
                continue; /* no faithful picture: its pieces keep the job */
            }

            $figures[] = [
                'id'     => $id,
                'name'   => $body['name'],
                'family' => $body['family'],
                'image'  => $image,
                'x'      => $body['minX'],
                'y'      => $body['maxY'], /* top-left on screen: y grows upwards on the board */
                'w'      => $width,
                'h'      => $height,
            ];

            foreach ($body['cells'] as $coordsId) {
                $covered[$coordsId] = true;
            }
        }

        return ['figures' => $figures, 'covered' => $covered];
    }
}
