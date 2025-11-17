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
 * - Instance maps: plan='tutorial_session_{uuid}' (one per session, deleted on completion)
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
     * Create a new tutorial map instance for a session
     *
     * Copies the template tutorial map (plan='tutorial') to a session-specific instance.
     * Returns the coords_id of the starting position (0,0) on the new instance.
     *
     * @param string $sessionId Tutorial session UUID
     * @return array ['plan_name' => string, 'starting_coords_id' => int]
     * @throws \RuntimeException if template map doesn't exist or copy fails
     */
    public function createInstance(string $sessionId): array
    {
        // Shorten plan name to fit coords_computed (varchar(35))
        // Format: tut_XXXXXXXXXX (max 14 chars to leave room for coords like "-10_-10_0_")
        $instancePlanName = 'tut_' . substr($sessionId, 0, 10);

        error_log("[TutorialMapInstance] Creating map instance for session {$sessionId}: {$instancePlanName}");

        // Step 1: Verify template map exists
        $templateCheck = $this->conn->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', ['tutorial']);
        if ($templateCheck == 0) {
            throw new \RuntimeException("Template tutorial map (plan='tutorial') not found. Run scripts/create_tutorial_map.php first.");
        }

        // Step 1.5: Copy plan JSON file for resource definitions
        $templateJsonPath = __DIR__ . '/../../datas/private/plans/tutorial.json';
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
        error_log("[TutorialMapInstance] Created plan JSON: {$instanceJsonPath}");

        // Step 2: Copy coords from template
        $sql = "
            INSERT INTO coords (x, y, z, plan)
            SELECT x, y, z, ? as plan
            FROM coords
            WHERE plan = 'tutorial'
        ";
        $coordsCopied = $this->conn->executeStatement($sql, [$instancePlanName]);
        error_log("[TutorialMapInstance] Copied {$coordsCopied} coords from template");

        // Step 3: Create mapping of old coords_id to new coords_id
        // We'll use a temp table for efficient mapping
        $coordsMapping = [];

        $templateCoords = $this->conn->fetchAllAssociative("
            SELECT id, x, y, z FROM coords WHERE plan = 'tutorial'
        ");

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

        error_log("[TutorialMapInstance] Created mapping for " . count($coordsMapping) . " coords");

        // Step 4: Copy map_walls (resources like trees/stones)
        $this->copyMapElements('walls', $coordsMapping, ['name', 'player_id', 'damages']);

        // Step 5: Copy NPCs (negative player IDs) to instance
        $this->copyNPCs($coordsMapping, $instancePlanName);

        // Step 6: Copy other map elements if they exist on tutorial map
        $mapElementTypes = ['tiles', 'foregrounds', 'triggers', 'elements', 'dialogs', 'plants', 'routes'];

        foreach ($mapElementTypes as $type) {
            $count = $this->conn->fetchOne("
                SELECT COUNT(*) FROM map_{$type} mw
                INNER JOIN coords c ON mw.coords_id = c.id
                WHERE c.plan = 'tutorial'
            ");

            if ($count > 0) {
                $this->copyMapElements($type, $coordsMapping, ['name', 'params']);
            }
        }

        // Step 6: Get starting position (0,0) coords_id
        $startingCoordsId = $this->conn->fetchOne("
            SELECT id FROM coords
            WHERE plan = ? AND x = 0 AND y = 0 AND z = 0
        ", [$instancePlanName]);

        if (!$startingCoordsId) {
            throw new \RuntimeException("Failed to find starting position (0,0) on instance map");
        }

        error_log("[TutorialMapInstance] Instance created successfully. Starting coords_id: {$startingCoordsId}");

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
     */
    private function copyNPCs(array $coordsMapping, string $instancePlanName): void
    {
        // Get all NPCs from template map
        $npcs = $this->conn->fetchAllAssociative("
            SELECT p.* FROM players p
            INNER JOIN coords c ON p.coords_id = c.id
            WHERE c.plan = 'tutorial' AND p.id < 0
        ");

        if (empty($npcs)) {
            error_log("[TutorialMapInstance] No NPCs found on template map");
            return;
        }

        $copiedCount = 0;
        foreach ($npcs as $npc) {
            $oldCoordsId = $npc['coords_id'];

            if (!isset($coordsMapping[$oldCoordsId])) {
                error_log("[TutorialMapInstance] Warning: No mapping found for NPC coords_id {$oldCoordsId}");
                continue;
            }

            $newCoordsId = $coordsMapping[$oldCoordsId];

            // Create a copy of the NPC with a new negative ID
            // We'll generate a new negative ID based on timestamp to avoid conflicts
            $newNpcId = -(time() + $copiedCount);  // Negative ID for NPC

            // Copy all NPC data except id and coords_id
            $npcData = [
                'id' => $newNpcId,
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

        error_log("[TutorialMapInstance] Copied {$copiedCount} NPCs to instance");
    }

    /**
     * Copy map elements from template to instance
     *
     * @param string $elementType Map element type (walls, tiles, etc.)
     * @param array $coordsMapping Old coords_id => new coords_id mapping
     * @param array $columnsToCopy Additional columns to copy beyond coords_id
     */
    private function copyMapElements(string $elementType, array $coordsMapping, array $columnsToCopy): void
    {
        // Get all elements from template map
        $templateElements = $this->conn->fetchAllAssociative("
            SELECT me.* FROM map_{$elementType} me
            INNER JOIN coords c ON me.coords_id = c.id
            WHERE c.plan = 'tutorial'
        ");

        $copiedCount = 0;
        foreach ($templateElements as $element) {
            $oldCoordsId = $element['coords_id'];

            if (!isset($coordsMapping[$oldCoordsId])) {
                error_log("[TutorialMapInstance] Warning: No mapping found for coords_id {$oldCoordsId}");
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
            error_log("[TutorialMapInstance] Copied {$copiedCount} {$elementType} from template");
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

        error_log("[TutorialMapInstance] Deleting map instance: {$instancePlanName}");

        // Get all coords IDs for this instance
        $coordsIds = $this->conn->fetchFirstColumn("
            SELECT id FROM coords WHERE plan = ?
        ", [$instancePlanName]);

        if (empty($coordsIds)) {
            error_log("[TutorialMapInstance] No coords found for instance {$instancePlanName}");
            return;
        }

        $coordsIdList = implode(',', $coordsIds);

        // Delete NPCs on this instance (negative player IDs)
        $npcsDeleted = $this->conn->executeStatement("
            DELETE FROM players WHERE coords_id IN ({$coordsIdList}) AND id < 0
        ");

        if ($npcsDeleted > 0) {
            error_log("[TutorialMapInstance] Deleted {$npcsDeleted} NPCs from instance");
        }

        // Delete all map elements
        $mapElementTypes = ['walls', 'tiles', 'foregrounds', 'triggers', 'elements', 'dialogs', 'plants', 'routes'];

        foreach ($mapElementTypes as $type) {
            $deleted = $this->conn->executeStatement("
                DELETE FROM map_{$type} WHERE coords_id IN ({$coordsIdList})
            ");

            if ($deleted > 0) {
                error_log("[TutorialMapInstance] Deleted {$deleted} {$type} from instance");
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
            error_log("[TutorialMapInstance] Deleted plan JSON: {$instanceJsonPath}");
        }

        error_log("[TutorialMapInstance] Deleted {$deleted} coords. Instance cleanup complete.");
    }

    /**
     * Delete instance by plan name (helper method)
     *
     * @param string $planName Full plan name (e.g., 'tutorial_session_abc123')
     */
    public function deleteInstanceByPlan(string $planName): void
    {
        error_log("[TutorialMapInstance] Deleting map instance by plan name: {$planName}");

        // Get all coords IDs for this instance
        $coordsIds = $this->conn->fetchFirstColumn("
            SELECT id FROM coords WHERE plan = ?
        ", [$planName]);

        if (empty($coordsIds)) {
            error_log("[TutorialMapInstance] No coords found for plan {$planName}");
            return;
        }

        $coordsIdList = implode(',', $coordsIds);

        // Delete NPCs on this instance
        $npcsDeleted = $this->conn->executeStatement("
            DELETE FROM players WHERE coords_id IN ({$coordsIdList}) AND id < 0
        ");

        if ($npcsDeleted > 0) {
            error_log("[TutorialMapInstance] Deleted {$npcsDeleted} NPCs");
        }

        // Delete all map elements
        $mapElementTypes = ['walls', 'tiles', 'foregrounds', 'triggers', 'elements', 'dialogs', 'plants', 'routes'];

        foreach ($mapElementTypes as $type) {
            $deleted = $this->conn->executeStatement("
                DELETE FROM map_{$type} WHERE coords_id IN ({$coordsIdList})
            ");

            if ($deleted > 0) {
                error_log("[TutorialMapInstance] Deleted {$deleted} {$type}");
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
            error_log("[TutorialMapInstance] Deleted plan JSON: {$instanceJsonPath}");
        }

        error_log("[TutorialMapInstance] Deleted {$deleted} coords. Cleanup complete.");
    }

    /**
     * Check if a map instance exists
     *
     * @param string $sessionId Tutorial session UUID
     * @return bool
     */
    public function instanceExists(string $sessionId): bool
    {
        $instancePlanName = 'tutorial_session_' . substr($sessionId, 0, 13);

        $count = $this->conn->fetchOne("
            SELECT COUNT(*) FROM coords WHERE plan = ?
        ", [$instancePlanName]);

        return $count > 0;
    }
}
