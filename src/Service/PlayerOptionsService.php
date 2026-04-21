<?php

namespace App\Service;

use Classes\Db;

/**
 * SQL access for the `players_options` table.
 *
 * Extracted from the generic Classes\Player::have/add/end/get god-method
 * (Classes/Player.php:467-568) in Phase 2 of the Classes\Player
 * dismantling.
 *
 * The legacy Classes\Player shims (have_option / add_option / end_option
 * / get_options) remain as thin delegations so the ~71 existing call
 * sites keep working unchanged. Modern code can hit this service
 * directly.
 *
 * Characterization tests:
 *   tests/Various/PlayerOptionsCharacterizationTest.php
 *
 * Side-effect note: the `isMerchant` option carries a `marchand`
 * follower hook (add on set, delete on unset). That hook stays in
 * Classes\Player where the follower methods live — this service owns
 * ONLY the table access, keeping the boundary clean for future
 * extractions.
 */
class PlayerOptionsService
{
    /**
     * Count rows matching (player_id, name) in players_options.
     *
     * Returns an int because callers historically treat the result as
     * both a truthiness check AND a count (`have_option()` returns 2
     * when the option was added twice). See the characterization test
     * `testDuplicateAddYieldsCountOfTwo` for the contract.
     */
    public function hasOption(int $playerId, string $name): int
    {
        $db = new Db();

        $sql = '
        SELECT COUNT(*) AS n
        FROM players_options
        WHERE player_id = ? AND name = ?';

        $res = $db->exe($sql, [$playerId, $name]);
        $row = $res->fetch_assoc();

        return (int) $row['n'];
    }

    /**
     * Insert a row into players_options. No UNIQUE constraint on
     * (player_id, name), so repeated calls produce duplicate rows —
     * preserved from the legacy behaviour.
     */
    public function addOption(int $playerId, string $name): void
    {
        $db = new Db();

        $db->insert('players_options', [
            'player_id' => $playerId,
            'name'      => $name,
        ]);
    }

    /**
     * Delete every row matching (player_id, name) from players_options.
     * No-op when no row matches — preserved from the legacy behaviour.
     */
    public function endOption(int $playerId, string $name): void
    {
        $db = new Db();

        $db->delete('players_options', [
            'player_id' => $playerId,
            'name'      => $name,
        ]);
    }

    /**
     * Return an ascending-sorted list of option names for a player.
     *
     * @return array<int, string>
     */
    public function getOptions(int $playerId): array
    {
        $return = [];

        $db = new Db();

        $res = $db->get_single_player_id('players_options', $playerId);

        while ($row = $res->fetch_object()) {
            $return[] = $row->name;
        }

        sort($return);

        return $return;
    }
}
