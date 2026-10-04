<?php

namespace Tests\Player;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\TeleportOutcomeInstruction;
use App\Entity\Action;
use App\Entity\ActionOutcome;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;
use Tests\Support\CachedBoard;

/**
 * View::get_free_coords_id_arround moves the coords it is given to the free
 * cell. Handed an entity's own coords, it moved that entity in memory: go()
 * lost the cell left and nobody watching it was told.
 */
#[Group('database')]
class ProjectionCoordsTest extends LegacyPlayerFixtureTestCase
{
    public function testAProjectionLeavesTheActorWhereItStands(): void
    {
        $actor = $this->createRealPlayer('GmLanceur');
        $target = $this->createRealPlayer('GmProjete');
        $this->link->executeStatement(
            'UPDATE players SET coords_id = ? WHERE id = ?',
            [(int) View::get_coords_id($this->tile(30, 0)), (int) $target->id]
        );
        $target = new \Classes\Player((int) $target->id);
        $watcher = $this->createRealPlayer('GmTemoin');
        $this->link->executeStatement(
            'UPDATE players SET coords_id = ? WHERE id = ?',
            [(int) View::get_coords_id($this->tile(31, 0)), (int) $watcher->id]
        );
        $board = CachedBoard::drawnFor((int) $watcher->id, 2);
        $before = clone $actor->getCoords();

        $outcome = $this->createMock(ActionOutcome::class);
        $outcome->method('getAction')->willReturn($this->createMock(Action::class));

        (new TeleportOutcomeInstruction())->setParameters(['coords' => 'projected'])->setOutcome($outcome)
            ->execute($actor, $target, new ConditionObject());

        $this->assertEquals($before, $actor->coords, 'the actor did not move');
        $this->assertFileDoesNotExist($board, 'whoever saw the target leave is told');
    }
}
