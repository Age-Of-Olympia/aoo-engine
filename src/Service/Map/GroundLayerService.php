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

    /**
     * Layer name => the entity family that now holds it.
     *
     * A road used to be a line in `map_routes`; it is an entity since
     * Version20260831140000. Everything still ASKS for the layer by name —
     * action config says `{"type": "routes"}` — so the translation lives
     * here, and the day another layer becomes a family it joins this list
     * rather than a second lookup somewhere else.
     */
    private const ENTITY_FAMILIES = ['routes' => 'route'];

    /** Does this catalogue subtype lay a layer instead of installing a thing? */
    public static function isLayer(string $subtype): bool
    {
        return in_array($subtype, self::LAYERS, true);
    }

    /**
     * Is this layer on that cell?
     *
     * Asked through `entity_cells` for a family that has become entities, so
     * a road covering several cells answers on every one of them.
     */
    public function hasAt(string $layer, int $coordsId): bool
    {
        $conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
        $family = self::ENTITY_FAMILIES[$layer] ?? null;

        if ($family !== null) {
            return (bool) $conn->fetchOne(
                'SELECT 1
                   FROM players p
                   JOIN entity_cells ec ON ec.player_id = p.id
                  WHERE p.player_type = ? AND ec.coords_id = ?
                  LIMIT 1',
                [$family, $coordsId]
            );
        }

        /* Still a plain layer: the name reaches here from action config and
           names a table, so it stays guarded to a bare identifier. */
        if (preg_match('/^[a-z][a-z0-9_]*$/', $layer) !== 1) {
            throw new \InvalidArgumentException("Type de tuile invalide : {$layer}.");
        }

        return (bool) $conn->fetchOne('SELECT 1 FROM map_' . $layer . ' WHERE coords_id = ? LIMIT 1', [$coordsId]);
    }

    /**
     * Lay the layer on the cell.
     *
     * A family that has become entities gets an entity — with its life, its
     * owner and its cells — rather than a table row. The rest still write
     * `map_<layer>`.
     *
     * @return array{ok: bool, message: string}
     */
    public function lay(string $layer, string $name, object $goCoords, int $actorId, bool $byPlayer = false): array
    {
        if ($layer === '' || $name === '') {
            return ['ok' => false, 'message' => 'Couche ou nom manquant.'];
        }

        $coordsId = (int) View::get_coords_id($goCoords);

        if ($this->hasAt($layer, $coordsId)) {
            return ['ok' => false, 'message' => 'Il y a déjà cela ici.'];
        }

        /* An element forbids the work UNLESS its effect is marked buildable
           over (blood, mud, tracks) — the same rule BuildingService::place
           applies to structures. */
        $db = new Db();
        $effectService = new \App\Service\EffectService();
        $element = $db->exe('SELECT name FROM map_elements WHERE coords_id = ?', $coordsId);
        while ($element && ($row = $element->fetch_object())) {
            if (!$effectService->isBuildableOver((string) $row->name)) {
                return ['ok' => false, 'message' => 'Un élément occupe cette case.'];
            }
        }

        $family = self::ENTITY_FAMILIES[$layer] ?? null;

        if ($family !== null) {
            $this->mintEntity($family, $name, $coordsId, $actorId, $byPlayer);
        } else {
            $layer = preg_replace('/[^a-z_]/', '', $layer);
            $db->insert('map_' . $layer, [
                'name' => $name,
                'coords_id' => $coordsId,
                'player_id' => $actorId,
            ]);
        }

        View::refresh_players_svg($goCoords);

        return [
            'ok' => true,
            'message' => 'Vous aménagez ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . ' <span class="ra ra-implosion"></span> en (' . $goCoords->x . ', ' . $goCoords->y . ').',
        ];
    }

    /**
     * The road entities standing on a cell.
     *
     * @return list<int>
     */
    public function roadsOn(int $coordsId): array
    {
        return array_map('intval', \App\Factory\EntityManagerFactory::getEntityManager()
            ->getConnection()
            ->fetchFirstColumn(
                "SELECT p.id
                   FROM players p
                   JOIN entity_cells ec ON ec.player_id = p.id
                  WHERE p.player_type = ? AND ec.coords_id = ?",
                [self::ENTITY_FAMILIES['routes'], $coordsId]
            ));
    }

    /** The entity a laid road becomes: its type's label, its own sprite. */
    private function mintEntity(string $family, string $type, int $coordsId, int $actorId, bool $byPlayer): void
    {
        $conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();

        $label = (string) ($conn->fetchOne(
            'SELECT label FROM races WHERE CONVERT(name USING utf8mb4) = CONVERT(? USING utf8mb4)',
            [$type]
        ) ?: $type);

        $id = (new EntityPlacementService($conn))->create(
            $family,
            $type,
            $coordsId,
            $label,
            'img/' . $family . 's/' . $type . '.png'
        );

        /* A road stays its builder's: that is what lets it decay when nobody
           walks it, and what the faction page reads. */
        $conn->executeStatement('UPDATE players SET owner_id = ? WHERE id = ?', [$actorId, $id]);

        /* Only what a PLAYER laid decays. The map editor uses this same
           gesture — its palette is unchanged — so the caller says which it
           is: an animator laying a road must not make the world perishable. */
        if ($byPlayer) {
            (new \App\Service\Decay\StructureDecayService())->enrol($id);
        }
    }
}
