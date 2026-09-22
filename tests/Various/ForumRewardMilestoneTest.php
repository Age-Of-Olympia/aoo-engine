<?php

namespace Tests\Various;

use Classes\Player;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Every 50 PR earns one forum reward, once: the milestone is rewarded when
 * the player reaches it, not again when they leave it.
 */
class ForumRewardMilestoneTest extends LegacyPlayerFixtureTestCase
{
    private ?int $playerId = null;

    protected function tearDown(): void
    {
        if ($this->playerId !== null && $this->link !== null) {
            $this->link->executeStatement('DELETE FROM players_forum_rewards WHERE to_player_id = ?', [$this->playerId]);
        }
        parent::tearDown();
    }

    public function testAMilestoneIsRewardedOnce(): void
    {
        $player = $this->playerWithPr(40);

        $player->put_pr(10);
        $this->assertSame(1, $this->rewards(), '40 → 50: the milestone');

        $player->put_pr(10);
        $this->assertSame(1, $this->rewards(), '50 → 60: already rewarded');
    }

    public function testTheFirstGainIsNoMilestone(): void
    {
        $this->playerWithPr(0)->put_pr(10);

        $this->assertSame(0, $this->rewards());
    }

    public function testOneGainCanCrossTwoMilestones(): void
    {
        $this->playerWithPr(40)->put_pr(70);

        $this->assertSame(2, $this->rewards(), '40 → 110: 50 and 100, in one request');
    }

    private function playerWithPr(int $pr): Player
    {
        $player = $this->createRealPlayer('RecompenseForum');
        $this->playerId = (int) $player->id;
        $this->link->executeStatement('UPDATE players SET pr = ? WHERE id = ?', [$pr, $this->playerId]);
        $player->get_data();

        return $player;
    }

    private function rewards(): int
    {
        return (int) $this->link->fetchOne('SELECT COUNT(*) FROM players_forum_rewards WHERE to_player_id = ?', [$this->playerId]);
    }
}
