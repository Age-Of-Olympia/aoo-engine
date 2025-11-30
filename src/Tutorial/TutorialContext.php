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
     *
     * Phase 4: Now accepts both string (JSON) and array formats for compatibility
     *
     * @param string|array $serializedState State data (JSON string or already-decoded array)
     */
    public function restoreState(string|array $serializedState): void
    {
        // Handle both JSON string and already-decoded array
        if (is_string($serializedState)) {
            $data = json_decode($serializedState, true);
        } else {
            $data = $serializedState;
        }

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

        // CRITICAL: Use TutorialHelper to load the correct player with validation
        $player = \App\Tutorial\TutorialHelper::loadActivePlayer(loadCaracs: true, throwOnFailure: false);

        // Check and restore movement points
        if (isset($prerequisites['mvt'])) {
            $required = (int) $prerequisites['mvt'];

            // Special value -1 means "use race's max movement"
            if ($required === -1) {
                $required = $this->getPlayerMaxMovement($player);
                error_log("[TutorialContext] Using race-specific max movement: {$required}");
            }

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

            // Ensure player data is loaded
            if (!$player->data || $player->data === false) {
                error_log("[TutorialContext] ERROR: Player data not loaded for player {$player->id}, attempting to load...");
                $player->get_data();

                // Verify data was loaded successfully
                if (!$player->data || $player->data === false) {
                    error_log("[TutorialContext] CRITICAL: Failed to load player data for player {$player->id}");
                    return false;
                }
            }

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

            // Ensure player data is loaded
            if (!$player->data || $player->data === false) {
                error_log("[TutorialContext] ERROR: Player data not loaded for player {$player->id}");
                $player->get_data();
            }

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
        // Ensure player data is loaded
        if (!$this->player->data || $this->player->data === false) {
            $this->player->get_data();

            // Return 0 if data still not loaded
            if (!$this->player->data || $this->player->data === false) {
                error_log("[TutorialContext] CRITICAL: Failed to load player data in getCurrentMovement()");
                return 0;
            }
        }

        return $this->player->data->mvt ?? 0;
    }

    /**
     * Get current action points
     */
    public function getCurrentActions(): int
    {
        // Ensure player data is loaded
        if (!$this->player->data || $this->player->data === false) {
            $this->player->get_data();

            // Return 0 if data still not loaded
            if (!$this->player->data || $this->player->data === false) {
                error_log("[TutorialContext] CRITICAL: Failed to load player data in getCurrentActions()");
                return 0;
            }
        }

        return $this->player->data->a ?? 0;
    }

    /**
     * Set movement points (for tutorial resource management)
     */
    public function setMovement(int $amount): void
    {
        // Ensure player data is loaded
        if (!$this->player->data || $this->player->data === false) {
            $this->player->get_data();

            // Throw exception if data still not loaded
            if (!$this->player->data || $this->player->data === false) {
                throw new \RuntimeException("Failed to load player data in setMovement()");
            }
        }

        $this->player->data->mvt = $amount;
    }

    /**
     * Set action points (for tutorial resource management)
     */
    public function setActions(int $amount): void
    {
        // Ensure player data is loaded
        if (!$this->player->data || $this->player->data === false) {
            $this->player->get_data();

            // Throw exception if data still not loaded
            if (!$this->player->data || $this->player->data === false) {
                throw new \RuntimeException("Failed to load player data in setActions()");
            }
        }

        $this->player->data->a = $amount;
    }

    /**
     * Set player movements to exact amount (not add)
     *
     * This method properly SETS movements by clearing existing bonuses first,
     * then calculating and applying the exact bonus needed.
     *
     * @param int $targetAmount The exact number of movements to set
     * @throws \RuntimeException If final movement doesn't match target
     */
    private function setPlayerMovements(int $targetAmount): void
    {
        // Get the actual tutorial player instance (not context's cached player)
        $player = \App\Tutorial\TutorialHelper::loadActivePlayer(loadCaracs: true, throwOnFailure: true);

        // Clear existing movement bonuses
        $this->clearMovementBonuses($player->id);

        // Calculate bonus needed to reach target
        $player->get_caracs();
        $baseMovement = $player->caracs->mvt ?? 4;
        $bonusNeeded = $targetAmount - $baseMovement;

        // Apply bonus if needed
        if ($bonusNeeded !== 0) {
            $player->putBonus(['mvt' => $bonusNeeded]);
        }

        // Refresh caracs to regenerate JSON cache files
        $player->get_caracs();
        $finalRemaining = $player->getRemaining('mvt');

        // Verify result matches target
        if ($finalRemaining !== $targetAmount) {
            $errorMsg = "Movement mismatch! Expected {$targetAmount}, got {$finalRemaining} (player {$player->id})";
            error_log("[TutorialContext] ERROR: {$errorMsg}");
            throw new \RuntimeException($errorMsg);
        }
    }

    /**
     * Clear all movement bonuses for a player
     *
     * @param int $playerId Player ID
     */
    private function clearMovementBonuses(int $playerId): void
    {
        $db = new \Classes\Db();
        $db->exe('DELETE FROM players_bonus WHERE player_id = ? AND name = "mvt"', [$playerId]);
    }

    /**
     * Get player's maximum movement points from race base stats
     *
     * This allows tutorial steps to dynamically adapt to race-specific movement values.
     * Different races have different base movements:
     * - Nain: 4, Elfe: 5, Olympien: 5, Géant: 5, HS: 6
     *
     * @param \Classes\Player $player Player instance
     * @return int Maximum movement points for this player's race
     */
    private function getPlayerMaxMovement(\Classes\Player $player): int
    {
        // Ensure player data is loaded
        if (!isset($player->data)) {
            $player->get_data();
        }

        // Get race data from JSON
        $race = $player->data->race ?? 'nain';
        $raceJson = (new \Classes\Json())->decode('races', $race);

        if ($raceJson && isset($raceJson->mvt)) {
            return (int) $raceJson->mvt;
        }

        // Fallback to default (Nain base movement)
        error_log("[TutorialContext] WARNING: Could not find mvt for race '{$race}', using default 4");
        return 4;
    }
}
