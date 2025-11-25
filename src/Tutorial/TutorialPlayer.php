<?php

namespace App\Tutorial;

use Doctrine\DBAL\Connection;

/**
 * TutorialPlayer - Temporary character for tutorial sessions
 *
 * Each tutorial session creates a fresh temporary character that:
 * - Lives only for the tutorial duration
 * - Has its own position, XP, level (separate from real player)
 * - Gets deleted when tutorial completes
 * - Allows safe, isolated tutorial experience
 */
class TutorialPlayer
{
    private Connection $conn;

    public int $id;
    public int $realPlayerId;
    public string $tutorialSessionId;
    public ?int $playerId = null; // ID in the actual players table
    public int $actualPlayerId; // Alias for playerId (for backwards compatibility)
    public string $name;
    public bool $isActive;

    // NOTE: coords_id, race, energie, level, xp, pi removed from tutorial_players table
    // These are now tracked in TutorialContext or players table only

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create a new tutorial character for a tutorial session
     *
     * Creates an isolated map instance for this tutorial session to prevent
     * resource/NPC conflicts with other concurrent tutorial players.
     *
     * @param Connection $conn
     * @param int $realPlayerId Real player account ID
     * @param string $tutorialSessionId Tutorial session UUID
     * @param int|null $startingCoordsId Starting position (deprecated - will be auto-generated from instance)
     * @param string|null $race Character race (defaults to real player's race)
     * @return self
     */
    public static function create(
        Connection $conn,
        int $realPlayerId,
        string $tutorialSessionId,
        ?int $startingCoordsId = null,
        ?string $race = null
    ): self {
        // Step 1: Create isolated map instance for this tutorial session
        error_log("[TutorialPlayer] Creating map instance for session {$tutorialSessionId}");
        $mapInstance = new TutorialMapInstance($conn);
        $instanceData = $mapInstance->createInstance($tutorialSessionId);

        $instancePlanName = $instanceData['plan_name'];
        $startingCoordsId = $instanceData['starting_coords_id'];

        error_log("[TutorialPlayer] Map instance created: {$instancePlanName}, starting at coords_id {$startingCoordsId}");

        // Step 2: Get real player's race if not specified
        if ($race === null) {
            $stmt = $conn->prepare('SELECT race, name FROM players WHERE id = ?');
            $stmt->bindValue(1, $realPlayerId);
            $result = $stmt->executeQuery();
            $playerData = $result->fetchAssociative();
            $race = $playerData['race'] ?? 'Humain';
            $realName = $playerData['name'] ?? 'Héros';
        }

        // Create tutorial character name
        $name = "Apprenti_" . substr($tutorialSessionId, 0, 8); // Unique name for tutorial character

        // Step 3: Create actual player entry in the players table
        // Tutorial characters use regular positive IDs (negative IDs are for NPCs)
        // They're distinguished by player_type discriminator column

        // Validate race
        $validRaces = RACES_EXT; // Use extended races list (includes all player races)
        if (!in_array(strtolower($race), $validRaces, true)) {
            throw new \InvalidArgumentException(
                "Invalid race '{$race}'. Valid races: " . implode(', ', $validRaces)
            );
        }

        // Get default avatar (map icon) and portrait (character image) for race
        $raceLower = strtolower($race);
        $defaultAvatar = "img/avatars/{$raceLower}/1.png";
        $defaultPortrait = "img/portraits/{$raceLower}/1.jpeg";

        // Generate IDs using the new range-based system
        $actualPlayerId = getNextEntityId('tutorial');  // Get ID in tutorial range (10000000+)
        $displayId = getNextDisplayId('tutorial');      // Get sequential display ID (1, 2, 3...)

        $conn->insert('players', [
            'id' => $actualPlayerId,
            'player_type' => 'tutorial',  // ← DISCRIMINATOR: marks this as tutorial player
            'display_id' => $displayId,   // ← Sequential ID for display (Tutorial Player #1, #2, etc.)
            'tutorial_session_id' => $tutorialSessionId,  // ← Link to tutorial session
            'real_player_id_ref' => $realPlayerId,  // ← Link to real player account
            'name' => $name,
            'psw' => '', // No password for tutorial characters
            'mail' => '',
            'plain_mail' => '',
            'coords_id' => $startingCoordsId,
            'race' => $race,
            'xp' => 0,
            'pi' => 0,
            'energie' => 100,
            'avatar' => $defaultAvatar,
            'portrait' => $defaultPortrait,
            'text' => 'Personnage de tutoriel'
        ]);

        // IMPORTANT: Delete any stale JSON cache for this player ID
        // This can happen if the player ID was previously used and has a cached file
        $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/datas/private/players/' . $actualPlayerId . '.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            error_log("[TutorialPlayer] Deleted stale cache file for player {$actualPlayerId}");
        }

        // Give the tutorial character basic actions
        $basicActions = ['fouiller', 'repos', 'attaquer', 'courir', 'prier', 'entrainement'];
        foreach ($basicActions as $actionName) {
            $conn->insert('players_actions', [
                'player_id' => $actualPlayerId,
                'name' => $actionName
            ]);
        }

        // Enable action details by default for tutorial players
        $conn->insert('players_options', [
            'player_id' => $actualPlayerId,
            'name' => 'showActionDetails'
        ]);

        // Then create the tutorial_players tracking entry
        $conn->insert('tutorial_players', [
            'real_player_id' => $realPlayerId,
            'tutorial_session_id' => $tutorialSessionId,
            'name' => $name,
            'is_active' => true
        ]);

        $tutorialPlayerId = (int) $conn->lastInsertId();

        // Update tutorial_players to store the actual player ID
        $conn->update('tutorial_players', [
            'player_id' => $actualPlayerId
        ], [
            'id' => $tutorialPlayerId
        ]);

        // Load and return
        $player = self::load($conn, $tutorialPlayerId);

        // Ensure actualPlayerId is set (should be loaded by hydrate, but set explicitly for safety)
        if (!$player->actualPlayerId) {
            $player->playerId = $actualPlayerId;
            $player->actualPlayerId = $actualPlayerId;
        }

        return $player;
    }

