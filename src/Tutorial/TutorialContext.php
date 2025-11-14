<?php

namespace App\Tutorial;

use Classes\Player;

/**
 * Isolated game context for tutorial (Phase 0 - Skeleton)
 *
 * Provides isolated state management for tutorial mode to:
 * - Track tutorial-specific state (unlimited resources, etc.)
 * - Manage XP/PI progression during tutorial
 * - Store step-specific data
 * - Keep tutorial isolated from main game state
 */
class TutorialContext
{
    private Player $player;
    private string $mode;
    private array $tutorialState = [];

    // Tutorial progression tracking
    private int $tutorialXP = 0;
    private int $tutorialLevel = 1;
    private int $tutorialPI = 0;

    public function __construct(Player $player, string $mode = 'first_time')
    {
        $this->player = $player;
        $this->mode = $mode;

        // Initialize default state
        $this->tutorialState = [
            'unlimited_mvt' => false,
            'unlimited_actions' => false,
            'invulnerable' => true,
            'tutorial_zone' => true,
            'pending_level_up' => false
        ];
    }

    /**
     * Get player
     */
    public function getPlayer(): Player
    {
        return $this->player;
    }

    /**
     * Set player (used when switching to tutorial player on resume)
     */
    public function setPlayer(Player $player): void
    {
        $this->player = $player;
    }

    /**
     * Get mode (first_time, replay, practice)
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Get complete tutorial state
     */
    public function getTutorialState(): array
    {
        return $this->tutorialState;
    }

    /**
     * Set tutorial state value
     */
    public function setState(string $key, $value): void
    {
        $this->tutorialState[$key] = $value;
    }

    /**
     * Get tutorial state value
     */
    public function getState(string $key, $default = null)
    {
        return $this->tutorialState[$key] ?? $default;
    }

    /**
     * Award XP for completing step
     */
    public function awardXP(int $amount): void
    {
        $this->tutorialXP += $amount;

        // Check for level up
        $xpNeeded = $this->getXPForLevel($this->tutorialLevel + 1);

        if ($this->tutorialXP >= $xpNeeded) {
            $this->levelUp();
        }

        // Update player XP display (temporary for tutorial)
        $this->player->data->xp = $this->tutorialXP;
    }

    /**
     * Level up and gain PI
     */
    private function levelUp(): void
    {
        $this->tutorialLevel++;
        $this->tutorialPI++;

        // Store level up flag for notification
        $this->tutorialState['pending_level_up'] = true;
        $this->tutorialState['new_level'] = $this->tutorialLevel;
    }

    /**
     * Invest PI in characteristic (for tutorial practice)
     *
     * @param string $characteristic (mvt, pv, cc, f, etc.)
     * @param int $amount Number of PI to invest
     * @return bool Success
     */
    public function investPI(string $characteristic, int $amount): bool
    {
        if ($this->tutorialPI < $amount) {
            return false;
        }

        // Deduct PI
        $this->tutorialPI -= $amount;

        // Apply investment temporarily (for tutorial demonstration)
        // In real game, this would be done via proper game mechanics
        switch ($characteristic) {
            case 'mvt':
                $this->player->data->mvt = ($this->player->data->mvt ?? 4) + $amount;
                $this->tutorialState['mvt_investment'] = $amount;
                break;

            case 'pv':
                $this->player->data->pv_max = ($this->player->data->pv_max ?? 20) + ($amount * 5);
                break;

            // Add other characteristics as needed
        }

        return true;
    }

    /**
     * Get XP needed for level (simple exponential curve)
     */
    private function getXPForLevel(int $level): int
    {
        // 100 XP for level 2, 250 for level 3, 450 for level 4, etc.
        return 100 * $level + 50 * ($level - 1) * ($level - 1);
    }

    /**
     * Get tutorial XP
     */
    public function getTutorialXP(): int
    {
        return $this->tutorialXP;
    }

    /**
     * Get tutorial level
     */
    public function getTutorialLevel(): int
    {
        return $this->tutorialLevel;
    }

    /**
     * Get tutorial PI
     */
    public function getTutorialPI(): int
    {
        return $this->tutorialPI;
    }

    /**
     * Check if level up is pending
     */
    public function hasPendingLevelUp(): bool
    {
        return $this->tutorialState['pending_level_up'] ?? false;
    }

    /**
     * Clear level up notification
     */
    public function clearLevelUpNotification(): void
    {
        $this->tutorialState['pending_level_up'] = false;
    }

    /**
     * Get public state for client (API response)
     */
    public function getPublicState(): array
    {
        return [
            'mode' => $this->mode,
            'tutorial_xp' => $this->tutorialXP,
            'tutorial_level' => $this->tutorialLevel,
            'tutorial_pi' => $this->tutorialPI,
            'unlimited_mvt' => $this->tutorialState['unlimited_mvt'] ?? false,
            'unlimited_actions' => $this->tutorialState['unlimited_actions'] ?? false,
            'invulnerable' => $this->tutorialState['invulnerable'] ?? true,
            'tutorial_zone' => true
        ];
    }

