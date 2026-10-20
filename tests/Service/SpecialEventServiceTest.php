<?php

namespace Tests\Service;

use App\Service\SpecialEventService;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Special events: who receives them, the line they leave, and the 1 PV floor
 * when the event may not kill.
 */
class SpecialEventServiceTest extends LegacyPlayerFixtureTestCase
{
    public function testMatriculesAreReadAsAListWithRanges(): void
    {
        $this->assertSame([12, 15, 20, 21, 22], SpecialEventService::parseMatricules('12, 15, 20-22'));
        $this->assertSame([], SpecialEventService::parseMatricules(''));
        $this->assertNull(SpecialEventService::parseMatricules('douze'));
        $this->assertNull(SpecialEventService::parseMatricules('30-20'));
    }

    public function testEveryCriterionNarrowsTheRecipients(): void
    {
        $a = $this->createRealPlayer('GmEventA');
        $b = $this->createRealPlayer('GmEventB');
        $a->get_data();
        $at = $a->getCoords();
        $service = new SpecialEventService();

        $byMatricule = $service->recipients('', SpecialEventService::RECIPIENTS_PLAYERS, null, [(int) $a->data->display_id]);
        $this->assertContains((int) $a->id, $byMatricule);
        $this->assertNotContains((int) $b->id, $byMatricule);

        $onA = ['x' => (int) $at->x, 'y' => (int) $at->y, 'z' => (int) $at->z, 'radius' => 0];
        $this->assertContains((int) $a->id, $service->recipients((string) $at->plan, SpecialEventService::RECIPIENTS_PLAYERS, $onA));
        $this->assertSame([], $service->recipients('gm_plan_absent', SpecialEventService::RECIPIENTS_PLAYERS));
    }

    public function testAnEventThatMayNotKillLeavesOnePvAndItsLine(): void
    {
        $a = $this->createRealPlayer('GmEventC');

        $result = (new SpecialEventService())->publish([(int) $a->id], 'La Terre tremble.', 100000);

        $this->assertSame(['published' => 1, 'deaths' => 0], $result);
        $reloaded = new \Classes\Player((int) $a->id);
        $reloaded->get_data();
        $reloaded->get_caracs();
        $this->assertSame(1, (int) $reloaded->getRemaining('pv'));

        $line = $this->link->fetchAssociative(
            "SELECT player_id, target_id, plan, coords_computed FROM players_logs WHERE player_id = ? AND type = 'special' AND text = 'La Terre tremble.'",
            [(int) $a->id]
        );
        $this->assertSame([(int) $a->id, (int) $a->id, '-', null],
            [(int) $line['player_id'], (int) $line['target_id'], $line['plan'], $line['coords_computed']]);
    }
}
