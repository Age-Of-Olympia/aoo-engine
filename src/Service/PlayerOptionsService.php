<?php

namespace App\Service;

use Classes\Db;

/**
 * SQL access for the `players_options` table.
 *
 * Extracted from the generic Classes\Player::have/add/end/get god-method
 * (Classes/Player.php) during the Classes\Player
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
     * Cache par requête HTTP des compteurs d'options par joueur : la
     * page de jeu appelait have_option() 5+ fois (5+ COUNT). Un seul
     * SELECT GROUP BY par joueur ; invalidé par addOption/endOption et
     * resetCache() (pour les écritures directes hors service).
     *
     * @var array<int, array<string, int>>
     */
    private static array $optionCountsByPlayer = [];

    /** Invalide le cache (un joueur, ou tout si null). */
    public static function resetCache(?int $playerId = null): void
    {
        if ($playerId === null) {
            self::$optionCountsByPlayer = [];
        } else {
            unset(self::$optionCountsByPlayer[$playerId]);
        }
    }

    /**
     * The canonical set of admin-toggleable option names. Single source of truth
     * shared by the admin options manager (admin/admin-access*.php) and the
     * `option` console command (Classes/console-commands/optioncmd.php), so the
     * two surfaces can never disagree on what is a valid option.
     */
    public const MANAGEABLE_OPTIONS = [
        'isSuperAdmin', 'isAdmin', 'isMerchant', 'isTrainer', 'showActionDetails',
        'alreadyFished', 'incognitoMode', 'invisibleMode', 'showBlockedTiles',
        'doubleUpload', 'alreadyChanged',
    ];

    /** Options that carry admin authority — highlighted / guarded in the UI. */
    public const PRIVILEGED_OPTIONS = ['isAdmin', 'isSuperAdmin'];

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
        if (!isset(self::$optionCountsByPlayer[$playerId])) {
            $db = new Db();

            $sql = '
            SELECT name, COUNT(*) AS n
            FROM players_options
            WHERE player_id = ?
            GROUP BY name';

            $res = $db->exe($sql, [$playerId]);

            $counts = [];
            while ($row = $res->fetch_assoc()) {
                $counts[$row['name']] = (int) $row['n'];
            }
            self::$optionCountsByPlayer[$playerId] = $counts;
        }

        return self::$optionCountsByPlayer[$playerId][$name] ?? 0;
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

        self::resetCache($playerId);
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

        self::resetCache($playerId);
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
