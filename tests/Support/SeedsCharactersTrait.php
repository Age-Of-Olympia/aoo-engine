<?php

namespace Tests\Support;

use Doctrine\DBAL\Connection;

/**
 * Puts a throwaway character in the world, for the cases that need one to exist.
 *
 * Several tests were written against the seeded development world — "Cradek is a
 * nain", "Cradek & co have a portrait on file" — and asserted on whatever
 * happened to be there. That reads well and holds only as long as nobody edits
 * the world: the day the suite moved to a database of its own, with catalogues
 * and no characters, every one of them went red without a single line of
 * production code having changed.
 *
 * A case that needs a character now says so and makes one. What it asserts then
 * depends on what it arranged, not on who happens to be playing.
 *
 * The rows are minimal on purpose — a name, a race, a cell. Anything else a
 * case needs, it passes in.
 */
trait SeedsCharactersTrait
{
    /** @var list<int> ids handed out here, removed by removeSeededCharacters() */
    private array $seededCharacterIds = [];

    /**
     * @param array<string, string|int> $columns extra `players` columns to set
     *
     * @return int the new character's id
     */
    private function seedCharacter(Connection $conn, string $race = 'nain', array $columns = []): int
    {
        $coordsId = (int) $conn->fetchOne(
            "SELECT id FROM coords WHERE x = 0 AND y = 0 AND z = 0 AND plan = 'gaia'"
        );
        if ($coordsId === 0) {
            $conn->insert('coords', ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => 'gaia']);
            $coordsId = (int) $conn->lastInsertId();
        }

        $conn->insert('players', array_merge([
            'name'        => 'Semé_' . bin2hex(random_bytes(4)),
            'race'        => $race,
            'player_type' => 'real',
            'coords_id'   => $coordsId,
            'slot'        => 'installed',
        ], $columns));

        $id = (int) $conn->lastInsertId();
        $this->seededCharacterIds[] = $id;

        return $id;
    }

    /**
     * Take them back out, satellites first.
     *
     * Foreign keys are RESTRICT here and there, and a teardown that stops at the
     * first refusal leaves rows standing — which is how a suite starts poisoning
     * its own later runs.
     */
    private function removeSeededCharacters(Connection $conn): void
    {
        foreach ($this->seededCharacterIds as $id) {
            foreach ($conn->fetchAllAssociative(
                "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'players'"
            ) as $ref) {
                if ($ref['TABLE_NAME'] === 'players' && $ref['COLUMN_NAME'] === 'id') {
                    continue;
                }
                $conn->executeStatement(
                    "DELETE FROM `{$ref['TABLE_NAME']}` WHERE `{$ref['COLUMN_NAME']}` = ?",
                    [$id]
                );
            }
            $conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [$id]);
            $conn->executeStatement('DELETE FROM players WHERE id = ?', [$id]);
        }

        $this->seededCharacterIds = [];
    }
}
