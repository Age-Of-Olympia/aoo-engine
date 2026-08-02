<?php

namespace Tests\Various;

use App\Service\TurnProcessingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Whether a turn is due is a rule about the ENTITY, not about a session — a
 * playable building will have no browser behind it
 * (docs/design-playable-buildings.md §3.4).
 */
#[Group('entities-baseline')]
class TurnDueRuleTest extends LegacyPlayerFixtureTestCase
{
    /**
     * A request builds its player from scratch; a test that mutates the row
     * behind a live object would read its caches, not the database.
     */
    private function freshPlayer(int $id): \Classes\Player
    {
        $player = \App\Factory\PlayerFactory::legacy($id);
        $player->refresh_data();

        return $player;
    }

    public function testTheRuleAnswersWithoutAnySession(): void
    {
        $sessionBackup = $_SESSION ?? null;
        $_SESSION = [];

        try {
            $player = $this->createRealPlayer('GmTour');
            $now = 1_800_000_000;

            $id = (int) $player->id;

            $this->link->executeStatement(
                'UPDATE players SET nextTurnTime = ? WHERE id = ?',
                [$now + 3600, $id]
            );
            $this->assertFalse(
                (new TurnProcessingService())->isDue($this->freshPlayer($id), $now),
                'une heure trop tôt : rien à traiter'
            );

            $this->link->executeStatement(
                'UPDATE players SET nextTurnTime = ? WHERE id = ?',
                [$now - 1, $id]
            );
            $this->assertTrue(
                (new TurnProcessingService())->isDue($this->freshPlayer($id), $now),
                'l\'heure est passée : le tour est dû, sans qu\'aucune session le dise'
            );
        } finally {
            $_SESSION = $sessionBackup ?? [];
        }
    }

    /** An entity on no cell is still due: nowhere is not a waiting room. */
    public function testAnEntityOnNoCellIsStillDue(): void
    {
        $player = $this->createRealPlayer('GmNullePart');
        $now = 1_800_000_000;

        $this->link->executeStatement(
            'UPDATE players SET nextTurnTime = ?, coords_id = NULL WHERE id = ?',
            [$now - 1, (int) $player->id]
        );

        $this->assertTrue(
            (new TurnProcessingService())->isDue($this->freshPlayer((int) $player->id), $now)
        );
    }

    /** Before its hour: nothing runs, and nothing is written. */
    public function testProcessDueDoesNothingBeforeItsTime(): void
    {
        $player = $this->createRealPlayer('GmPatient');
        $now = 1_800_000_000;

        $this->link->executeStatement(
            'UPDATE players SET nextTurnTime = ? WHERE id = ?',
            [$now + 60, (int) $player->id]
        );

        $this->assertNull((new TurnProcessingService())->processDue($this->freshPlayer((int) $player->id), $now));
        $this->assertSame(
            $now + 60,
            (int) $this->link->fetchOne('SELECT nextTurnTime FROM players WHERE id = ?', [(int) $player->id]),
            'et l\'échéance n\'a pas bougé'
        );
    }
}
