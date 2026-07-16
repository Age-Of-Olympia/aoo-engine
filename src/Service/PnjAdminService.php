<?php

namespace App\Service;

use Classes\Db;
use Classes\Player;
use Classes\View;

/**
 * Read + light-write access for the admin PNJ management page (admin/pnjs.php).
 *
 * A "PNJ" is a character row with player_type = 'npc' (negative id). This
 * service exposes the roster (with the players that control each PNJ, its
 * activity and XP), the per-PNJ owner list, creation, identity edit and the
 * soft-retire operation.
 *
 * Assignment (linking a PNJ to a controlling player) stays in PlayerPnjService,
 * which owns the players_pnjs table; this service only reads that table for the
 * roster and clears it on retire. Character creation delegates to
 * Player::put_player, the canonical creation path shared with registration.
 */
class PnjAdminService
{
    /**
     * Races offerable when creating/editing a PNJ: every CHARACTER-kind race
     * in the DB — the races table also carries structure types (palissade…)
     * that must never become a PNJ. All rows carry a faction ('' when none),
     * so PNJ creation can no longer blow up on a missing definition — this is
     * the single source of truth both the dropdown and the server-side
     * whitelist use.
     *
     * @return array<int, string>
     */
    public function availableRaces(): array
    {
        return (new RaceService())->getCharacterRaceNames();
    }

    /**
     * Create a PNJ (npc, negative id) via the canonical put_player path, then
     * stamp lastLoginTime so a freshly-created PNJ reads as active rather than
     * immediately "Inactif" (put_player leaves the column at its 0 default).
     *
     * @return int the new PNJ id
     */
    public function createPnj(string $name, string $race): int
    {
        $id = Player::put_player($name, $race, true);

        (new Db())->exe(
            "UPDATE players SET lastLoginTime = ? WHERE id = ? AND player_type = 'npc'",
            [time(), $id]
        );

        return $id;
    }

    /**
     * Every PNJ with its controlling owner(s), activity flag and XP, name-ordered.
     *
     * @return array<int, array{id:int, name:string, race:string, xp:int, lastLoginTime:int, active:bool, owners:?string, owner_count:int}>
     */
    public function listPnjs(): array
    {
        $sql = "SELECT p.id, p.name, p.race, p.xp, p.lastLoginTime,
                       GROUP_CONCAT(
                           DISTINCT CONCAT(owner.name, ' (#', owner.id, ')')
                           ORDER BY owner.id SEPARATOR ', '
                       ) AS owners,
                       COUNT(DISTINCT pp.player_id) AS owner_count
                FROM players p
                LEFT JOIN players_pnjs pp ON pp.pnj_id = p.id
                LEFT JOIN players owner ON owner.id = pp.player_id
                WHERE p.player_type = 'npc'
                GROUP BY p.id, p.name, p.race, p.xp, p.lastLoginTime
                ORDER BY p.name ASC";

        $db = new Db();
        // GROUP_CONCAT truncates silently at group_concat_max_len (default 1024).
        // Raise it on this connection so a heavily-controlled PNJ's owner list is
        // never cut off (which would read as "fewer owners than there are").
        $db->exe('SET SESSION group_concat_max_len = 1000000');
        $res = $db->exe($sql);

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $lastLoginTime = (int) $row['lastLoginTime'];
            $rows[] = [
                'id'            => (int) $row['id'],
                'name'          => (string) $row['name'],
                'race'          => (string) $row['race'],
                'xp'            => (int) $row['xp'],
                'lastLoginTime' => $lastLoginTime,
                'active'        => !PlayerService::isInactiveSince($lastLoginTime),
                'owners'        => $row['owners'] !== null ? (string) $row['owners'] : null,
                'owner_count'   => (int) $row['owner_count'],
            ];
        }

