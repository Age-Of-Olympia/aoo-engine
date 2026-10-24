<?php

namespace Tests\Action;

use App\Entity\Action;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\View\ActionResultsView;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Bousculade: its non-blocking Push condition decides. A target that holds
 * makes the action a failure and gains no stability; a pushed one moves and
 * gains it.
 */
#[Group('database')]
class BousculadeStabilityTest extends LegacyPlayerFixtureTestCase
{
    private int $x;
    private ?int $sownOutcomeId = null;

    /** The CI catalog carries the bousculade without its steps: sow push + stability when absent. */
    protected function setUp(): void
    {
        parent::setUp();
        $actionId = (int) $this->link->fetchOne("SELECT id FROM actions WHERE name = 'bousculade'");
        if ($actionId === 0 || $this->link->fetchOne('SELECT 1 FROM action_outcomes WHERE action_id = ?', [$actionId])) {
            return;
        }
        $this->link->executeStatement(
            "INSERT INTO action_outcomes (name, on_success, action_id, apply_to) VALUES ('test_bousculade', 1, ?, 'target')",
            [$actionId]
        );
        $this->sownOutcomeId = (int) $this->link->lastInsertId();
        $this->link->executeStatement(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id) VALUES
             ('teleport', '{\"coords\":\"opposite\"}', 2, :o),
             ('applystatus', '{\"effect\":\"stabilite\",\"apply\":true,\"stackable\":true,\"value\":4,\"duration\":0}', 10, :o)",
            ['o' => $this->sownOutcomeId]
        );
    }

    protected function tearDown(): void
    {
        if ($this->sownOutcomeId !== null) {
            $this->link->executeStatement('DELETE FROM outcome_instructions WHERE outcome_id = ?', [$this->sownOutcomeId]);
            $this->link->executeStatement('DELETE FROM action_outcomes WHERE id = ?', [$this->sownOutcomeId]);
        }
        parent::tearDown();
    }

    /** @return array{0: Player, 1: Player} target one cell east of the actor, free cell behind; $trait ranks on $boosted */
    private function duel(string $boosted, string $trait): array
    {
        $actor = $this->createRealPlayer('GmBouscule');
        $target = $this->createRealPlayer('GmBousculee');
        [$this->x, $y] = $this->farTile();
        $this->movePlayerTo($actor->id, $this->x, $y);
        $this->movePlayerTo($target->id, $this->x + 1, $y);
        $this->coordsIdOn('gaia', $this->x + 2, $y);
        $boostedId = $boosted === 'actor' ? $actor->id : $target->id;
        for ($i = 0; $i < 40; $i++) {
            $this->link->executeStatement('INSERT INTO players_upgrades (player_id, name, cost) VALUES (?, ?, 0)', [$boostedId, $trait]);
        }

        $actor = PlayerFactory::legacy($actor->id);
        $target = PlayerFactory::legacy($target->id);
        $actor->getCoords(); $target->getCoords(); $actor->get_caracs(); $target->get_caracs();

        return [$actor, $target];
    }

    private function bousculade(): Action
    {
        $action = $this->actionOrSkip('bousculade');
        $this->assertInstanceOf(Action::class, $action);

        return $action;
    }

    public function testATargetThatResistsStaysAndGainsNoStability(): void
    {
        [$actor, $target] = $this->duel('target', 'e');

        $results = (new ActionExecutorService($this->bousculade(), $actor, $target))->executeAction();
        $html = (new ActionResultsView($results))->getActionResults();

        $this->assertSame($this->x + 1, (int) PlayerFactory::legacy($target->id)->getCoords()->x, 'the target did not move');
        $this->assertSame(0, PlayerFactory::legacy($target->id)->have_effect('stabilite'), 'no stability without a push');
        $this->assertStringContainsString('Echec !', $html);
        $this->assertStringContainsString('reste stable', $html);
    }

    public function testAPushedTargetMovesAndGainsStability(): void
    {
        [$actor, $target] = $this->duel('actor', 'f');

        $results = (new ActionExecutorService($this->bousculade(), $actor, $target))->executeAction();
        $html = (new ActionResultsView($results))->getActionResults();

        $this->assertSame($this->x + 2, (int) PlayerFactory::legacy($target->id)->getCoords()->x, 'pushed one cell further');
        $this->assertNotSame(0, PlayerFactory::legacy($target->id)->have_effect('stabilite'));
        $this->assertStringContainsString('Réussite !', $html);
        $this->assertStringContainsString('est repoussé', $html);
    }
}
