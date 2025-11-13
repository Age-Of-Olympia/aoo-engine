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
}
