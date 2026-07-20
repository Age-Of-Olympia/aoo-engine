<?php

namespace App\Service;

use Classes\Db;

/**
 * Computes a player's "nude" caracs — base stats built from the race's
 * ability profile plus their bought upgrades.
 *
 * This is the scope the read-path audit flagged as the BourrinsView /
 * infos.php blocker: entity-side callers need caracs without going
 * through legacy
 * Classes\Player::get_caracs(), which bundles item bonuses, effects,
 * turn bonuses, and filesystem-cache writes — too much to lift at
 * once.
 *
 * What's in the nude path:
 *   - race stats (from the `races` table via RaceService)
 *   - upgrades bought by the player (rows in `players_upgrades`)
 *
 * What's NOT in the nude path (stays on legacy
 * `Classes\Player::get_caracs()` until a future mini-phase):
 *   - equipped item bonuses (Item::get_equiped_list loop)
 *   - buff/debuff effects (EffectService::getBuffCaracs/getDebuffCaracs)
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
     * Cost grid per carac: [first rank, ranks 2-3, ranks 4 and up].
     * It lives with the upgrades it prices, so that the table showing
     * them and the endpoints charging them read the same figures.
     *
     * @var array<string, array{0: int, 1: int, 2: int}>
     */
    private const UPGRADE_COSTS = [
        'pv' => [4, 2, 1],
        'ct' => [110, 50, 30],
        'f' => [120, 55, 30],
        'agi' => [95, 45, 25],
        'e' => [120, 55, 30],
        'pm' => [5, 3, 1],
        'fm' => [100, 50, 30],
        'pui' => [120, 55, 30],
        'res' => [120, 55, 30],
        'a' => [800, 200, 100],
        'mvt' => [100, 50, 30],
        'r' => [40, 30, 15],
        'rm' => [50, 40, 20],
        'cc' => [100, 50, 30],
        'p' => [110, 85, 78],
        'spd' => [400, 100, 50],
    ];

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
     * Fetch the race definition from the DB (races table). An unknown
     * race returns a zero-filled stdClass — matches the defensive
     * fallback on `Classes\Player::get_caracs` (Player.php around L191).
     */
    private function loadRaceData(string $race): object
    {
        $raceData = (new RaceService())->getRaceData($race);

        if ($raceData === null) {
            return (object) array_fill_keys(array_keys(CARACS), 0);
        }

        return $raceData;
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

    /**
     * The cost grid of a carac, or null when it has no rank to buy —
     * `ae` is read from the equipment, never bought.
     *
     * @return array{0: int, 1: int, 2: int}|null
     */
    public function getUpgradeProgress(string $carac): ?array
    {
        return self::UPGRADE_COSTS[$carac] ?? null;
    }

    /**
     * Price of the next rank: full fare for the first, then the
     * degressive steps of the grid. What is not sold costs nothing.
     */
    public function returnCost(string $carac, int $upgraded): int
    {
        $progress = $this->getUpgradeProgress($carac);

        if ($progress === null) {
            return 0;
        }

        $total = $progress[0];

        for ($i = 1; $i <= $upgraded; $i++) {
            $total += $i < 3 ? $progress[1] : $progress[2];
        }

        return $total;
    }
}
