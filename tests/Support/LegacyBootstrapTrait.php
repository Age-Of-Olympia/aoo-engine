<?php

namespace Tests\Support;

use Doctrine\DBAL\Connection;

/**
 * Boots the legacy stack (bootstrap + functions + constants) for a case that
 * runs against the configured database, and skips cleanly when it cannot.
 *
 * Written once here instead of once per class: the probe differs, the rest
 * never did.
 */
trait LegacyBootstrapTrait
{
    /** The bootstrap connection, null until bootstrapLegacyOrSkip() ran. */
    protected ?Connection $link = null;

    /**
     * @param string|null $probeTable a table the case needs; absent, the
     *                                case skips with the migration hint
     */
    protected function bootstrapLegacyOrSkip(?string $probeTable = null): Connection
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        global $link;
        if (!$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery($probeTable === null ? 'SELECT 1' : "SELECT 1 FROM {$probeTable} LIMIT 1");
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                ($probeTable === null ? 'Legacy DB' : "{$probeTable} table") . ' unreachable (run migrations?): ' . $e->getMessage()
            );
        }

        $this->link = $link;

        return $link;
    }

    /** The lowest real character id the world holds, or skip. */
    protected function firstRealPlayerIdOrSkip(): int
    {
        try {
            $id = $this->link->fetchOne(
                "SELECT id FROM players WHERE id > 0 AND (player_type IS NULL OR player_type = 'real') ORDER BY id ASC LIMIT 1"
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('players table unreadable: ' . $e->getMessage());
        }

        if (empty($id)) {
            $this->markTestSkipped('No real player row available — run scripts/testing/reset_test_database.sh.');
        }

        return (int) $id;
    }
}
