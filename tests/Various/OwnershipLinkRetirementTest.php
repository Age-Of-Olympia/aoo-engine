<?php

namespace Tests\Various;

use App\Service\OwnershipLinkRetirement;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** Whether the ownership link can be dropped, computed rather than remembered. */
#[Group('items-baseline')]
class OwnershipLinkRetirementTest extends LegacyPlayerFixtureTestCase
{
    public function testNothingWritesTheLinkSoNoRowContradictsTheEntity(): void
    {
        $status = (new OwnershipLinkRetirement($this->link))->status();

        $this->assertTrue($status['present'], 'the table is still there — the drop comes after the deploy');
        $this->assertSame(0, $status['disagreeing'], implode(' ; ', $status['blockers']));
        $this->assertTrue($status['droppable']);
    }

    /** A row that contradicts the entity holds the drop back. */
    public function testARowThatContradictsTheEntityBlocksTheDrop(): void
    {
        $player = $this->createRealPlayer('GmDivergent');
        $other = $this->createRealPlayer('GmAutre');
        $gladius = $this->itemOrSkip('gladius');

        $instanceId = (new \App\Service\ItemInstanceService())
            ->create((int) $player->id, (int) $gladius->id, (int) $player->id, '');
        $this->trackEntityId(
            (int) $this->link->fetchOne('SELECT entity_id FROM item_instances WHERE id = ?', [$instanceId])
        );

        $this->link->executeStatement(
            'INSERT INTO players_items_instances (player_id, instance_id) VALUES (?, ?)',
            [$other->id, $instanceId]
        );

        try {
            $status = (new OwnershipLinkRetirement($this->link))->status();

            $this->assertSame(1, $status['disagreeing']);
            $this->assertFalse($status['droppable']);
            $this->assertStringContainsString("écrit encore l'ancienne moitié", $status['blockers'][0]);
        } finally {
            $this->link->executeStatement(
                'DELETE FROM players_items_instances WHERE instance_id = ?',
                [$instanceId]
            );
        }
    }
}