        return $rows;
    }

    /**
     * A single PNJ summary row, or null when the id is not a PNJ.
     *
     * @return array{id:int, name:string, race:string, xp:int, lastLoginTime:int}|null
     */
    public function getPnj(int $pnjId): ?array
    {
        $res = (new Db())->exe(
            "SELECT id, name, race, xp, lastLoginTime FROM players WHERE id = ? AND player_type = 'npc'",
            [$pnjId]
        );

        if (!$res->num_rows) {
            return null;
        }

        $row = $res->fetch_assoc();

        return [
            'id'            => (int) $row['id'],
            'name'          => (string) $row['name'],
            'race'          => (string) $row['race'],
            'xp'            => (int) $row['xp'],
            'lastLoginTime' => (int) $row['lastLoginTime'],
        ];
    }

    /**
     * The players that control this PNJ.
     *
     * @return array<int, array{player_id:int, name:string, displayed:bool}>
     */
    public function getOwners(int $pnjId): array
    {
        $sql = "SELECT pp.player_id, owner.name, pp.displayed
                FROM players_pnjs pp
                JOIN players owner ON owner.id = pp.player_id
                WHERE pp.pnj_id = ?
                ORDER BY owner.id ASC";

        $res = (new Db())->exe($sql, [$pnjId]);

        $owners = [];
        while ($row = $res->fetch_assoc()) {
            $owners[] = [
                'player_id' => (int) $row['player_id'],
                'name'      => (string) $row['name'],
                'displayed' => (bool) $row['displayed'],
            ];
        }

        return $owners;
    }

    /**
     * Rename a PNJ and/or change its race. Race change keeps avatar and faction
     * consistent, mirroring the derivation in Player::put_player. faction is
     * coerced to '' when the race has none (the column is NOT NULL).
     */
    public function updatePnj(int $pnjId, string $name, string $race): void
    {
        $raceEntity = (new RaceService())->getRaceByName($race);
        $faction = $raceEntity ? $raceEntity->getFaction() : '';

        (new Db())->exe(
            "UPDATE players
             SET name = ?, race = ?, avatar = ?, faction = ?
             WHERE id = ? AND player_type = 'npc'",
            [$name, $race, 'img/avatars/ame/' . $race . '.webp', $faction, $pnjId]
        );
    }

    /** Default plan where retired PNJs are parked (overridable via settings). */
    public const RETIRE_PLAN_DEFAULT = 'pnjs';

    /** admin_settings key holding the configured retirement plan name. */
    public const RETIRE_PLAN_SETTING = 'pnj_retire_plan';

    /**
     * The configured retirement plan name (admin_settings), or the default.
     * Sanitised to a safe slug so it can only ever be a plan identifier.
     */
    public function getRetirePlan(): string
    {
        $plan = (new AdminSettingsService())->get(self::RETIRE_PLAN_SETTING, self::RETIRE_PLAN_DEFAULT);
        $plan = $this->sanitizePlan($plan);

        return $plan !== '' ? $plan : self::RETIRE_PLAN_DEFAULT;
    }

    /**
     * Set the retirement plan name. Returns the stored (sanitised) value, or
     * null if the input sanitised to nothing (rejected).
     */
    public function setRetirePlan(string $plan): ?string
    {
        $plan = $this->sanitizePlan($plan);
        if ($plan === '') {
            return null;
        }

        (new AdminSettingsService())->set(self::RETIRE_PLAN_SETTING, $plan);

        return $plan;
    }

    /** Reduce a plan name to a safe slug: lowercase letters, digits, _ and -. */
    private function sanitizePlan(string $plan): string
    {
        return substr((string) preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($plan))), 0, 64);
    }

    /**
     * Distinct existing plan names (for the retirement-plan autocomplete).
     *
     * @return array<int, string>
     */
    public function listPlans(): array
    {
        $res = (new Db())->exe('SELECT DISTINCT plan FROM coords ORDER BY plan');

        $plans = [];
        while ($row = $res->fetch_assoc()) {
            $plans[] = (string) $row['plan'];
        }

        return $plans;
    }

    /**
     * Soft-retire a PNJ. Nothing is destroyed — the character row and its data
     * survive, so re-assigning it later fully restores it. Three effects:
     *
     *   1. Unassigned from every controlling player (players_pnjs cleared).
     *   2. incognitoMode  — invisible on the map and in events.
     *   3. anonymeMode    — not found in recipient (missive) searches.
     *   4. Teleported to the dedicated 'pnjs' plan, on a free tile, so it leaves
     *      the live world entirely.
     */
    public function softRetire(int $pnjId): void
    {
        $db = new Db();

        // 1. Drop every control link (players_pnjs.pnj_id = this PNJ).
        $db->exe('DELETE FROM players_pnjs WHERE pnj_id = ?', [$pnjId]);

        // 2 & 3. Hide it in-game. No UNIQUE constraint on (player_id, name), so
        // guard each option against a duplicate row.
        $optionsService = new PlayerOptionsService();
        foreach (['incognitoMode', 'anonymeMode'] as $option) {
            if (!$optionsService->hasOption($pnjId, $option)) {
                $optionsService->addOption($pnjId, $option);
            }
        }

        // 4. Park it on the 'pnjs' plan, out of the live world.
        $this->teleportToPnjPlan($pnjId);
    }

    /**
     * Move a PNJ to the first free tile of the retirement plan. get_coords_id
     * creates the tile (and the plan) on demand; the coords_id is set directly,
     * mirroring how put_player initialises position (no movement side effects).
     */
    private function teleportToPnjPlan(int $pnjId): void
    {
        $plan = $this->getRetirePlan();
        [$x, $y] = $this->firstFreeTileOnRetirePlan($plan);

        $coordsId = View::get_coords_id((object) [
            'x'    => $x,
            'y'    => $y,
            'z'    => 0,
            'plan' => $plan,
        ]);

        if ($coordsId !== null) {
            (new Db())->exe(
                "UPDATE players SET coords_id = ? WHERE id = ? AND player_type = 'npc'",
                [$coordsId, $pnjId]
            );
        }
    }

    /**
     * First (x, y) on the retirement plan not occupied by a player, scanning a
     * bounded grid so retired PNJs don't stack on one tile. Falls back to (0,0)
     * if the grid is somehow full.
     *
     * @return array{0:int, 1:int}
     */
    private function firstFreeTileOnRetirePlan(string $plan): array
    {
        $res = (new Db())->exe(
            'SELECT c.x, c.y
             FROM players p
             JOIN coords c ON c.id = p.coords_id
             WHERE c.plan = ?',
            [$plan]
        );

        $occupied = [];
        while ($row = $res->fetch_assoc()) {
            $occupied[(int) $row['x'] . ',' . (int) $row['y']] = true;
        }

        $side = 100;
        for ($y = 0; $y < $side; $y++) {
            for ($x = 0; $x < $side; $x++) {
                if (!isset($occupied[$x . ',' . $y])) {
                    return [$x, $y];
                }
            }
        }

        return [0, 0];
    }
}
