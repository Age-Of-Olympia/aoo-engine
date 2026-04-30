<?php

namespace App\Tutorial;

use Doctrine\DBAL\Connection;

/**
 * Read-only repository over `tutorial_npcs`. Used by:
 *   - TutorialMapInstance: pulls template-mode rows to spawn NPCs on
 *     the per-session map at fixed (x,y).
 *   - TutorialResourceManager: pulls dynamic-mode rows to spawn NPCs
 *     per session relative to the player tile.
 *
 * Returns plain associative arrays — keeps the surface tiny and
 * mirrors how the rest of TutorialStepRepository* hands rows back.
 */
class TutorialNpcRepository
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /**
     * @return list<array{id:int,role:string,spawn_mode:string,x:int,y:int,
     *                    name:string,race:string,avatar:string,portrait:string,
     *                    faction:string,text:string|null,energie:int,
     *                    spawn_at_step_id:int|null}>
     */
    public function listActive(string $version, string $spawnMode, ?int $stepId = null): array
    {
        $sql = "
            SELECT id, role, spawn_mode, x, y, name, race, avatar, portrait,
                   faction, text, energie, spawn_at_step_id
            FROM tutorial_npcs
            WHERE version = ?
              AND spawn_mode = ?
              AND is_active = 1
        ";
        $params = [$version, $spawnMode];

        if ($stepId === null) {
            $sql .= " AND spawn_at_step_id IS NULL";
        } else {
            $sql .= " AND spawn_at_step_id = ?";
            $params[] = $stepId;
        }

        $sql .= " ORDER BY id ASC";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }
        $rows = $stmt->executeQuery()->fetchAllAssociative();

        return array_map([$this, 'normalize'], $rows);
    }

    /**
     * Variant of listActive that resolves spawn_at_step_id by the step's
     * NAME (`tutorial_steps.step_id`). Used by TutorialProgressManager
     * when transitioning to a step — it knows the step's name string,
     * not its DB id.
     *
     * @return list<array> same shape as listActive()
     */
    public function listForStepName(string $version, string $spawnMode, string $stepName): array
    {
        $sql = "
            SELECT npc.id, npc.role, npc.spawn_mode, npc.x, npc.y, npc.name, npc.race,
                   npc.avatar, npc.portrait, npc.faction, npc.text, npc.energie,
                   npc.spawn_at_step_id
            FROM tutorial_npcs npc
            JOIN tutorial_steps ts ON npc.spawn_at_step_id = ts.id
            WHERE npc.version = ?
              AND npc.spawn_mode = ?
              AND npc.is_active = 1
              AND ts.step_id = ?
              AND ts.version = ?
            ORDER BY npc.id ASC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, $version);
        $stmt->bindValue(2, $spawnMode);
        $stmt->bindValue(3, $stepName);
        $stmt->bindValue(4, $version);
        $rows = $stmt->executeQuery()->fetchAllAssociative();

        return array_map([$this, 'normalize'], $rows);
    }

    private function normalize(array $r): array
    {
        return [
            'id'               => (int) $r['id'],
            'role'             => $r['role'],
            'spawn_mode'       => $r['spawn_mode'],
            'x'                => (int) $r['x'],
            'y'                => (int) $r['y'],
            'name'             => $r['name'],
            'race'             => $r['race'],
            'avatar'           => $r['avatar'],
            'portrait'         => $r['portrait'],
            'faction'          => $r['faction'] ?? '',
            'text'             => $r['text'],
            'energie'          => (int) $r['energie'],
            'spawn_at_step_id' => isset($r['spawn_at_step_id']) ? (int) $r['spawn_at_step_id'] : null,
        ];
    }
}
