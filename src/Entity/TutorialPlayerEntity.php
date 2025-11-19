<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TutorialPlayerEntity - Temporary character for tutorial sessions
 *
 * These are temporary characters that:
 * - Exist only during tutorial sessions
 * - Are isolated from real players (don't appear in lists)
 * - Have their own isolated map instance
 * - Get deleted when tutorial completes
 * - Transfer rewards (XP, PI) to real player on completion
 *
 * Discriminator: player_type = 'tutorial'
 */
#[ORM\Entity]
class TutorialPlayerEntity extends PlayerEntity
{
    #[ORM\Column(type: "string", length: 36, nullable: true)]
    protected ?string $tutorialSessionId = null;

    #[ORM\Column(type: "integer", nullable: true)]
    protected ?int $realPlayerIdRef = null;

    /**
     * Tutorial players are temporary characters
     */
    public function isRealPlayer(): bool
    {
        return false;
    }

    public function isTutorialPlayer(): bool
    {
        return true;
    }

    public function isNPC(): bool
    {
        return false;
    }

    /**
     * Tutorial players never appear in public lists
     */
    public function isPubliclyVisible(): bool
    {
        return false;
    }

    /**
     * Get the tutorial session ID for this character
     */
    public function getTutorialSessionId(): ?string
    {
        return $this->tutorialSessionId;
    }

    public function setTutorialSessionId(?string $tutorialSessionId): self
    {
        $this->tutorialSessionId = $tutorialSessionId;
        return $this;
    }

    /**
     * Get the real player account this tutorial character belongs to
     */
    public function getRealPlayerIdRef(): ?int
    {
        return $this->realPlayerIdRef;
    }

    public function setRealPlayerIdRef(?int $realPlayerIdRef): self
    {
        $this->realPlayerIdRef = $realPlayerIdRef;
        return $this;
    }

    /**
     * Check if this tutorial character is temporary (always true)
     */
    public function isTemporary(): bool
    {
        return true;
    }

    /**
     * Transfer rewards (XP, PI) to the real player account
     *
     * @param \Doctrine\DBAL\Connection $conn
     */
    public function transferRewardsToRealPlayer(\Doctrine\DBAL\Connection $conn): void
    {
        if (!$this->realPlayerIdRef) {
            throw new \RuntimeException("Cannot transfer rewards: real_player_id_ref is null");
        }

        $conn->executeStatement('
            UPDATE players
            SET xp = xp + ?, pi = pi + ?
            WHERE id = ? AND player_type = "real"
        ', [$this->xp, $this->pi, $this->realPlayerIdRef]);

        error_log(sprintf(
            "Tutorial rewards transferred: Player %d received %d XP and %d PI from tutorial player %d",
            $this->realPlayerIdRef,
            $this->xp,
            $this->pi,
            $this->id
        ));
    }

    /**
     * Delete this tutorial character and all related data
     *
     * @param \Doctrine\DBAL\Connection $conn
     */
    public function deleteWithRelatedData(\Doctrine\DBAL\Connection $conn): void
    {
        if (!$this->id) {
            return;
        }

        // Delete all foreign key references
        $tables = [
            'players_logs' => ['player_id', 'target_id'],
            'players_actions' => ['player_id'],
            'players_items' => ['player_id'],
            'players_effects' => ['player_id'],
            'players_options' => ['player_id'],
            'players_connections' => ['player_id'],
            'players_bonus' => ['player_id'],
            'players_assists' => ['player_id', 'target_id'],
            'players_kills' => ['player_id', 'target_id'],
        ];

        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
                $conn->executeStatement(
                    "DELETE FROM $table WHERE $column = ?",
                    [$this->id]
                );
            }
        }

        // Delete the player record
        $conn->executeStatement(
            'DELETE FROM players WHERE id = ? AND player_type = "tutorial"',
            [$this->id]
        );

        error_log("[TutorialPlayerEntity] Deleted tutorial player {$this->id} and related data");
    }
}
