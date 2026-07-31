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
 *
 * @see \App\Service\PlanAdminService admin-side plan cloning/deletion (set-based
 *      SQL, no NPC spawning) — a future cleanup may migrate this class onto it.
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


        /* Step 4: les ressources du modèle, reposées sur l'instance.
         *
         * Ce n'est plus une copie de lignes : ce sont des entités, et copier
         * `map_resources` ne rapportait plus rien — une instance de tutoriel
         * naissait sans un seul arbre, et l'étape de récolte n'avait rien à
         * récolter. Le réconciliateur relit le modèle et pose la même chose
         * ici, aux mêmes (x, y, z). */
        $resources = new \App\Service\Map\ResourceReconciler();
        $resources->reconcile($instancePlanName, $resources->asPayloadRows($templatePlan));

        // Step 5: Spawn template NPCs from tutorial_npcs config (replaces
        // the legacy "copy any NPC sitting on plan='tutorial'" pass).
        $this->spawnTemplateNpcs($instancePlanName);

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
     * Spawn template NPCs onto the per-session instance plan.
     *
     * Replaces the legacy copyNPCs pass that read whatever id<0 row
     * sat on the template plan. Source of truth is now tutorial_npcs
     * (spawn_mode='template') — admins manage the roster via the
     * admin UI, not by hand-editing players rows.
     *
     * Each template NPC config produces ONE new players row at the
     * configured (x,y) on the instance plan, with a fresh negative id.
     * coords are looked up / created for the instance plan as needed.
     */
    private function spawnTemplateNpcs(string $instancePlanName, string $version = '1.0.0'): void
    {
        $repo = new TutorialNpcRepository($this->conn);
        $templateNpcs = $repo->listActive($version, 'template');
        if (empty($templateNpcs)) {
            return;
        }

        foreach ($templateNpcs as $i => $npc) {
            // Resolve / create coords for (x,y) on the instance plan.
            $coordsId = $this->conn->fetchOne(
                "SELECT id FROM coords WHERE plan = ? AND x = ? AND y = ? AND z = 0",
                [$instancePlanName, $npc['x'], $npc['y']]
            );
            if (!$coordsId) {
                $this->conn->insert('coords', [
                    'plan' => $instancePlanName,
                    'x' => $npc['x'],
                    'y' => $npc['y'],
                    'z' => 0,
                ]);
                $coordsId = (int) $this->conn->lastInsertId();
            }

            // Spread negative ids across the seconds-resolution clock
            // so concurrent session creations don't collide on the same
            // ts. (i offset within the loop too.)
            $newNpcId = -(time() + $i);

            $this->conn->insert('players', [
                'id' => $newNpcId,
                'player_type' => 'npc',
                'name' => $npc['name'],
                'coords_id' => (int) $coordsId,
                'race' => $npc['race'],
                'psw' => '',
                'mail' => '',
                'plain_mail' => '',
                'xp' => 0,
                'pi' => 0,
                'energie' => $npc['energie'],
                'avatar' => $npc['avatar'],
                'portrait' => $npc['portrait'],
                'text' => $npc['text'] ?? '',
            ]);
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

        /* Toutes les entités posées sur l'instance, pas seulement les PNJ.
         *
         * Le prédicat « id < 0 » datait d'un monde où seuls les PNJ étaient
         * des lignes players. Depuis la conversion des murs, un bâtiment
         * porte un id POSITIF (plage 20 000 000+) : il survivait au ménage,
         * puis faisait échouer le DELETE des coords sur la clé étrangère —
         * chaque session abandonnée laissait donc un plan entier derrière
         * elle.
         *
         * Liste noire : tout ce qui est posé sur un plan d'instance s'en va,
         * sauf ce qui doit lui survivre. Un type d'entité ajouté plus tard —
         * ressource, décor — sera nettoyé sans qu'on ait à y repenser. */
        $npcIds = $this->conn->fetchFirstColumn("
            SELECT id FROM players
            WHERE coords_id IN ({$coordsIdList})
            AND player_type NOT IN ('real', 'tutorial')
        ");

        $this->purgeEntities($npcIds);

        // Delete all map elements
        $mapElementTypes = ['resources', 'tiles', 'foregrounds', 'triggers', 'elements', 'dialogs', 'plants', 'routes'];

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

        /* Même correction qu'en deleteInstance : « id < 0 » laissait derrière
         * lui les bâtiments, dont l'id est positif depuis la conversion des
         * murs, et le DELETE des coords échouait ensuite sur la clé
         * étrangère. Ce chemin ne démontait par ailleurs AUCUNE référence
         * avant de supprimer la ligne players — il passe par le même
         * démontage que sa jumelle. */
        $this->purgeEntities($this->conn->fetchFirstColumn("
            SELECT id FROM players
            WHERE coords_id IN ({$coordsIdList})
            AND player_type NOT IN ('real', 'tutorial')
        "));

        // Delete all map elements
        $mapElementTypes = ['resources', 'tiles', 'foregrounds', 'triggers', 'elements', 'dialogs', 'plants', 'routes'];

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

    /**
     * Démonte les entités d'une instance : satellites, références croisées,
     * puis la ligne players elle-même.
     *
     * Les deux chemins de suppression faisaient chacun leur version de ce
     * ménage — l'un incomplet, l'autre inexistant. Ils partagent désormais
     * celle-ci, qui couvre aussi `players_bonus`, `buildings` et
     * `unique_objects` : trois clés étrangères vers players.id qu'aucune des
     * deux ne défaisait, et qui font échouer la suppression des coords dès
     * qu'une structure a été posée ou blessée sur l'instance.
     *
     * @param list<int|string> $entityIds
     */
    private function purgeEntities(array $entityIds): void
    {
        if (empty($entityIds)) {
            return;
        }

        $idList = implode(',', array_map('intval', $entityIds));

        foreach (['buildings', 'unique_objects'] as $satellite) {
            $this->conn->executeStatement("DELETE FROM {$satellite} WHERE player_id IN ({$idList})");
        }

        /* players_options n'était dans aucune des deux versions : 237 des 245
         * entités non joueuses de la base en portent une (les bâtiments en
         * ont toutes une, les PNJ leur incognitoMode). Sa clé étrangère
         * suffisait à faire échouer la suppression. */
        foreach (['players_bonus', 'players_effects', 'players_items', 'players_actions', 'players_options'] as $table) {
            $this->conn->executeStatement("DELETE FROM {$table} WHERE player_id IN ({$idList})");
        }

        // Les ennemis d'entraînement sont des PNJ posés sur l'instance.
        $this->conn->executeStatement("DELETE FROM tutorial_enemies WHERE enemy_player_id IN ({$idList})");

        // Ces trois-là référencent players des DEUX côtés : une entité qui a
        // combattu bloquait la suppression par sa colonne target_id.
        foreach (['players_logs', 'players_kills', 'players_assists'] as $table) {
            $this->conn->executeStatement(
                "DELETE FROM {$table} WHERE player_id IN ({$idList}) OR target_id IN ({$idList})"
            );
        }

        $this->conn->executeStatement("DELETE FROM players WHERE id IN ({$idList})");
    }

}