    /**
     * Serialize state for database storage
     */
    public function serializeState(): string
    {
        return json_encode([
            'tutorial_xp' => $this->tutorialXP,
            'tutorial_level' => $this->tutorialLevel,
            'tutorial_pi' => $this->tutorialPI,
            'state' => $this->tutorialState
        ]);
    }

    /**
     * Restore state from database
     */
    public function restoreState(string $serializedState): void
    {
        $data = json_decode($serializedState, true);

        if ($data) {
            $this->tutorialXP = $data['tutorial_xp'] ?? 0;
            $this->tutorialLevel = $data['tutorial_level'] ?? 1;
            $this->tutorialPI = $data['tutorial_pi'] ?? 0;
            $this->tutorialState = array_merge($this->tutorialState, $data['state'] ?? []);
        }
    }

    /**
     * Ensure step prerequisites are met
     *
     * This is called before rendering each step to guarantee resources are available
     *
     * @param array $prerequisites Step prerequisite configuration
     * @return bool True if prerequisites met/restored, false if failed
     */
    public function ensurePrerequisites(array $prerequisites): bool
    {
        if (empty($prerequisites)) {
            return true; // No prerequisites
        }

        $autoRestore = $prerequisites['auto_restore'] ?? false;

        // CRITICAL: Use TutorialHelper to get the correct player
        $playerId = \App\Tutorial\TutorialHelper::getActivePlayerId();
        $player = new \Classes\Player($playerId);
        $player->get_data();

        // Check and restore movement points
        if (isset($prerequisites['mvt'])) {
            $required = (int) $prerequisites['mvt'];
            // Get actual movement from turn system
            $player->get_caracs();
            $current = $player->getRemaining('mvt');

            // Get caller context for debugging
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $caller = isset($backtrace[1]) ? $backtrace[1]['function'] : 'unknown';

            error_log("[TutorialContext] Prerequisites mvt check: required={$required}, current={$current}, auto_restore={$autoRestore}, caller={$caller}");

            if ($current < $required) {
                if ($autoRestore) {
                    // SET movement to exact amount (not add)
                    $this->setPlayerMovements($required);
                    error_log("[TutorialContext] Restored movement: {$current} → {$required}");
                } else {
                    error_log("[TutorialContext] ERROR: Insufficient movement (need {$required}, have {$current})");
                    return false;
                }
            } else {
                error_log("[TutorialContext] Movement OK: have {$current}, need {$required} - no restoration needed");
            }
        }

        // Check and restore action points
        if (isset($prerequisites['actions'])) {
            $required = (int) $prerequisites['actions'];
            $current = $player->data->a ?? 0;

            if ($current < $required) {
                if ($autoRestore) {
                    $player->data->a = $required;
                    error_log("[TutorialContext] Restored actions: {$current} → {$required}");
                } else {
                    error_log("[TutorialContext] ERROR: Insufficient actions (need {$required}, have {$current})");
                    return false;
                }
            }
        }

        // Mark entities to spawn (actual spawning handled by game logic)
        if (isset($prerequisites['ensure_enemy'])) {
            $this->setState('ensure_enemy', $prerequisites['ensure_enemy']);
            error_log("[TutorialContext] Marking enemy to spawn: {$prerequisites['ensure_enemy']}");
        }

        if (isset($prerequisites['ensure_item'])) {
            $this->setState('ensure_item', $prerequisites['ensure_item']);
            error_log("[TutorialContext] Marking item to spawn: {$prerequisites['ensure_item']}");
        }

        if (isset($prerequisites['ensure_npc'])) {
            $this->setState('ensure_npc', $prerequisites['ensure_npc']);
            error_log("[TutorialContext] Marking NPC to ensure: {$prerequisites['ensure_npc']}");
        }

        return true;
    }

