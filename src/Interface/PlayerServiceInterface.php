<?php

namespace App\Interface;

use Classes\Player;

interface PlayerServiceInterface
{
    /**
     * Get plain email for a player
     * @param int $playerId The player ID
     * @return string|null The plain email or null if not found
     */
    public function getPlainEmail(int $playerId): ?string;

    /**
     * Check if player has email bonus
     * @param int $playerId The player ID
     * @return bool True if player has email bonus, false otherwise
     */
    public function getEmailBonus(int $playerId): bool;

    /**
     * Get specific fields for the player
     * @param array $fields Array of field names to retrieve
     * @return array Associative array of field names and their values
     */
    public function getPlayerFields(array $fields): array;

    /**
     * Check if a player is inactive based on last login time
     * @param int $lastLoginTime The last login timestamp
     * @return bool True if inactive, false otherwise
     */
    public function isInactive(int $lastLoginTime): bool;

    /**
     * Search for non-anonymous players by name
     * @param string $searchKey The search term
     * @return array Array of player names matching the search
     */
    public function searchNonAnonymePlayer(string $searchKey): array;

    /**
     * Get a Player object by ID with caching options
     * @param mixed $id The player ID
     * @param bool $readCache Whether to read from cache
     * @param bool $writeCache Whether to write to cache
     * @return Player The player object
     */
    public function GetPlayer($id, bool $readCache = true, bool $writeCache = true): Player;

    /**
     * Get all players ordered by name
     * @return array Array of all players data
     */
    public function getAllPlayers(): array;

    /**
     * Update the last action time for the current player
     * @return void
     */
    public function updateLastActionTime(): void;

    /**
     * Get the number of spells available for the current player
     * @return int Number of available spells
     */
    public function getNumberOfSpellAvailable(): int;
}