<?php

namespace App\Service;

use Classes\Db;

/**
 * SQL access for the `players_actions` table.
 *
 * Extracted from the generic Classes\Player::have/add/end/get god-method
 * during the Classes\Player dismantling. Sibling to
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
 * `actions` table with a learned-skill ormType (spell / technique /
 * buff / heal) persist with `type='sort'` instead of the default empty
 * string — defensive spells (buff/heal) included, else they vanish from
 * the owned-spells page and dodge the cap (#264). 'attaquer' is a
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
     * Looks up the action's ormType via ActionService; learned combat
     * skills (spell/technique/buff/heal) persist with `type='sort'` to
     * satisfy the caster UI branches downstream (owned-spells list +
     * NUMBER_MAX_COMP cap). Le court-circuit qui sautait cette lecture
     * pour « attaquer » a disparu avec le nom fantôme lui-même : melee
     * et distance sont au catalogue, et leur ormType (melee/distance)
     * n'est pas une compétence apprise — elles ne comptent donc pas
     * dans le plafond, exactement comme avant.
     */
    public function addAction(int $playerId, string $name): void
    {
        $values = [
            'player_id' => $playerId,
            'name'      => $name,
        ];

        $action = (new ActionService())->getActionByName($name);
        // 'sort' marks a learned combat skill (shows on the owned-spells page
        // and counts toward NUMBER_MAX_COMP). Defensive spells are buff/heal
        // classes, not just spell/technique — without them the owned heals were
        // invisible and uncapped (#264).
        if ($action !== null && in_array($action->getOrmType(), ['spell', 'technique', 'buff', 'heal'], true)) {
            $values['type'] = 'sort';
        }

        (new Db())->insert('players_actions', $values);
    }

    /**
     * Grant a race's starter actions (race_starter_actions list),
     * idempotently. Used at player creation and when a tutorial
     * finishes/aborts. A failure on one action is logged and the rest
     * still process — granting must never block creation or tutorial
     * completion.
     */
    public function grantRaceStarterPack(int $playerId, string $race): void
    {
        $raceEntity = (new RaceService())->getRaceByName($race);
        if ($raceEntity === null) {
            return;
        }

        foreach ($raceEntity->getStarterActionNames() as $name) {
            try {
                if (!$this->hasAction($playerId, $name)) {
                    $this->addAction($playerId, $name);
                }
            } catch (\Throwable $e) {
                error_log("[grantRaceStarterPack] could not add action '{$name}' to player {$playerId}: " . $e->getMessage());
            }
        }
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

    /**
     * Owned action names with their players_actions.type, in one query —
     * 'sort' marks a war-school purchase, '' a granted starter action.
     * @return array<string, string>
     */
    public function getActionsWithType(int $playerId): array
    {
        $return = [];

        $res = (new Db())->get_single_player_id('players_actions', $playerId);

        while ($row = $res->fetch_object()) {
            $return[$row->name] = (string) ($row->type ?? '');
        }

        return $return;
    }
}
