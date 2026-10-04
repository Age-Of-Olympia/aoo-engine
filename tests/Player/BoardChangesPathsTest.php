<?php

namespace Tests\Player;

use App\Service\EffectService;
use App\Service\Map\BoardChanges;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;
use Tests\Support\CachedBoard;

/** Changes that used to redraw only the actor's board, or nobody's. */
#[Group('database')]
class BoardChangesPathsTest extends LegacyPlayerFixtureTestCase
{
    protected function tearDown(): void
    {
        $this->link->executeStatement("DELETE FROM effects WHERE name IN ('oeil_test', 'rien_test')");
        EffectService::clearCache();
        parent::tearDown();
    }

    /** Blindness cast by someone else shrinks the target's board: it is redrawn. */
    public function testAPerceptionEffectRedrawsItsBearersBoard(): void
    {
        $this->link->executeStatement(
            "INSERT INTO effects (name, label, carac_mods) VALUES ('oeil_test', 'Oeil', '{\"p\":-1}'), ('rien_test', 'Rien', NULL)"
        );
        EffectService::clearCache();
        $bearer = $this->createRealPlayer('GmAveugle');
        $board = CachedBoard::drawnFor((int) $bearer->id);

        $bearer->add_effect('rien_test', 3);
        $this->assertFileExists($board, 'an effect off Perception leaves the board alone');

        $bearer->add_effect('oeil_test', 3);
        $this->assertFileDoesNotExist($board);
        $this->assertTrue(BoardChanges::isStale((int) $bearer->id));
    }

    /** A leap far across the plan reaches those who see where it lands. */
    public function testAJumpRedrawsTheBoardsAroundItsLanding(): void
    {
        $jumper = $this->createRealPlayer('GmSauteur');
        $watcher = $this->createRealPlayer('GmGuetteur');
        $this->link->executeStatement(
            'UPDATE players SET coords_id = ? WHERE id = ?',
            [(int) View::get_coords_id($this->tile(42, 0)), (int) $watcher->id]
        );
        $board = CachedBoard::drawnFor((int) $watcher->id, 3);

        $jumper->go($this->tile(40, 0));

        $this->assertFileDoesNotExist($board);
    }
}