    /**
     * Load existing tutorial character by ID
     */
    public static function load(Connection $conn, int $id): self
    {
        $stmt = $conn->prepare('
            SELECT * FROM tutorial_players
            WHERE id = ? AND is_active = 1 AND deleted_at IS NULL
        ');
        $stmt->bindValue(1, $id);
        $result = $stmt->executeQuery();
        $data = $result->fetchAssociative();

        if (!$data) {
            throw new \RuntimeException("Tutorial character $id not found or inactive");
        }

        $player = new self($conn);
        $player->hydrate($data);

        return $player;
    }

    /**
     * Load tutorial character by session ID
     */
    public static function loadBySession(Connection $conn, string $sessionId): ?self
    {
        $stmt = $conn->prepare('
            SELECT * FROM tutorial_players
            WHERE tutorial_session_id = ? AND is_active = 1 AND deleted_at IS NULL
        ');
        $stmt->bindValue(1, $sessionId);
        $result = $stmt->executeQuery();
        $data = $result->fetchAssociative();

        if (!$data) {
            return null;
        }

        $player = new self($conn);
        $player->hydrate($data);

        return $player;
    }

    /**
     * Hydrate object from database row
     */
    private function hydrate(array $data): void
    {
        $this->id = (int) $data['id'];
        $this->realPlayerId = (int) $data['real_player_id'];
        $this->tutorialSessionId = $data['tutorial_session_id'];
        $this->playerId = isset($data['player_id']) ? (int) $data['player_id'] : null;
        $this->actualPlayerId = $this->playerId ?? 0; // Set actualPlayerId for backwards compatibility
        $this->name = $data['name'];
        $this->isActive = (bool) $data['is_active'];
    }

    /**
     * NOTE: save(), moveTo(), and awardXP() methods removed.
     *
     * Progression tracking (XP, PI, level) is now handled by TutorialContext.
     * Position is tracked in players.coords_id (not tutorial_players).
     * The tutorial_players table is now a lightweight reference table only.
     */

    /**
     * Get tutorial character position (from players table, not tutorial_players)
     */
    public function getPosition(): array
    {
        if (!$this->playerId) {
            throw new \RuntimeException("Cannot get position: tutorial player not linked to players table");
        }

        $stmt = $this->conn->prepare('SELECT c.x, c.y, c.z, c.plan
                                       FROM players p
                                       JOIN coords c ON p.coords_id = c.id
                                       WHERE p.id = ?');
        $stmt->bindValue(1, $this->playerId);
        $result = $stmt->executeQuery();
        $coords = $result->fetchAssociative();

        if (!$coords) {
            throw new \RuntimeException("Position not found for tutorial player {$this->playerId}");
        }

        return [
            'x' => (int) $coords['x'],
            'y' => (int) $coords['y'],
            'z' => (int) $coords['z'],
            'plan' => $coords['plan']
        ];
    }

    /**
     * Delete tutorial character (soft delete)
     *
     * Called when tutorial completes. XP/rewards should be transferred
     * to real player before calling this.
     */
    public function delete(): void
    {
        $playerIdToDelete = $this->playerId ?? $this->actualPlayerId;

        if (!$playerIdToDelete) {
            error_log("[TutorialPlayer] No player ID to delete for tutorial_players ID {$this->id}");
            return;
        }

        error_log("[TutorialPlayer] Deleting tutorial player {$playerIdToDelete} and related records...");

        // Use centralized cleanup service to avoid code duplication
        $cleanup = new TutorialPlayerCleanup($this->conn, new \Psr\Log\NullLogger());

        try {
            $cleanup->deleteTutorialPlayer($this->id, $playerIdToDelete);
            error_log("[TutorialPlayer] Tutorial player {$playerIdToDelete} deleted successfully");
        } catch (TutorialPlayerCleanupException $e) {
            error_log("[TutorialPlayer] Error during deletion: " . $e->getMessage());
            throw $e; // Re-throw to be caught by TutorialManager
        }
    }

    /**
     * Transfer tutorial rewards to real player
     *
     * This is called when tutorial completes successfully.
     * XP/PI amounts come from TutorialContext (not tutorial_players table).
     *
     * @param int $xpEarned XP earned during tutorial
     * @param int $piEarned PI earned during tutorial
     */
    public function transferRewardsToRealPlayer(int $xpEarned, int $piEarned): void
    {
        // Update real player's XP
        $this->conn->executeStatement('
            UPDATE players
            SET xp = xp + ?, pi = pi + ?
            WHERE id = ?
        ', [$xpEarned, $piEarned, $this->realPlayerId]);

        // Log the transfer
        error_log(sprintf(
            "Tutorial rewards transferred: Player %d received %d XP and %d PI from tutorial",
            $this->realPlayerId,
            $xpEarned,
            $piEarned
        ));
    }

    /**
     * Convert to array for API responses
     *
     * NOTE: race, xp, pi, level, energie are no longer stored in tutorial_players table.
     * These come from players table or TutorialContext. This method now returns minimal data.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tutorial_players_id' => $this->id,
            'player_id' => $this->playerId,
            'real_player_id' => $this->realPlayerId,
            'tutorial_session_id' => $this->tutorialSessionId,
            'name' => $this->name,
            'is_active' => $this->isActive
        ];
    }
}
