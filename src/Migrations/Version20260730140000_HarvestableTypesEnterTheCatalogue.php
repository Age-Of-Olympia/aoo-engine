<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Interface\HarvestableInterface;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * HarvestableInterface resources become structure types, so they can become entities.
 *
 * Derived from `resource_types` where pv = -1 — the flag that already means
 * "harvestable, indestructible" — rather than from a list frozen here: each
 * environment then gets rows for the resources it actually holds. 39 of them
 * on the current schema, none in `races` yet.
 *
 * The values are not chosen, they are the ones this chantier already set:
 * `arbre7` and `glaise3` were seeded as structure / obstacle / pv 10, blocking
 * step and shot, by an earlier migration. The rest join them.
 *
 * pv 10 does NOT make a resource fellable. What refuses destruction today is
 * `destroy.php`, on pv < 0 in `resource_types`; when these become entities the
 * same refusal is carried by a targeting gate, in the conversion's own change.
 * Until then these rows are inert: nothing reads `races` for a `map_resources`
 * row.
 *
 * Names are compared with an explicit collation: `races.name`,
 * `resource_types.name` and `map_resources.name` disagree on three databases,
 * and a bare join on them errors 1267.
 */
final class Version20260730140000_HarvestableTypesEnterTheCatalogue extends AbstractMigration
{
    private const PV = 10;
    private const BG_COLOR = '#8a8a8a';

    public function getDescription(): string
    {
        return 'HarvestableInterface resource types enter the races catalogue as structures';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT IGNORE INTO races
                (code, name, label, description, playable, hidden, kind, structure_nature,
                 bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color,
                 faction, plan, pv)
             SELECT UPPER(t.name),
                    t.name,
                    CONCAT(UPPER(LEFT(t.name, 1)), SUBSTRING(t.name, 2)),
                    '',
                    0, 1, 'structure', 'obstacle',
                    '', '#cd7f32', 1, 1, ?, 'black',
                    '', '', ?
               FROM resource_types t
              WHERE t.pv = -1
                AND NOT EXISTS (
                    SELECT 1 FROM races r
                     WHERE CONVERT(r.name USING utf8mb4) = CONVERT(t.name USING utf8mb4)
                )",
            [self::BG_COLOR, self::PV]
        );
    }

    public function down(Schema $schema): void
    {
        /* Only the untouched ones go: a type worn by an entity, or edited by
         * hand since, is left alone. */
        $this->addSql(
            "DELETE r FROM races r
               JOIN resource_types t
                 ON CONVERT(t.name USING utf8mb4) = CONVERT(r.name USING utf8mb4)
              WHERE t.pv = -1
                AND r.kind = 'structure'
                AND r.structure_nature = 'obstacle'
                AND r.pv = ?
                AND r.description = ''
                AND NOT EXISTS (
                    SELECT 1 FROM players p
                     WHERE CONVERT(p.race USING utf8mb4) = CONVERT(r.name USING utf8mb4)
                )",
            [self::PV]
        );
    }
}