    /**
     * Prepare resources for next step
     *
     * Called after current step completes to ensure next step has resources
     *
     * @param array $preparation Preparation configuration
     */
    public function prepareForNextStep(array $preparation): void
    {
        if (empty($preparation)) {
            return;
        }

        $player = $this->getPlayer();

        // Restore movement points
        if (isset($preparation['restore_mvt'])) {
            $amount = (int) $preparation['restore_mvt'];
            $this->setPlayerMovements($amount);
            error_log("[TutorialContext] Prepared movement for next step: {$amount}");
        }

        // Restore action points
        if (isset($preparation['restore_actions'])) {
            $amount = (int) $preparation['restore_actions'];
            $player->data->a = $amount;
            error_log("[TutorialContext] Prepared actions for next step: {$amount}");
        }

        // Mark entities to spawn for next step
        if (isset($preparation['spawn_enemy'])) {
            $this->setState('spawn_enemy_next', $preparation['spawn_enemy']);
            error_log("[TutorialContext] Will spawn enemy for next step: {$preparation['spawn_enemy']}");
        }

        if (isset($preparation['spawn_item'])) {
            $this->setState('spawn_item_next', $preparation['spawn_item']);
            error_log("[TutorialContext] Will spawn item for next step: {$preparation['spawn_item']}");
        }

        // Mark entities to remove
        if (isset($preparation['remove_enemy'])) {
            $this->setState('remove_enemy', $preparation['remove_enemy']);
            error_log("[TutorialContext] Will remove enemy: {$preparation['remove_enemy']}");
        }

        if (isset($preparation['remove_item'])) {
            $this->setState('remove_item', $preparation['remove_item']);
            error_log("[TutorialContext] Will remove item: {$preparation['remove_item']}");
        }
    }

    /**
     * Get current movement points
     */
    public function getCurrentMovement(): int
    {
        return $this->player->data->mvt ?? 0;
    }

    /**
     * Get current action points
     */
    public function getCurrentActions(): int
    {
        return $this->player->data->a ?? 0;
    }

    /**
     * Set movement points (for tutorial resource management)
     */
    public function setMovement(int $amount): void
    {
        $this->player->data->mvt = $amount;
    }

    /**
     * Set action points (for tutorial resource management)
     */
    public function setActions(int $amount): void
    {
        $this->player->data->a = $amount;
    }

    /**
     * Set player movements to exact amount (not add)
     *
     * This method properly SETS movements by clearing existing bonuses first,
     * then calculating and applying the exact bonus needed.
     *
     * @param int $targetAmount The exact number of movements to set
     */
    private function setPlayerMovements(int $targetAmount): void
    {
        // CRITICAL: Get the actual tutorial player instance (not context's cached player)
        $playerId = \App\Tutorial\TutorialHelper::getActivePlayerId();
        $player = new \Classes\Player($playerId);
        $player->get_data();

        // Log current state BEFORE clearing
        $db = new \Classes\Db();
        $checkSql = 'SELECT * FROM players_bonus WHERE player_id = ? AND name = "mvt"';
        $checkResult = $db->exe($checkSql, [$player->id]);
        $existingBonuses = [];
        while ($row = $checkResult->fetch_assoc()) {
            $existingBonuses[] = $row;
        }
        error_log("[TutorialContext] BEFORE clear - player_id={$player->id}, existing bonuses: " . json_encode($existingBonuses));

        // Clear existing movement bonuses from database
        $sql = 'DELETE FROM players_bonus WHERE player_id = ? AND name = "mvt"';
        $deleteResult = $db->exe($sql, [$player->id]);
        error_log("[TutorialContext] Deleted movement bonuses for player {$player->id}");

        // Get base movement from race/characteristics
        $player->get_caracs();
        $baseMovement = $player->caracs->mvt ?? 4;
        $currentRemaining = $player->getRemaining('mvt');

        // Calculate bonus needed to reach target
        $bonusNeeded = $targetAmount - $baseMovement;

        error_log("[TutorialContext] setPlayerMovements: player_id={$player->id}, target={$targetAmount}, base={$baseMovement}, currentRemaining={$currentRemaining}, bonusNeeded={$bonusNeeded}");

        // Apply bonus if needed
        if ($bonusNeeded > 0) {
            $player->putBonus(array('mvt' => $bonusNeeded));
            error_log("[TutorialContext] Applied bonus: +{$bonusNeeded}");
        } elseif ($bonusNeeded < 0) {
            // Negative bonus (penalty)
            $player->putBonus(array('mvt' => $bonusNeeded));
            error_log("[TutorialContext] Applied penalty: {$bonusNeeded}");
        } else {
            error_log("[TutorialContext] No bonus needed (at base movement)");
        }
        // If bonusNeeded == 0, we're at base movement, no bonus needed

        // CRITICAL: Refresh caracs to regenerate JSON cache files
        // This writes to:
        // - datas/private/players/{id}.turn.json (with bonuses)
        // - datas/private/players/{id}.caracs.json (base stats)
        $player->get_caracs();
        $finalRemaining = $player->getRemaining('mvt');

        error_log("[TutorialContext] AFTER set - player_id={$player->id}, finalRemaining={$finalRemaining} (target was {$targetAmount})");
        error_log("[TutorialContext] JSON cache regenerated: {$player->id}.turn.json, {$player->id}.caracs.json");

        // Verify it matches
        if ($finalRemaining != $targetAmount) {
            error_log("[TutorialContext] ERROR: Movement mismatch! Expected {$targetAmount}, got {$finalRemaining}");
        }
    }
}
