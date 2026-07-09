<?php

namespace App\Service;

use Classes\Db;

/**
 * Read + light-write access for the admin PNJ management page (admin/pnjs.php).
 *
 * A "PNJ" is a character row with player_type = 'npc' (negative id). This
 * service exposes the roster (with the players that control each PNJ, its
 * activity and XP), the per-PNJ owner list, and the soft-retire operation.
 *
 * Assignment (linking a PNJ to a controlling player) stays in PlayerPnjService,
 * which owns the players_pnjs table; this service only reads that table for the
 * roster and clears it on retire. PNJ creation stays in Player::put_player, the
 * canonical creation path shared with registration.
 */
class PnjAdminService
{
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

        $res = (new Db())->exe($sql);

        // Same INACTIVE_TIME cutoff as the roster / PlayerService::isInactive.
        $activeThreshold = time() - INACTIVE_TIME;

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $lastLoginTime = (int) $row['lastLoginTime'];
            $rows[] = [
                'id'            => (int) $row['id'],
                'name'          => (string) $row['name'],
                'race'          => (string) $row['race'],
                'xp'            => (int) $row['xp'],
                'lastLoginTime' => $lastLoginTime,
                'active'        => $lastLoginTime >= $activeThreshold,
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
     * coerced to '' when the race JSON has none (the column is NOT NULL).
     */
    public function updatePnj(int $pnjId, string $name, string $race): void
    {
        $raceJson = \json()->decode('races', $race);
        $faction = isset($raceJson->faction) ? (string) $raceJson->faction : '';

        (new Db())->exe(
            "UPDATE players
             SET name = ?, race = ?, avatar = ?, faction = ?
             WHERE id = ? AND player_type = 'npc'",
            [$name, $race, 'img/avatars/ame/' . $race . '.webp', $faction, $pnjId]
        );
    }

    /**
     * Soft-retire a PNJ: unassign it from every controlling player and hide it
     * on the map (incognitoMode). Nothing is destroyed — the character row and
     * its data survive, so re-assigning it later fully restores it.
     */
    public function softRetire(int $pnjId): void
    {
        $db = new Db();

        // Drop every control link (players_pnjs.pnj_id = this PNJ).
        $db->exe('DELETE FROM players_pnjs WHERE pnj_id = ?', [$pnjId]);

        // Ensure it is hidden on the map. No UNIQUE constraint on
        // (player_id, name), so guard against a duplicate incognitoMode row.
        $optionsService = new PlayerOptionsService();
        if (!$optionsService->hasOption($pnjId, 'incognitoMode')) {
            $optionsService->addOption($pnjId, 'incognitoMode');
        }
    }
}
