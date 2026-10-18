<?php

namespace Tests\Various;

use App\Service\ActionService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** An action acquired by default (actions.innate) is known by every character, with no row of its own. */
#[Group('action')]
class ActionsAcquiredByDefaultTest extends LegacyPlayerFixtureTestCase
{
    protected function tearDown(): void
    {
        ActionService::clearInnateCache();
        parent::tearDown();
    }

    public function testEveryCharacterKnowsItWithoutARow(): void
    {
        $this->actionOrSkip('fermer');
        $this->link->executeStatement("UPDATE actions SET innate = 1 WHERE name = 'fermer'");
        ActionService::clearInnateCache();

        $player = $this->createRealPlayer('GmInne');
        $this->link->executeStatement("DELETE FROM players_actions WHERE player_id = ? AND name = 'fermer'", [$player->id]);

        $this->assertContains('fermer', $player->get_actions());
        $this->assertNotEmpty($player->have_action('fermer'));
        $this->assertEmpty($player->have_action('action_inconnue_test'));
    }
}
