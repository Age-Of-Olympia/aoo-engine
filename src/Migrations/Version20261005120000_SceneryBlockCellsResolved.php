<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Service\Map\SceneryFootprintDeriver;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Scenery cells still holding the `block` role, left by the conversion of
 * the old map fences, take an explicit role.
 *
 * `block` was laid per copy, while the Formes editor declares roles per type,
 * and any re-lay of the cells (saving the type in Formes, moving a copy)
 * turned them back to `cover`. Two cases:
 *
 *  - every copy of the type blocks on the same pieces: the type declares
 *    them, `wall` (or `fence` when the type lets shots through, which is
 *    what `block` meant), so they survive a re-lay and show in Formes;
 *  - copies disagree (4 of 49 barrels): the type cannot say it, the cells
 *    become `cover`.
 *
 * Shapes come from the map (`map_foregrounds`) or are single cells: reads
 * only the database. Re-running finds no `block` cell left.
 */
final class Version20261005120000_SceneryBlockCellsResolved extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cases block des décors : rôle déclaré sur le type quand toutes les copies concordent, cover sinon';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT p.race, ec.piece, SUM(ec.role = 'block') AS blocking, COUNT(*) AS total
               FROM entity_cells ec
               JOIN players p ON p.id = ec.player_id
              WHERE p.player_type = 'scenery'
              GROUP BY p.race, ec.piece"
        );

        /** @var array<string, array<int, bool>> $pieces race => piece => blocks on every copy */
        $pieces = [];
        $mixed = [];

        foreach ($rows as $row) {
            $blocking = (int) $row['blocking'];

            if ($blocking > 0 && $blocking < (int) $row['total']) {
                $mixed[(string) $row['race']] = true;
            }

            $pieces[(string) $row['race']][(int) $row['piece']] = $blocking > 0;
        }

        $derived = null;

        foreach ($pieces as $race => $blocks) {
            if (!in_array(true, $blocks, true)) {
                continue;
            }

            if (isset($mixed[$race])) {
                $this->setRole($race, 'cover');
                continue;
            }

            $role = (int) $this->connection->fetchOne(
                'SELECT blocks_projectiles FROM races WHERE name = ?',
                [$race]
            ) === 0 ? 'fence' : 'wall';

            $derived ??= (new SceneryFootprintDeriver($this->connection))->derive();

            if (!$this->declareRoles($race, array_keys(array_filter($blocks)), $role, $derived[$race]['footprint'] ?? null)) {
                $this->setRole($race, 'cover');
                continue;
            }

            $this->setRole($race, $role);
        }

        $this->connection->executeStatement('UPDATE board_views SET stale = 1');
    }

    /**
     * Add the blocking pieces to the type's declared roles, declaring its
     * shape first when it only came from the map, or is a single cell.
     *
     * @param list<int> $blockingPieces
     * @return bool false when the shape is unknown and nothing was declared
     */
    private function declareRoles(string $race, array $blockingPieces, string $role, ?\App\Service\Map\Footprint $mapShape): bool
    {
        $declared = $this->connection->fetchAssociative(
            'SELECT roles FROM entity_type_footprints WHERE type_name = ?',
            [$race]
        );

        if ($declared !== false) {
            $roles = json_decode((string) ($declared['roles'] ?? ''), true);
            $roles = is_array($roles) ? $roles : [];

            foreach ($blockingPieces as $piece) {
                $roles[$piece] ??= $role;
            }

            ksort($roles);
            $this->connection->executeStatement(
                'UPDATE entity_type_footprints SET roles = ? WHERE type_name = ?',
                [json_encode($roles), $race]
            );

            return true;
        }

        if ($mapShape !== null) {
            [$w, $h, $offsets] = [$mapShape->width(), $mapShape->height(), $mapShape->offsets()];
        } elseif ($blockingPieces === [0]) {
            [$w, $h, $offsets] = [1, 1, [0 => [0, 0]]];
        } else {
            return false;
        }

        $this->connection->executeStatement(
            'INSERT INTO entity_type_footprints (type_name, w, h, offsets, roles) VALUES (?, ?, ?, ?, ?)',
            [$race, $w, $h, json_encode($offsets), json_encode(array_fill_keys($blockingPieces, $role))]
        );

        return true;
    }

    private function setRole(string $race, string $role): void
    {
        $this->connection->executeStatement(
            "UPDATE entity_cells ec
               JOIN players p ON p.id = ec.player_id
                SET ec.role = ?
              WHERE ec.role = 'block' AND p.player_type = 'scenery' AND p.race = ?",
            [$role, $race]
        );
    }

    public function down(Schema $schema): void
    {
        /* Data only: which cells were `block` is not kept. */
    }
}
