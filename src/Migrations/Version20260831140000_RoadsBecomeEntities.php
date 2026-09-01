<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Roads join the entity model — docs/plan-routes-entities.md.
 *
 * A road was a line in `map_routes`: a coordinate and a name, nothing more.
 * That is enough to grant the running bonus and to paint the map, and too
 * little for everything the team wants next — life, an owner, repair, decay.
 *
 * It becomes a structure whose TYPE blocks nothing, the way a plant is one:
 * walked on rather than stood in. Several kinds are planned, so the types
 * arrive as a family with `route` as its first member rather than as a single
 * hard-coded row.
 *
 * Every existing line becomes an entity on its cell, keeping whoever laid it.
 * The table itself stays for now: the Tiled endpoints still write it, and the
 * step that teaches them entities comes next. Until then nothing reads it —
 * the readers move in this same branch.
 */
final class Version20260831140000_RoadsBecomeEntities extends AbstractMigration
{
    /** The first road type. `pv` is what a road can take before it is gone. */
    private const TYPES = [
        'route' => ['label' => 'Route', 'pv' => 60, 'bgColor' => '#8b4513'],
    ];

    public function getDescription(): string
    {
        return 'les routes deviennent des entités : types de route en races, et chaque ligne de map_routes devient une entité sur sa case';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TYPES as $name => $type) {
            $this->addSql(
                /* `spd` 16 explicitly: the migration that moved every
                   structure type to the players' 18 h turn ran BEFORE this
                   one, so a type created here would have kept the 0 the
                   column defaults to — a 34 h turn, and decay counting at
                   the wrong pace for ever. */
                "INSERT INTO races (code, name, label, description, playable, hidden, kind, type_kind,
                                    structure_nature, bleeds, wound_color, blocks_passage, blocks_projectiles,
                                    pv, spd, bgColor, color, repairable)
                 SELECT ?, ?, ?, ?, 0, 1, 'structure', 'scenery', 'decor', '', '#8b4513', 0, 0, ?, 16, ?, 'black', 1
                  WHERE NOT EXISTS (SELECT 1 FROM races r WHERE CONVERT(r.name USING utf8mb4) = CONVERT(? USING utf8mb4))",
                [
                    strtoupper($name),
                    $name,
                    $type['label'],
                    'Une route : on la marche, elle n\'occupe pas la case.',
                    $type['pv'],
                    $type['bgColor'],
                    $name,
                ]
            );
        }

        /* Each line becomes an entity on its cell, keeping its builder. Ids
           come from the road range; the cells follow in the next statement. */
        $this->addSql(
            "INSERT INTO players (id, player_type, name, race, avatar, portrait, coords_id, slot, registerTime, text, owner_id)
             SELECT @next := @next + 1,
                    'route', r.label, mr.name,
                    CONCAT('img/routes/', mr.name, '.png'),
                    CONCAT('img/routes/', mr.name, '.png'),
                    mr.coords_id, 'installed', UNIX_TIMESTAMP(), '', mr.player_id
               FROM map_routes mr
               JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(mr.name USING utf8mb4)
               CROSS JOIN (
                    /* Start AFTER whatever the road range already holds, not
                       at its floor. Starting at the floor collides on the
                       primary key the moment a single road entity exists —
                       a re-run after a half-applied attempt, or a second
                       environment. */
                    SELECT @next := COALESCE(
                        (SELECT MAX(id) FROM players WHERE id BETWEEN 80000000 AND 89999999),
                        79999999
                    )
               ) init
              WHERE NOT EXISTS (
                    SELECT 1 FROM players p
                     WHERE p.player_type = 'route' AND p.coords_id = mr.coords_id
                )"
        );

        $this->addSql(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             SELECT p.id, c.id, c.plan, c.z, c.x, c.y, 0, 'part'
               FROM players p
               JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type = 'route'
                AND NOT EXISTS (
                    SELECT 1 FROM entity_cells ec WHERE ec.player_id = p.id
                )"
        );
    }

    /**
     * The entities go; `map_routes` was never emptied, so the roads are still
     * there in their old form.
     */
    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM entity_cells WHERE player_id IN (SELECT id FROM players WHERE player_type = 'route')");
        $this->addSql("DELETE FROM players WHERE player_type = 'route'");
        $this->addSql("DELETE FROM races WHERE name IN ('" . implode("','", array_keys(self::TYPES)) . "')");
    }
}
