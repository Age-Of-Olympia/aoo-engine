<?php

namespace App\Service;

use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionPassiveCatalogService;
use Classes\Db;

/**
 * Read access for the admin "player loadout" pages: resolve a player by
 * matricule or name, and expose the lightweight summary row the loadout
 * editor renders in its header.
 *
 * This sits alongside PlayerActionsService / PlayerPassiveService (which own
 * the per-player actions/passives rows). It deliberately does NOT exclude PNJs
 * or anonyme-mode players the way PlayerService::searchNonAnonymePlayer does:
 * an admin editing loadouts needs to reach every character, not just the
 * public roster.
 */
class PlayerLoadoutService
{
    /** Columns surfaced in search results and the loadout header. */
    private const SUMMARY_FIELDS = 'id, name, race, player_type, xp';

    /**
     * Resolve players for the admin picker.
     *
     * A purely numeric term is treated as a matricule (exact id match); any
     * other term is a name LIKE search. Results are name-ordered and capped.
     *
     * @return array<int, array{id:int, name:string, race:string, player_type:string, xp:int}>
     */
    public function searchPlayers(string $term, int $limit = 50): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $db = new Db();

        if (ctype_digit($term)) {
            $sql = 'SELECT ' . self::SUMMARY_FIELDS . ' FROM players WHERE id = ?';
            $res = $db->exe($sql, [(int) $term]);
        } else {
            // $limit is an internal int (cast here), never user input, so
            // inlining it avoids mysqli binding LIMIT as a quoted string.
            $sql = 'SELECT ' . self::SUMMARY_FIELDS . '
                    FROM players
                    WHERE name LIKE ?
                    ORDER BY name ASC
                    LIMIT ' . (int) $limit;
            $res = $db->exe($sql, ['%' . $term . '%']);
        }

        return $this->hydrateRows($res);
    }

    /**
     * Load a single player's summary row, or null when no such id exists.
     *
     * @return array{id:int, name:string, race:string, player_type:string, xp:int}|null
     */
    public function getPlayerSummary(int $playerId): ?array
    {
        $db = new Db();
        $res = $db->exe(
            'SELECT ' . self::SUMMARY_FIELDS . ' FROM players WHERE id = ?',
            [$playerId]
        );

        $rows = $this->hydrateRows($res);

        return $rows[0] ?? null;
    }

    /**
     * Reconcile a player's catalogued actions/passives to the desired sets and
     * report what changed.
     *
     * Orphan-safe by construction: only names/ids that exist in the catalog are
     * ever touched. Anything the player owns that is not in the catalog (e.g.
     * the base attack 'attaquer', which the catalog does not model) is left
     * exactly as-is, and any desired name/id absent from the catalog is ignored.
     * So this can neither revoke the base attack by omission nor inject an
     * arbitrary players_actions row from forged input.
     *
     * @param array<int, string> $desiredActionNames names the form submitted as checked
     * @param array<int, int>    $desiredPassiveIds   passive ids the form submitted as checked
     * @return array{actions_added:int, actions_removed:int, passives_added:int, passives_removed:int}
     */
    public function applyLoadout(int $playerId, array $desiredActionNames, array $desiredPassiveIds): array
    {
        $catalogActionNames = array_map(
            static fn($action) => $action->getName(),
            (new ActionCatalogService())->listActions()
        );
        $catalogPassiveIds = array_map(
            static fn($passive) => $passive->getId(),
            (new ActionPassiveCatalogService())->listPassives()
        );

        // Whitelist the desired sets against the catalog universe — forged or
        // stale values that are not catalogued are silently dropped.
        $desiredActions = array_values(array_intersect($desiredActionNames, $catalogActionNames));
        $desiredPassives = array_values(array_intersect(
            array_map('intval', $desiredPassiveIds),
            $catalogPassiveIds
        ));

        $actionsService = new PlayerActionsService();
        $passiveService = new PlayerPassiveService();

        $currentActions = $actionsService->getActions($playerId);
        $currentPassiveIds = array_map(
            static fn($passive) => $passive->getId(),
            $passiveService->getPassivesByPlayerId($playerId)
        );

        // Diff is scoped to the catalog, so owned-but-uncatalogued entries
        // (orphans) never enter the remove set.
        $actionsToAdd = array_diff($desiredActions, $currentActions);
        $actionsToRemove = array_diff(
            array_intersect($currentActions, $catalogActionNames),
            $desiredActions
        );
        $passivesToAdd = array_diff($desiredPassives, $currentPassiveIds);
        $passivesToRemove = array_diff(
            array_intersect($currentPassiveIds, $catalogPassiveIds),
            $desiredPassives
        );

        // One transaction around all four loops so a mid-sequence failure
        // (e.g. a passive insert blowing up) leaves no half-applied loadout.
        $connection = \db();
        $connection->beginTransaction();
        try {
            foreach ($actionsToAdd as $name) {
                $actionsService->addAction($playerId, $name);
            }
            foreach ($actionsToRemove as $name) {
                $actionsService->endAction($playerId, $name);
            }
            foreach ($passivesToAdd as $passiveId) {
                $passiveService->addPassiveByPlayerId($playerId, (int) $passiveId);
            }
            foreach ($passivesToRemove as $passiveId) {
                $passiveService->removePassiveByPlayerId($playerId, (int) $passiveId);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $exception;
        }

        return [
            'actions_added'    => count($actionsToAdd),
            'actions_removed'  => count($actionsToRemove),
            'passives_added'   => count($passivesToAdd),
            'passives_removed' => count($passivesToRemove),
        ];
    }

    /**
     * @return array<int, array{id:int, name:string, race:string, player_type:string, xp:int}>
     */
    private function hydrateRows(\mysqli_result $res): array
    {
        $players = [];
        while ($row = $res->fetch_assoc()) {
            $players[] = [
                'id'          => (int) $row['id'],
                'name'        => (string) $row['name'],
                'race'        => (string) $row['race'],
                'player_type' => (string) $row['player_type'],
                'xp'          => (int) $row['xp'],
            ];
        }

        return $players;
    }
}
