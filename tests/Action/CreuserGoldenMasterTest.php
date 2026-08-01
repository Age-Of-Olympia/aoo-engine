<?php

namespace Tests\Action;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * L'action 'creuser' de bout en bout (extraite de go.php, cadrage du
 * 2026-07-19) : coût 1 A en condition, galerie + pierre + malus sans
 * Pioche dans l'instruction digtunnel, XP par la règle du type 'search'
 * (1 = XP_PER_MINE). La case visée arrive par POST digX/digY — mêmes
 * gardes que le pas légitime (souterrain, adjacente, pas déjà creusée).
 */
#[Group('items-golden-master')]
class CreuserGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    private function actionOrSkip(): \App\Interface\ActionInterface
    {
        $action = ActionFactory::getAction('creuser');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'creuser' row — run migrations).");
        }

        return $action;
    }

    protected function tearDown(): void
    {
        unset($_POST['digX'], $_POST['digY']);
        $this->link->executeStatement(
            "DELETE t FROM map_tiles t JOIN coords c ON c.id = t.coords_id WHERE c.plan = 'gaia' AND c.z = -1"
        );
        parent::tearDown();
    }

    /** Téléporte le joueur de fixture sous terre (z = -1, gaia). */
    private function sendUnderground(\Classes\Player $digger, int $x, int $y): void
    {
        $coordsId = View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => -1, 'plan' => 'gaia']);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$coordsId, $digger->id]);
        $digger->getCoords();
    }

    public function testDiggingBareHandedMakesACaveAStoneAMalusAndCostsOneA(): void
    {
        $digger = $this->createRealPlayer('GmMiner');
        $digger->get_caracs();
        $maxA = (int) $digger->caracs->a;
        $this->sendUnderground($digger, 0, 3);
        $this->snapshotBloodAt((int) $digger->data->coords_id);

        $pierre = $this->itemOrSkip('pierre');
        $xpBefore = (int) $this->link->fetchOne('SELECT xp FROM players WHERE id = ?', [$digger->id]);
        $_POST['digX'] = '1';
        $_POST['digY'] = '3';

        $results = (new ActionExecutorService($this->actionOrSkip(), $digger, $digger))->executeAction();

        $this->assertFalse($results->isBlocked(), 'underground and adjacent: the dig must pass');
        $this->assertTrue($results->isSuccess());

        $fresh = PlayerFactory::legacy($digger->id);
        $dugId = View::get_coords_id((object) ['x' => 1, 'y' => 3, 'z' => -1, 'plan' => 'gaia']);
        $this->assertNotFalse(
            $this->link->fetchOne("SELECT 1 FROM map_tiles WHERE coords_id = ? AND name = 'caverne'", [$dugId]),
            'a caverne tile must be dug'
        );
        $this->assertSame(1, $pierre->get_n($fresh), 'digging yields 1 pierre');
        $this->assertSame($maxA - 1, $fresh->getRemaining('a'), 'digging costs exactly 1 A');
        $this->assertSame(
            MALUS_PER_MINE,
            (int) $this->link->fetchOne('SELECT malus FROM players WHERE id = ?', [$digger->id]),
            'digging bare-handed leaves the mine malus'
        );
        $this->assertSame(
            $xpBefore + XP_PER_MINE,
            (int) $this->link->fetchOne('SELECT xp FROM players WHERE id = ?', [$digger->id]),
            "the 'search' type rule grants exactly XP_PER_MINE"
        );
    }

    public function testDiggingAboveGroundIsRefused(): void
    {
        $digger = $this->createRealPlayer('GmSurface');
        $digger->getCoords();
        $digger->get_caracs();

        $maxA = (int) $digger->caracs->a;
        $_POST['digX'] = (string) ((int) $digger->coords->x + 1);
        $_POST['digY'] = (string) $digger->coords->y;

        $tilesBefore = (int) $this->link->fetchOne('SELECT COUNT(*) FROM map_tiles');
        $results = (new ActionExecutorService($this->actionOrSkip(), $digger, $digger))->executeAction();

        $this->assertTrue($results->isBlocked(), 'digging at z >= 0 must be BLOCKED (DigSite, before any payment)');
        $this->assertSame(
            $tilesBefore,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM map_tiles'),
            'no tile may be dug'
        );
        $this->assertSame(
            $maxA,
            PlayerFactory::legacy($digger->id)->getRemaining('a'),
            'a blocked dig must not cost the A'
        );
    }
}
