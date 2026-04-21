<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Temporary character used for a single tutorial run. Isolated from real
 * players, lives on its own map instance, and transfers its earned XP/PI
 * to the real player on completion. Discriminator: `player_type = 'tutorial'`.
 */
#[ORM\Entity]
class TutorialPlayer extends PlayerEntity
{
    #[ORM\Column(type: "string", length: 36, name: "tutorial_session_id", nullable: true)]
    protected ?string $tutorialSessionId = null;

    #[ORM\Column(type: "integer", name: "real_player_id_ref", nullable: true)]
    protected ?int $realPlayerIdRef = null;

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

    public function isPubliclyVisible(): bool
    {
        return false;
    }

    public function getTutorialSessionId(): ?string
    {
        return $this->tutorialSessionId;
    }

    public function setTutorialSessionId(?string $tutorialSessionId): self
    {
        $this->tutorialSessionId = $tutorialSessionId;
        return $this;
    }

    public function getRealPlayerIdRef(): ?int
    {
        return $this->realPlayerIdRef;
    }

    public function setRealPlayerIdRef(?int $realPlayerIdRef): self
    {
        $this->realPlayerIdRef = $realPlayerIdRef;
        return $this;
    }

    public function isTemporary(): bool
    {
        return true;
    }

    /**
     * Progression is tracked on `TutorialContext::$tutorialXP`, not on this row,
     * so the caller passes the earned deltas explicitly instead of reading
     * `$this->xp` / `$this->pi`.
     */
    public function transferRewardsToRealPlayer(
        \Doctrine\DBAL\Connection $conn,
        int $xpEarned,
        int $piEarned
    ): void {
        if (!$this->realPlayerIdRef) {
            throw new \RuntimeException("Cannot transfer rewards: real_player_id_ref is null");
        }

        $conn->executeStatement('
            UPDATE players
            SET xp = xp + ?, pi = pi + ?
            WHERE id = ? AND player_type = "real"
        ', [$xpEarned, $piEarned, $this->realPlayerIdRef]);
    }

    public function deleteWithRelatedData(\Doctrine\DBAL\Connection $conn): void
    {
        if (!$this->id) {
            return;
        }

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

        $conn->executeStatement(
            'DELETE FROM players WHERE id = ? AND player_type = "tutorial"',
            [$this->id]
        );
    }
}
