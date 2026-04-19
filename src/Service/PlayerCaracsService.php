<?php

namespace App\Service;

use Classes\Db;

/**
 * Computes a player's "nude" caracs — base stats built from the race's
 * ability profile plus their bought upgrades.
 *
 * This is the scope the Phase 3 audit (docs/phase-3-schema-audit.md)
 * flagged as the BourrinsView / infos.php blocker: entity-side
 * callers need caracs without going through legacy
 * Classes\Player::get_caracs(), which bundles item bonuses, effects,
 * turn bonuses, and filesystem-cache writes — too much to lift at
 * once.
 *
 * What's in the nude path:
 *   - race stats (from `datas/public/races/<race>.json`)
 *   - upgrades bought by the player (rows in `players_upgrades`)
 *
 * What's NOT in the nude path (stays on legacy
 * `Classes\Player::get_caracs()` until a future mini-phase):
 *   - equipped item bonuses (Item::get_equiped_list loop)
 *   - buff/debuff effects (ELE_BUFFS / ELE_DEBUFFS)
 *   - turn bonuses from `players_bonus`
 *   - JSON cache writes to `datas/private/players/*.caracs.json`
 *
 * Matches the legacy `Player::get_caracs(nude: true)` path, which
 * BourrinsView is the only caller of today. Characterization test
 * covers the equivalence.
 */
class PlayerCaracsService
{
    /**
     * Return a stdClass with every CARACS key populated as
     * race base stat + upgrade count. Matches the shape of
     * `$player->caracs` after `$player->get_caracs(nude: true)`.
     */
    public function computeNudeCaracs(int $playerId, string $race): object
    {
        $raceData = $this->loadRaceData($race);
        $upgradeCounts = $this->loadUpgradeCounts($playerId);

        $caracs = new \stdClass();
        foreach (CARACS as $k => $_) {
            $raceValue = $raceData->$k ?? 0;
            $upgradeValue = $upgradeCounts[$k] ?? 0;
            $caracs->$k = $raceValue + $upgradeValue;
        }

        return $caracs;
    }

    /**
     * Fetch the race JSON from `datas/public/races/<race>.json`. A
     * missing race file returns a zero-filled stdClass — matches the
     * defensive fallback on `Classes\Player::get_caracs` (Player.php
     * around L191).
     */
    private function loadRaceData(string $race): object
    {
        $raceJson = json()->decode('races', $race);

        if (!$raceJson || !is_object($raceJson)) {
            return (object) array_fill_keys(array_keys(CARACS), 0);
        }

        return $raceJson;
    }

    /**
     * Sum the rows in `players_upgrades` per carac name. The table
     * stores one row per upgrade purchase, so duplicates accumulate.
     * Same contract the legacy `Player::get_upgrades()` relied on via
     * the generic god-method `$this->get('upgrades')`.
     *
     * @return array<string, int>
     */
    private function loadUpgradeCounts(int $playerId): array
    {
        $db = new Db();
        $res = $db->get_single_player_id('players_upgrades', $playerId);

        $counts = [];
        while ($row = $res->fetch_object()) {
            $counts[$row->name] = ($counts[$row->name] ?? 0) + 1;
        }

        return $counts;
    }
}
