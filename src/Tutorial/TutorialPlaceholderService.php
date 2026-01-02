<?php

namespace App\Tutorial;

use Classes\Player;
use Classes\Json;

/**
 * TutorialPlaceholderService - Replaces dynamic placeholders in tutorial text
 *
 * Provides a flexible system for inserting dynamic values into tutorial step text.
 * This allows tutorial content to adapt to different player races, stats, and context.
 *
 * Available Placeholders:
 * - {max_mvt} - Player's maximum movement points (race-dependent)
 * - {max_pa} - Player's maximum action points (race-dependent)
 * - {player_name} - Tutorial player's name
 * - {race} - Player's race name
 * - {race_lower} - Player's race name (lowercase)
 *
 * Usage:
 * ```php
 * $service = new TutorialPlaceholderService($player);
 * $processedText = $service->replacePlaceholders("Vous avez {max_mvt} mouvements par tour.");
 * // Result: "Vous avez 4 mouvements par tour." (for Dwarf)
 * ```
 *
 * Adding New Placeholders:
 * To add a new placeholder, add a new case in the getPlaceholderValue() method.
 * Document it in the class docblock above and in the admin panel documentation.
 */
class TutorialPlaceholderService
{
    private Player $player;
    private array $cachedValues = [];

    /**
     * @param Player $player Tutorial player instance
     */
    public function __construct(Player $player)
    {
        $this->player = $player;
    }

    /**
     * Replace all placeholders in text with dynamic values
     *
     * @param string $text Text containing placeholders like {max_mvt}
     * @return string Processed text with placeholders replaced
     */
    public function replacePlaceholders(string $text): string
    {
        // Find all placeholders in format {placeholder_name}
        return preg_replace_callback(
            '/{([a-z_]+)}/',
            function ($matches) {
                $placeholder = $matches[1];
                return $this->getPlaceholderValue($placeholder);
            },
            $text
        );
    }

    /**
     * Get the value for a specific placeholder
     *
     * @param string $placeholder Placeholder name (without braces)
     * @return string Replacement value
     */
    private function getPlaceholderValue(string $placeholder): string
    {
        // Use cached value if available
        if (isset($this->cachedValues[$placeholder])) {
            return $this->cachedValues[$placeholder];
        }

        $value = match ($placeholder) {
            'max_mvt' => $this->getMaxMovement(),
            'max_pa' => $this->getMaxActionPoints(),
            'player_name' => $this->getPlayerName(),
            'race' => $this->getRaceName(),
            'race_lower' => strtolower($this->getRaceName()),
            default => '{' . $placeholder . '}' // Keep unknown placeholders unchanged
        };

        // Cache the value
        $this->cachedValues[$placeholder] = $value;

        return $value;
    }

    /**
     * Get player's maximum movement points from race base stats
     *
     * @return string Maximum movement value as string
     */
    private function getMaxMovement(): string
    {
        // Ensure player data is loaded
        if (!isset($this->player->data)) {
            $this->player->get_data();
        }

        // Get race data
        $race = $this->player->data->race;
        $raceJson = (new Json())->decode('races', $race);

        if ($raceJson && isset($raceJson->mvt)) {
            return (string) $raceJson->mvt;
        }

        // Fallback to default (should not happen with valid race)
        error_log("[TutorialPlaceholderService] Could not find mvt for race '{$race}', using default 4");
        return '4';
    }

    /**
     * Get player's maximum action points from race base stats
     *
     * @return string Maximum action points value as string
     */
    private function getMaxActionPoints(): string
    {
        // Ensure player data is loaded
        if (!isset($this->player->data)) {
            $this->player->get_data();
        }

        // Get race data
        $race = $this->player->data->race;
        $raceJson = (new Json())->decode('races', $race);

        if ($raceJson && isset($raceJson->a)) {
            return (string) $raceJson->a;
        }

        // Fallback to default
        error_log("[TutorialPlaceholderService] Could not find 'a' for race '{$race}', using default 2");
        return '2';
    }

    /**
     * Get tutorial player's name
     *
     * @return string Player name
     */
    private function getPlayerName(): string
    {
        if (!isset($this->player->data)) {
            $this->player->get_data();
        }

        return $this->player->data->name ?? 'Héros';
    }

    /**
     * Get player's race name (capitalized)
     *
     * @return string Race name
     */
    private function getRaceName(): string
    {
        if (!isset($this->player->data)) {
            $this->player->get_data();
        }

        $race = $this->player->data->race ?? 'nain';

        // Get race display name from JSON
        $raceJson = (new Json())->decode('races', $race);
        if ($raceJson && isset($raceJson->name)) {
            return $raceJson->name;
        }

        // Fallback to capitalized race code
        return ucfirst($race);
    }
}
