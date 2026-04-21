<?php

namespace App\Service;

use Classes\Db;

/**
 * SQL access for the `players_actions` table.
 *
 * Extracted from the generic Classes\Player::have/add/end/get god-method
 * in Phase 2b of the Classes\Player dismantling. Sibling to
 * PlayerOptionsService.
 *
 * The legacy Classes\Player shims (have_action / add_action /
 * end_action / get_actions) remain as thin delegations so the existing
 * call sites keep working unchanged.
 *
 * Characterization tests:
 *   tests/Various/PlayerActionsCharacterizationTest.php
 *
 * Unlike the options table, players_actions has PRIMARY KEY
 * (player_id, name), so a duplicate addAction throws
 * mysqli_sql_exception under the strict-mode reporting Classes\Db
 * enables globally. The characterization test pins this.
 *
 * addAction also carries the ortType branch: names registered in the
 * `actions` table with ormType 'spell' or 'technique' persist with
 * `type='sort'` instead of the default empty string. 'attaquer' is a
 * hot-path short-circuit that skips the ActionService lookup — the
 * black-box outcome is identical (attaquer is ormType='melee', so
 * neither branch sets 'sort'), but the optimization avoids one DB
 * round-trip per attack registration.
 */
class PlayerActionsService
{
    /**
     * Count rows matching (player_id, name) in players_actions.
     *
     * With PRIMARY KEY (player_id, name) this only ever returns 0 or 1,
     * but the `int` return preserves the legacy have()/have_action
     * signature that treated the count as truth-value-or-count.
     */
    public function hasAction(int $playerId, string $name): int
    {
        $db = new Db();

        $sql = '
        SELECT COUNT(*) AS n
        FROM players_actions
        WHERE player_id = ? AND name = ?';

        $res = $db->exe($sql, [$playerId, $name]);
        $row = $res->fetch_assoc();

        return (int) $row['n'];
    }

    /**
     * Insert a row into players_actions. Throws on PK conflict
     * (mysqli_sql_exception under strict mode).
     *
     * For non-'attaquer' names, looks up the action's ormType via
     * ActionService; spell/technique entries persist with
     * `type='sort'` to satisfy the caster UI branches downstream.
     */
    public function addAction(int $playerId, string $name): void
    {
        $values = [
            'player_id' => $playerId,
            'name'      => $name,
        ];

        if ($name !== 'attaquer') {
            $actionService = new ActionService();
            $action = $actionService->getActionByName($name);
            if ($action !== null && in_array($action->getOrmType(), ['spell', 'technique'], true)) {
                $values['type'] = 'sort';
            }
        }

        (new Db())->insert('players_actions', $values);
    }

    /**
     * Delete the row matching (player_id, name). No-op when absent.
     */
    public function endAction(int $playerId, string $name): void
    {
        (new Db())->delete('players_actions', [
            'player_id' => $playerId,
            'name'      => $name,
        ]);
    }

    /**
     * Return an ascending-sorted list of action names for a player.
     *
     * @return array<int, string>
     */
    public function getActions(int $playerId): array
    {
        $return = [];

        $db = new Db();

        $res = $db->get_single_player_id('players_actions', $playerId);

        while ($row = $res->fetch_object()) {
            $return[] = $row->name;
        }

        sort($return);

        return $return;
    }
}
