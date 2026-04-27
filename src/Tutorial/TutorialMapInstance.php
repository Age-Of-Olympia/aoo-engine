<?php

namespace App\Tutorial;

use Doctrine\DBAL\Connection;
use Classes\Db;

/**
 * TutorialMapInstance - Creates isolated map instances for tutorial sessions
 *
 * Each tutorial session gets its own map instance by copying the template tutorial map.
 * This prevents:
 * - Resource depletion conflicts (Player A harvests, Player B sees depleted)
 * - NPC state conflicts (Player A damages NPC, Player B sees damaged NPC)
 * - Any other map state interference between concurrent tutorial players
 *
 * Architecture:
 * - Template map: plan='tutorial' (source, never modified)
 * - Instance maps: plan='tut_{first-10-of-uuid}' (per session, deleted on completion)
 */
class TutorialMapInstance
{
    private Connection $conn;
    private Db $db;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
        $this->db = new Db();
    }

    /**
     * Create a new tutorial map instance for a session.
     *
     * Copies the template tutorial map to a session-specific instance and
     * returns the coords_id of the scenario's spawn tile on the new instance.
     *
     * The spawn tile must exist on the template plan — it's picked up by the
     * copy and looked up by (x,y,0). If the catalog points at a tile the
     * template doesn't include, this throws.
     *
     * @param string $sessionId Tutorial session UUID
     * @param string $templatePlan Template plan to copy (defaults to 'tutorial')
     * @param int $spawnX Spawn X coordinate on the template (defaults to 0)
     * @param int $spawnY Spawn Y coordinate on the template (defaults to 0)
     * @return array ['plan_name' => string, 'starting_coords_id' => int]
     * @throws \RuntimeException if template map doesn't exist, copy fails, or the spawn tile is absent
     */
    public function createInstance(
        string $sessionId,
        string $templatePlan = 'tutorial',
        int $spawnX = 0,
        int $spawnY = 0
    ): array {
        // Shorten plan name to fit coords_computed (varchar(35))
        // Format: tut_XXXXXXXXXX (max 14 chars to leave room for coords like "-10_-10_0_")
        $instancePlanName = 'tut_' . substr($sessionId, 0, 10);


        // Step 1: Verify template map exists
        $templateCheck = $this->conn->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [$templatePlan]);
        if ($templateCheck == 0) {
            throw new \RuntimeException("Template tutorial map (plan='{$templatePlan}') not found.");
        }

        // Step 1.5: Copy plan JSON file for resource definitions
        $templateJsonPath = __DIR__ . '/../../datas/private/plans/' . $templatePlan . '.json';
        $instanceJsonPath = __DIR__ . '/../../datas/private/plans/' . $instancePlanName . '.json';

        if (!file_exists($templateJsonPath)) {
            throw new \RuntimeException("Template plan JSON not found: {$templateJsonPath}");
        }

        // Read template JSON and modify plan name
        $templateJson = json_decode(file_get_contents($templateJsonPath), true);
        $templateJson['name'] = 'Tutoriel Instance';
        $templateJson['shortName'] = 'Tuto';

        // Write instance JSON
        file_put_contents($instanceJsonPath, json_encode($templateJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Step 2: Copy coords from template
        $sql = "
            INSERT INTO coords (x, y, z, plan)
            SELECT x, y, z, ? as plan
            FROM coords
            WHERE plan = ?
        ";
        $coordsCopied = $this->conn->executeStatement($sql, [$instancePlanName, $templatePlan]);

        // Step 3: Create mapping of old coords_id to new coords_id
        // We'll use a temp table for efficient mapping
        $coordsMapping = [];

        $templateCoords = $this->conn->fetchAllAssociative("
            SELECT id, x, y, z FROM coords WHERE plan = ?
        ", [$templatePlan]);

        $instanceCoords = $this->conn->fetchAllAssociative("
            SELECT id, x, y, z FROM coords WHERE plan = ?
        ", [$instancePlanName]);

        // Map by x,y,z coordinates
        foreach ($templateCoords as $templateCoord) {
            foreach ($instanceCoords as $instanceCoord) {
                if ($templateCoord['x'] == $instanceCoord['x'] &&
                    $templateCoord['y'] == $instanceCoord['y'] &&
                    $templateCoord['z'] == $instanceCoord['z']) {
                    $coordsMapping[$templateCoord['id']] = $instanceCoord['id'];
                    break;
                }
            }
        }


        // Step 4: Copy map_walls (resources like trees/stones)
        $this->copyMapElements('walls', $coordsMapping, ['name', 'player_id', 'damages'], $templatePlan);

        // Step 5: Copy NPCs (negative player IDs) to instance
        $this->copyNPCs($coordsMapping, $instancePlanName, $templatePlan);

        // Step 6: Copy other map elements if they exist on template map
        $mapElementTypes = ['tiles', 'foregrounds', 'triggers', 'elements', 'dialogs', 'plants', 'routes'];

        foreach ($mapElementTypes as $type) {
            $count = $this->conn->fetchOne("
                SELECT COUNT(*) FROM map_{$type} mw
                INNER JOIN coords c ON mw.coords_id = c.id
                WHERE c.plan = ?
            ", [$templatePlan]);

            if ($count > 0) {
                $this->copyMapElements($type, $coordsMapping, ['name', 'params'], $templatePlan);
            }
        }

        // Step 6: Get starting position coords_id from the catalog spawn.
        $startingCoordsId = $this->conn->fetchOne("
            SELECT id FROM coords
            WHERE plan = ? AND x = ? AND y = ? AND z = 0
        ", [$instancePlanName, $spawnX, $spawnY]);

        if (!$startingCoordsId) {
            throw new \RuntimeException(
                "Failed to find spawn position ({$spawnX},{$spawnY}) on instance of template '{$templatePlan}'"
            );
        }


        return [
            'plan_name' => $instancePlanName,
            'starting_coords_id' => (int) $startingCoordsId
        ];
    }

    /**
     * Copy NPCs from template to instance
     *
     * NPCs (negative player IDs) need to be duplicated for each instance.
     * Creates new NPC copies with new negative IDs on instance coords.
     *
     * @param array $coordsMapping Old coords_id => new coords_id mapping
     * @param string $instancePlanName Instance plan name
     * @param string $templatePlan Template plan name
     */
    private function copyNPCs(array $coordsMapping, string $instancePlanName, string $templatePlan = 'tutorial'): void
    {
        // Get all NPCs from template map
        $npcs = $this->conn->fetchAllAssociative("
            SELECT p.* FROM players p
            INNER JOIN coords c ON p.coords_id = c.id
            WHERE c.plan = ? AND p.id < 0
        ", [$templatePlan]);

        if (empty($npcs)) {
            return;
        }

        $copiedCount = 0;
        foreach ($npcs as $npc) {
            $oldCoordsId = $npc['coords_id'];

            if (!isset($coordsMapping[$oldCoordsId])) {
                continue;
            }

            $newCoordsId = $coordsMapping[$oldCoordsId];

            // Create a copy of the NPC with a new negative ID
            // We'll generate a new negative ID based on timestamp to avoid conflicts
            $newNpcId = -(time() + $copiedCount);  // Negative ID for NPC

            // Copy all NPC data except id and coords_id.
            // player_type must be 'npc' — without it the column default
            // ('real') applies and the copy is misclassified, breaking
            // every callsite that filters on player_type (rankings,
            // get_player_by_name, STI hydration, admin filters).
            $npcData = [
                'id' => $newNpcId,
                'player_type' => 'npc',
                'name' => $npc['name'],
                'coords_id' => $newCoordsId,
                'race' => $npc['race'],
                'psw' => $npc['psw'] ?? '',
                'mail' => $npc['mail'] ?? '',
                'plain_mail' => $npc['plain_mail'] ?? '',
                'xp' => $npc['xp'] ?? 0,
                'pi' => $npc['pi'] ?? 0,
                'energie' => $npc['energie'] ?? 100,
                'avatar' => $npc['avatar'] ?? '',
                'portrait' => $npc['portrait'] ?? '',
                'text' => $npc['text'] ?? ''
            ];

            $this->conn->insert('players', $npcData);
            $copiedCount++;
        }

    }

    /**
     * Copy map elements from template to instance
     *
     * @param string $elementType Map element type (walls, tiles, etc.)
     * @param array $coordsMapping Old coords_id => new coords_id mapping
     * @param array $columnsToCopy Additional columns to copy beyond coords_id
     * @param string $templatePlan Template plan name
     */
    private function copyMapElements(string $elementType, array $coordsMapping, array $columnsToCopy, string $templatePlan = 'tutorial'): void
    {
        // Get all elements from template map
        $templateElements = $this->conn->fetchAllAssociative("
            SELECT me.* FROM map_{$elementType} me
            INNER JOIN coords c ON me.coords_id = c.id
            WHERE c.plan = ?
        ", [$templatePlan]);

        $copiedCount = 0;
        foreach ($templateElements as $element) {
            $oldCoordsId = $element['coords_id'];

            if (!isset($coordsMapping[$oldCoordsId])) {
                continue;
            }

            $newCoordsId = $coordsMapping[$oldCoordsId];

            // Build insert data
            $insertData = ['coords_id' => $newCoordsId];

            foreach ($columnsToCopy as $column) {
                if (isset($element[$column])) {
                    $insertData[$column] = $element[$column];
                }
            }

            // Insert into instance
            $this->conn->insert("map_{$elementType}", $insertData);
            $copiedCount++;
        }

        if ($copiedCount > 0) {
        }
    }

    /**
     * Delete a tutorial map instance
     *
     * Removes all coords and map elements for a session-specific instance.
     * Called when tutorial completes or is cancelled.
     *
     * @param string $sessionId Tutorial session UUID
     */
    public function deleteInstance(string $sessionId): void
    {
        $instancePlanName = 'tut_' . substr($sessionId, 0, 10);


        // Get all coords IDs for this instance
        $coordsIds = $this->conn->fetchFirstColumn("
            SELECT id FROM coords WHERE plan = ?
        ", [$instancePlanName]);

        if (empty($coordsIds)) {
            return;
        }

        $coordsIdList = implode(',', $coordsIds);

        // First, get all NPC IDs on this instance
        $npcIds = $this->conn->fetchFirstColumn("
            SELECT id FROM players WHERE coords_id IN ({$coordsIdList}) AND id < 0
        ");

        // Delete foreign key references for each NPC
        if (!empty($npcIds)) {
            $npcIdList = implode(',', $npcIds);

            // Delete all foreign key references
            $this->conn->executeStatement("DELETE FROM players_logs WHERE player_id IN ({$npcIdList}) OR target_id IN ({$npcIdList})");
            $this->conn->executeStatement("DELETE FROM players_actions WHERE player_id IN ({$npcIdList})");
            $this->conn->executeStatement("DELETE FROM players_items WHERE player_id IN ({$npcIdList})");
            $this->conn->executeStatement("DELETE FROM players_effects WHERE player_id IN ({$npcIdList})");
            $this->conn->executeStatement("DELETE FROM players_kills WHERE player_id IN ({$npcIdList}) OR target_id IN ({$npcIdList})");
            $this->conn->executeStatement("DELETE FROM players_assists WHERE player_id IN ({$npcIdList}) OR target_id IN ({$npcIdList})");

            // Now safe to delete NPCs
            $npcsDeleted = $this->conn->executeStatement("
                DELETE FROM players WHERE id IN ({$npcIdList})
            ");

            if ($npcsDeleted > 0) {
            }
        }

        // Delete all map elements
        $mapElementTypes = ['walls', 'tiles', 'foregrounds', 'triggers', 'elements', 'dialogs', 'plants', 'routes'];

        foreach ($mapElementTypes as $type) {
            $deleted = $this->conn->executeStatement("
                DELETE FROM map_{$type} WHERE coords_id IN ({$coordsIdList})
            ");

            if ($deleted > 0) {
            }
        }

        // Delete coords
        $deleted = $this->conn->executeStatement("
            DELETE FROM coords WHERE plan = ?
        ", [$instancePlanName]);

        // Delete plan JSON file
        $instanceJsonPath = __DIR__ . '/../../datas/private/plans/' . $instancePlanName . '.json';
        if (file_exists($instanceJsonPath)) {
            unlink($instanceJsonPath);
        }

    }

    /**
     * Delete instance by plan name (helper method)
     *
     * @param string $planName Full plan name (e.g., 'tutorial_session_abc123')
     */
    public function deleteInstanceByPlan(string $planName): void
    {

        // Get all coords IDs for this instance
        $coordsIds = $this->conn->fetchFirstColumn("
            SELECT id FROM coords WHERE plan = ?
        ", [$planName]);

        if (empty($coordsIds)) {
            return;
        }

        $coordsIdList = implode(',', $coordsIds);

        // Delete NPCs on this instance
        $npcsDeleted = $this->conn->executeStatement("
            DELETE FROM players WHERE coords_id IN ({$coordsIdList}) AND id < 0
        ");

        if ($npcsDeleted > 0) {
        }

        // Delete all map elements
        $mapElementTypes = ['walls', 'tiles', 'foregrounds', 'triggers', 'elements', 'dialogs', 'plants', 'routes'];

        foreach ($mapElementTypes as $type) {
            $deleted = $this->conn->executeStatement("
                DELETE FROM map_{$type} WHERE coords_id IN ({$coordsIdList})
            ");

            if ($deleted > 0) {
            }
        }

        // Delete coords
        $deleted = $this->conn->executeStatement("
            DELETE FROM coords WHERE plan = ?
        ", [$planName]);

        // Delete plan JSON file
        $instanceJsonPath = __DIR__ . '/../../datas/private/plans/' . $planName . '.json';
        if (file_exists($instanceJsonPath)) {
            unlink($instanceJsonPath);
        }

    }

}
