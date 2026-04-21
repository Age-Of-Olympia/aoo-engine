<?php

namespace App\Tutorial;

use Classes\Player;

/**
 * Isolated state container for a tutorial run: tutorial-only XP/PI progression,
 * step-local flags, and resource prerequisites — kept off the real player.
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
     * @param string|array $serializedState JSON string or already-decoded array.
     */
    public function restoreState(string|array $serializedState): void
    {
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

        $player = \App\Tutorial\TutorialHelper::loadActivePlayer(loadCaracs: true, throwOnFailure: false);

        if (isset($prerequisites['mvt'])) {
            $required = (int) $prerequisites['mvt'];

            // Sentinel: -1 means "use this race's max movement".
            if ($required === -1) {
                $required = $this->getPlayerMaxMovement($player);
            }

            $player->get_caracs();
            $current = $player->getRemaining('mvt');

            if ($current < $required) {
                if ($autoRestore) {
                    $this->setPlayerMovements($required);
                } else {
                    return false;
                }
            }
        }

        if (isset($prerequisites['actions'])) {
            $required = (int) $prerequisites['actions'];

            // Read PA from the turn system — $player->data->a is not a column on
            // players and resolves to 0, which silently inflates every subsequent
            // +pa_required bonus.
            $player->get_caracs();
            $current = $player->getRemaining('a');

            if ($current < $required) {
                if ($autoRestore) {
                    $player->putBonus(['a' => $required - $current]);
                } else {
                    return false;
                }
            }
        }

        if (isset($prerequisites['ensure_enemy'])) {
            $this->setState('ensure_enemy', $prerequisites['ensure_enemy']);
        }

        if (isset($prerequisites['ensure_item'])) {
            $this->setState('ensure_item', $prerequisites['ensure_item']);
        }

        if (isset($prerequisites['ensure_npc'])) {
            $this->setState('ensure_npc', $prerequisites['ensure_npc']);
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

        if (isset($preparation['restore_mvt'])) {
            $this->setPlayerMovements((int) $preparation['restore_mvt']);
        }

        if (isset($preparation['restore_actions'])) {
            if (!$player->data || $player->data === false) {
                $player->get_data();
            }

            $player->data->a = (int) $preparation['restore_actions'];
        }

        if (isset($preparation['spawn_enemy'])) {
            $this->setState('spawn_enemy_next', $preparation['spawn_enemy']);
        }

        if (isset($preparation['spawn_item'])) {
            $this->setState('spawn_item_next', $preparation['spawn_item']);
        }

        if (isset($preparation['remove_enemy'])) {
            $this->setState('remove_enemy', $preparation['remove_enemy']);
        }

        if (isset($preparation['remove_item'])) {
            $this->setState('remove_item', $preparation['remove_item']);
        }
    }

    /**
     * Get current movement points
     */
    public function getCurrentMovement(): int
    {
        if (!$this->player->data || $this->player->data === false) {
            $this->player->get_data();
            if (!$this->player->data || $this->player->data === false) {
                return 0;
            }
        }

        return $this->player->data->mvt ?? 0;
    }

    public function getCurrentActions(): int
    {
        if (!$this->player->data || $this->player->data === false) {
            $this->player->get_data();
            if (!$this->player->data || $this->player->data === false) {
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
        $player = \App\Tutorial\TutorialHelper::loadActivePlayer(loadCaracs: true, throwOnFailure: true);

        $this->clearMovementBonuses($player->id);

        $player->get_caracs();
        $baseMovement = $player->caracs->mvt ?? 4;
        $bonusNeeded = $targetAmount - $baseMovement;

        if ($bonusNeeded !== 0) {
            $player->putBonus(['mvt' => $bonusNeeded]);
        }

        // Refresh caracs to regenerate JSON cache files.
        $player->get_caracs();
        $finalRemaining = $player->getRemaining('mvt');

        if ($finalRemaining !== $targetAmount) {
            throw new \RuntimeException(
                "Movement mismatch! Expected {$targetAmount}, got {$finalRemaining} (player {$player->id})"
            );
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
     * Max movement points from the player's race JSON (Nain 4, Elfe/Olympien/Géant 5, HS 6).
     * Falls back to Nain base if the race file can't be read.
     */
    private function getPlayerMaxMovement(\Classes\Player $player): int
    {
        if (!isset($player->data)) {
            $player->get_data();
        }

        $race = $player->data->race ?? 'nain';
        $raceJson = (new \Classes\Json())->decode('races', $race);

        if ($raceJson && isset($raceJson->mvt)) {
            return (int) $raceJson->mvt;
        }

        return 4;
    }
}
