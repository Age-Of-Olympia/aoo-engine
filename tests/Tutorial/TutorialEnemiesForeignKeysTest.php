<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: tutorial_enemies must carry FKs to players + coords.
 *
 * The 20251127 monolith created the table without foreign keys, so
 * deleting the enemy NPC's `players` row leaves an orphan
 * `tutorial_enemies.enemy_player_id`, and deleting the enemy's coord
 * row breaks observation queries that JOIN through `coords`.
 */
class TutorialEnemiesForeignKeysTest extends TestCase
{
    private const MIGRATION_GLOB =
        __DIR__ . '/../../src/Migrations/Version*AddTutorialEnemies*ForeignKeys*.php';

    private function loadMigrationSource(): string
    {
        $matches = glob(self::MIGRATION_GLOB) ?: [];
        $this->assertNotEmpty(
            $matches,
            'A migration matching ' . basename(self::MIGRATION_GLOB)
            . ' must exist to add the tutorial_enemies FKs.'
        );

        return (string) file_get_contents($matches[0]);
    }

    #[Group('tutorial-enemies-fks')]
    public function testMigrationAddsEnemyPlayerIdCascadeFk(): void
    {
        $source = $this->loadMigrationSource();

        $this->assertMatchesRegularExpression(
            '/FOREIGN\s+KEY\s*\(\s*[`"]?enemy_player_id[`"]?\s*\)\s*REFERENCES\s*[`"]?players[`"]?\s*\(\s*[`"]?id[`"]?\s*\)\s*ON\s+DELETE\s+CASCADE/i',
            $source,
            'tutorial_enemies.enemy_player_id must FK players(id) with '
            . 'ON DELETE CASCADE — when the enemy NPC players row is '
            . 'cleaned up, the tracking row goes with it.'
        );
    }

    #[Group('tutorial-enemies-fks')]
    public function testMigrationAddsEnemyCoordsIdRestrictFk(): void
    {
        $source = $this->loadMigrationSource();

        $this->assertMatchesRegularExpression(
            '/FOREIGN\s+KEY\s*\(\s*[`"]?enemy_coords_id[`"]?\s*\)\s*REFERENCES\s*[`"]?coords[`"]?\s*\(\s*[`"]?id[`"]?\s*\)\s*ON\s+DELETE\s+RESTRICT/i',
            $source,
            'tutorial_enemies.enemy_coords_id must FK coords(id) with '
            . 'ON DELETE RESTRICT — refuse to delete a coord that still '
            . 'has a live enemy referencing it (catches cleanup-order bugs).'
        );
    }

    #[Group('tutorial-enemies-fks')]
    public function testMigrationScrubsOrphansBeforeAddingFks(): void
    {
        $source = $this->loadMigrationSource();

        // The FK validation step refuses to apply when existing rows
        // already violate the constraint. Pre-scrub via DELETE on
        // orphan rows so the FK addition succeeds on dirty databases.
        $this->assertMatchesRegularExpression(
            '/DELETE\s+(FROM\s+)?[`"]?(tutorial_enemies\s+)?te[`"]?\s+FROM\s+[`"]?tutorial_enemies[`"]?\s+te\s+LEFT\s+JOIN\s+[`"]?players[`"]?/i',
            $source,
            'Migration must DELETE orphan tutorial_enemies (rows whose '
            . 'enemy_player_id no longer references a players row) before '
            . 'adding the FK, otherwise the ALTER TABLE fails on dirty data.'
        );
    }
}
