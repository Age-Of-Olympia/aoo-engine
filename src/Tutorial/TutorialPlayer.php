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
    public int $coordsId;
    public string $race;
    public int $xp;
    public int $pi;
    public int $energie;
    public int $level;
    public bool $isActive;

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
        // They're distinguished by being tracked in the tutorial_players table

        // Get default avatar (map icon) and portrait (character image) for race
        $defaultAvatar = 'img/avatars/' . strtolower($race) . '/1.png';
        $defaultPortrait = 'img/portraits/' . strtolower($race) . '/1.jpeg';

        $conn->insert('players', [
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

        $actualPlayerId = (int) $conn->lastInsertId();

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
            'coords_id' => $startingCoordsId,
            'race' => $race,
            'xp' => 0,
            'pi' => 0,
            'energie' => 100,
            'level' => 1,
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
        $this->coordsId = (int) $data['coords_id'];
        $this->race = $data['race'];
        $this->xp = (int) $data['xp'];
        $this->pi = (int) $data['pi'];
        $this->energie = (int) $data['energie'];
        $this->level = (int) $data['level'];
        $this->isActive = (bool) $data['is_active'];
    }

    /**
     * Update tutorial character data
     */
    public function save(): void
    {
        $this->conn->update('tutorial_players', [
            'coords_id' => $this->coordsId,
            'xp' => $this->xp,
            'pi' => $this->pi,
            'energie' => $this->energie,
            'level' => $this->level,
            'is_active' => $this->isActive
        ], [
            'id' => $this->id
        ]);
    }

    /**
     * Move tutorial character to new position
     */
    public function moveTo(int $newCoordsId): void
    {
        $this->coordsId = $newCoordsId;
        $this->save();
    }

    /**
     * Award XP to tutorial character
     */
    public function awardXP(int $amount): void
    {
        $this->xp += $amount;

        // Level up logic (simple: every 100 XP = 1 level)
        $newLevel = floor($this->xp / 100) + 1;
        if ($newLevel > $this->level) {
            $this->level = $newLevel;
            $this->pi += 5; // Award 5 PI per level
        }

        $this->save();
    }

    /**
     * Get tutorial character position
     */
    public function getPosition(): array
    {
        $stmt = $this->conn->prepare('SELECT x, y, z, plan FROM coords WHERE id = ?');
        $stmt->bindValue(1, $this->coordsId);
        $result = $stmt->executeQuery();
        $coords = $result->fetchAssociative();

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
        // Soft delete in tutorial_players table
        $this->conn->update('tutorial_players', [
            'is_active' => false,
            'deleted_at' => date('Y-m-d H:i:s')
        ], [
            'id' => $this->id
        ]);

        // Hard delete from actual players table to clean up
        if ($this->playerId || $this->actualPlayerId) {
            $playerIdToDelete = $this->playerId ?? $this->actualPlayerId;

            // Delete foreign key references FIRST to avoid constraint errors
            // Delete logs where this player is the subject or target
            $this->conn->delete('players_logs', ['player_id' => $playerIdToDelete]);
            $this->conn->delete('players_logs', ['target_id' => $playerIdToDelete]);

            // Delete other related records
            $this->conn->delete('players_actions', ['player_id' => $playerIdToDelete]);
            $this->conn->delete('players_items', ['player_id' => $playerIdToDelete]);
            $this->conn->delete('players_effects', ['player_id' => $playerIdToDelete]);
            $this->conn->delete('players_options', ['player_id' => $playerIdToDelete]);
            $this->conn->delete('players_connections', ['player_id' => $playerIdToDelete]);
            $this->conn->delete('players_bonus', ['player_id' => $playerIdToDelete]);

            // Now safe to delete the player record
            $this->conn->delete('players', ['id' => $playerIdToDelete]);
        }
    }

    /**
     * Transfer tutorial rewards to real player
     *
     * This is called when tutorial completes successfully
     */
    public function transferRewardsToRealPlayer(): void
    {
        // Update real player's XP
        $this->conn->executeStatement('
            UPDATE players
            SET xp = xp + ?, pi = pi + ?
            WHERE id = ?
        ', [$this->xp, $this->pi, $this->realPlayerId]);

        // Log the transfer
        error_log(sprintf(
            "Tutorial rewards transferred: Player %d received %d XP and %d PI from tutorial",
            $this->realPlayerId,
            $this->xp,
            $this->pi
        ));
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'race' => $this->race,
            'xp' => $this->xp,
            'pi' => $this->pi,
            'level' => $this->level,
            'energie' => $this->energie,
            'position' => $this->getPosition()
        ];
    }
}
