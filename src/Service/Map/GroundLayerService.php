<?php

namespace App\Service\Map;

use Classes\Db;
use Classes\View;

/**
 * Laying a ground layer on a cell — a road, and whatever joins it.
 *
 * A road is walked ON, not stood in: it belongs to `map_routes`, the layer
 * the map editor writes and that everything already reads — the running
 * bonus through `MapService::getTileTypeAtCoord`, the drawn map, `observe`,
 * and the rule keeping plants off roads.
 *
 * Placing one as an installed object instead puts a THING on the cell: the
 * board draws an object rather than a road, and every one of those readers
 * misses it. That is what happened when the placement action moved to
 * `placestructure` and road items followed the object path with it.
 *
 * Shared by the two instructions that can lay one, so the rules — one layer
 * per cell, elements that forbid it — are written once.
 */
final class GroundLayerService
{
    /** Item subtypes that name a ground layer rather than an object. */
    public const LAYERS = ['routes'];

    /** Does this catalogue subtype lay a layer instead of installing a thing? */
    public static function isLayer(string $subtype): bool
    {
        return in_array($subtype, self::LAYERS, true);
    }

    /**
     * Write the layer on the cell.
     *
     * @return array{ok: bool, message: string}
     */
    public function lay(string $layer, string $name, object $goCoords, int $actorId): array
    {
        /* The layer names a table and reaches here from action config, never
           from player input — a strict identifier still costs one line. */
        $layer = preg_replace('/[^a-z_]/', '', $layer);

        if ($layer === '' || $name === '') {
            return ['ok' => false, 'message' => 'Couche ou nom manquant.'];
        }

        $coordsId = View::get_coords_id($goCoords);
        $db = new Db();

        $already = $db->exe('SELECT id FROM map_' . $layer . ' WHERE coords_id = ?', $coordsId);
        if ($already && $already->num_rows) {
            return ['ok' => false, 'message' => 'Il y a déjà cela ici.'];
        }

        /* An element forbids the work UNLESS its effect is marked buildable
           over (blood, mud, tracks) — the same rule BuildingService::place
           applies to structures. */
        $effectService = new \App\Service\EffectService();
        $element = $db->exe('SELECT name FROM map_elements WHERE coords_id = ?', $coordsId);
        while ($element && ($row = $element->fetch_object())) {
            if (!$effectService->isBuildableOver((string) $row->name)) {
                return ['ok' => false, 'message' => 'Un élément occupe cette case.'];
            }
        }

        $db->insert('map_' . $layer, [
            'name' => $name,
            'coords_id' => $coordsId,
            'player_id' => $actorId,
        ]);

        View::refresh_players_svg($goCoords);

        return [
            'ok' => true,
            'message' => 'Vous aménagez ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . ' <span class="ra ra-implosion"></span> en (' . $goCoords->x . ', ' . $goCoords->y . ').',
        ];
    }
}
